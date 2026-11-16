<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatAiRequest;
use App\Http\Resources\ChatLogResource;
use App\Models\ChatLog;
use App\Services\LlmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

// Chat endpoints: ask the LLM (buffered or streamed) and list history.
class ChatAiController extends Controller
{
    public function __construct(private LlmClient $llm)
    {
    }

    // POST /api/chat-ai: wait for the full answer, log it, return it wrapped in ChatLogResource.
    public function store(ChatAiRequest $request): JsonResponse
    {
        $messages = $this->llm->messages($request->prompt, $request->theme());

        try {
            $result = $this->llm->chat($messages, $request->think());
        } catch (Throwable $e) {
            $this->record($request, ['status' => 'failed']);

            return response()->json(['status' => 'error', 'message' => $e->getMessage().' Coba lagi nanti.'], 503);
        }

        $log = $this->record($request, [
            'status' => 'success',
            'provider' => $result['provider'],
            'model' => $result['model'],
            'response' => $result['content'],
        ] + $this->tokens($result['usage']));

        return response()->json(['status' => 'success', 'data' => new ChatLogResource($log)], 201);
    }

    // POST /api/chat-ai/stream: Server-Sent Events.
    // Events: thinking {thinking} (once, only in reasoning mode), token {delta}, done {status,data}, error {message}.
    public function stream(ChatAiRequest $request): StreamedResponse
    {
        $messages = $this->llm->messages($request->prompt, $request->theme());

        return response()->stream(function () use ($request, $messages) {
            $text = '';
            $stream = $this->llm->stream($messages, $request->think());

            try {
                // The generator key is the event type: "thinking" or "token"
                foreach ($stream as $event => $delta) {
                    if ($event === 'thinking') {
                        $this->sendEvent('thinking', ['thinking' => true]);

                        continue;
                    }
                    $text .= $delta;
                    $this->sendEvent('token', ['delta' => $delta]);
                }
                $meta = $stream->getReturn();

                $log = $this->record($request, [
                    'status' => 'success',
                    'provider' => $meta['provider'],
                    'model' => $meta['model'],
                    'response' => $text,
                ] + $this->tokens($meta['usage']));

                $this->sendEvent('done', ['status' => 'success', 'data' => (new ChatLogResource($log))->resolve()]);
            } catch (Throwable $e) {
                Log::error('LLM stream failed: '.$e->getMessage());
                // Keep whatever arrived before the failure
                $this->record($request, ['status' => 'failed', 'response' => $text !== '' ? $text : null]);
                $this->sendEvent('error', ['status' => 'error', 'message' => 'Streaming gagal. Coba lagi nanti.']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // stop nginx from buffering the stream
        ]);
    }

    // GET /api/chat-ai/history: the caller's own logs, newest first, 10 per page.
    public function history(Request $request): AnonymousResourceCollection
    {
        return ChatLogResource::collection($request->user()->chatLogs()->latest('id')->paginate(10))
            ->additional(['status' => 'success']);
    }

    // Save one chat log row for the current user.
    private function record(ChatAiRequest $request, array $fields): ChatLog
    {
        return $request->user()->chatLogs()->create([
            'prompt' => $request->prompt,
            'theme' => $request->theme(),
            'think' => $request->think(),
        ] + $fields);
    }

    // Map OpenAI-style usage onto our token columns.
    private function tokens(array $usage): array
    {
        return [
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
        ];
    }

    // Write one SSE event and push it to the client now.
    private function sendEvent(string $event, array $data): void
    {
        echo "event: $event\ndata: ".json_encode($data)."\n\n";

        // ob_flush() raises a notice (turned into an exception by Laravel) when no buffer is open
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
