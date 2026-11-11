<?php

namespace App\Services;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

// Talks to OpenAI-compatible Chat Completion servers (Ollama, Senopati) with retry + fallback.
class LlmClient
{
    // Build the messages array: theme system prompt first, then the user prompt.
    public function messages(string $prompt, string $theme = 'default'): array
    {
        return [
            ['role' => 'system', 'content' => config("llm.themes.$theme")],
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    // Wait for the full answer. Returns provider, model, content and token usage.
    public function chat(array $messages): array
    {
        foreach ($this->providers() as $name => $provider) {
            try {
                $json = $this->request($provider)
                    ->retry(config('llm.retries'), 200, fn (Throwable $e) => $this->isTransient($e))
                    ->post('/chat/completions', $this->payload($provider, $messages, false))
                    ->throw() // retry() only throws itself when tries > 1
                    ->json();

                return [
                    'provider' => $name,
                    'model' => $json['model'] ?? $provider['model'],
                    'content' => $json['choices'][0]['message']['content'] ?? '',
                    'usage' => $json['usage'] ?? [],
                ];
            } catch (Throwable $e) {
                Log::warning("LLM provider [$name] failed, trying next: ".$e->getMessage());
            }
        }

        throw new RuntimeException('Semua server AI tidak dapat dihubungi.');
    }

    // Stream the answer. Yields text pieces; the generator's return value holds provider/model/usage.
    // Fallback only happens before the first piece arrives; a mid-stream failure is thrown.
    public function stream(array $messages): Generator
    {
        foreach ($this->providers() as $name => $provider) {
            try {
                $response = $this->request($provider)
                    ->withOptions(['stream' => true])
                    ->post('/chat/completions', $this->payload($provider, $messages, true))
                    ->throw();
            } catch (Throwable $e) {
                Log::warning("LLM provider [$name] failed, trying next: ".$e->getMessage());

                continue;
            }

            $usage = yield from $this->readSse($response->toPsrResponse()->getBody());

            return ['provider' => $name, 'model' => $provider['model'], 'usage' => $usage];
        }

        throw new RuntimeException('Semua server AI tidak dapat dihubungi.');
    }

    // Parse "data: {...}" lines from an SSE body, yield each content delta, return usage.
    private function readSse($body): Generator
    {
        $buffer = '';
        $usage = [];

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            // Handle every complete line; keep a partial line in the buffer for the next read
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));
                if ($data === '[DONE]') {
                    return $usage;
                }

                $chunk = json_decode($data, true) ?? [];
                $usage = $chunk['usage'] ?? $usage;
                $delta = $chunk['choices'][0]['delta']['content'] ?? '';
                if ($delta !== '') {
                    yield $delta;
                }
            }
        }

        return $usage;
    }

    // Providers in fallback order, skipping ones without a URL or model configured.
    private function providers(): array
    {
        $all = config('llm.providers');

        return collect(config('llm.order'))
            ->mapWithKeys(fn ($name) => [$name => $all[$name] ?? null])
            ->filter(fn ($p) => ! empty($p['base_url']) && ! empty($p['model']))
            ->all();
    }

    // Base HTTP request for one provider.
    private function request(array $provider): PendingRequest
    {
        return Http::baseUrl(rtrim($provider['base_url'], '/'))
            ->timeout(config('llm.timeout'))
            ->acceptJson()
            ->when($provider['api_key'], fn ($http, $key) => $http->withToken($key));
    }

    // Chat Completion request body.
    private function payload(array $provider, array $messages, bool $stream): array
    {
        return array_filter([
            'model' => $provider['model'],
            'messages' => $messages,
            'temperature' => config('llm.temperature'),
            'stream' => $stream,
            // Ask for token usage in the final stream chunk
            'stream_options' => $stream ? ['include_usage' => true] : null,
        ], fn ($v) => $v !== null);
    }

    // Retry only errors that may heal: dropped connection or 5xx. A 4xx will fail again.
    private function isTransient(Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && $e->response->serverError());
    }
}
