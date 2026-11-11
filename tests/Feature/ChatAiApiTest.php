<?php

namespace Tests\Feature;

use App\Models\ChatLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// End-to-end checks for the secured chat API. LLM servers are faked with Http::fake().
class ChatAiApiTest extends TestCase
{
    use RefreshDatabase;

    // Point both providers at fake hosts and disable retry waits.
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'llm.order' => ['ollama', 'senopati'],
            'llm.retries' => 1,
            'llm.providers.ollama.base_url' => 'http://ollama.test/v1',
            'llm.providers.ollama.model' => 'qwen3:14b',
            'llm.providers.senopati.base_url' => 'https://senopati.test/v1',
            'llm.providers.senopati.model' => 'senopati-chat',
            'llm.providers.senopati.api_key' => 'its-secret',
        ]);
    }

    // A typical non-streaming Chat Completion response.
    private function completion(string $text, string $model): array
    {
        return [
            'model' => $model,
            'choices' => [['message' => ['role' => 'assistant', 'content' => $text]]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8, 'total_tokens' => 20],
        ];
    }

    public function test_login_returns_token_and_logout_revokes_it(): void
    {
        $this->freezeTime(); // expires_at is compared to the second
        $user = User::factory()->create(['password' => 'password']);

        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_at', now()->addMinutes(1440)->format('Y-m-d H:i:s'))
            ->json('access_token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
    }

    public function test_chat_requires_token_even_without_accept_header(): void
    {
        // Plain POST (no Accept: application/json) must still get JSON 401, not a redirect
        $this->post('/api/chat-ai', ['prompt' => 'Halo'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_prompt_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/chat-ai', [])->assertUnprocessable()->assertJsonValidationErrors('prompt');
        $this->postJson('/api/chat-ai', ['prompt' => str_repeat('a', 1001)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.prompt.0', 'Prompt maksimal 1000 karakter.');
        $this->postJson('/api/chat-ai', ['prompt' => 'x', 'theme' => 'hacker'])->assertJsonValidationErrors('theme');
        Http::assertNothingSent();
    }

    public function test_chat_returns_resource_and_logs_to_database(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Http::fake(['ollama.test/*' => Http::response($this->completion('Middleware adalah...', 'qwen3:14b'))]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Jelaskan middleware!', 'theme' => 'prompt_engineer'])
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.model_used', 'qwen3:14b')
            ->assertJsonPath('data.prompt', 'Jelaskan middleware!')
            ->assertJsonPath('data.ai_response', 'Middleware adalah...')
            ->assertJsonPath('data.tokens.total', 20)
            ->assertJsonStructure(['status', 'data' => ['session_id', 'model_used', 'prompt', 'ai_response', 'timestamp']])
            ->assertJsonMissingPath('data.user_id');

        $this->assertDatabaseHas('chat_logs', [
            'user_id' => $user->id, 'status' => 'success', 'provider' => 'ollama',
            'model' => 'qwen3:14b', 'prompt_tokens' => 12, 'completion_tokens' => 8, 'total_tokens' => 20,
        ]);

        // The theme's system prompt is injected as the first message
        Http::assertSent(fn (Request $r) => $r['messages'][0]['role'] === 'system'
            && $r['messages'][0]['content'] === config('llm.themes.prompt_engineer')
            && $r['stream'] === false);
    }

    public function test_falls_back_to_senopati_when_ollama_is_down(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Http::fake([
            'ollama.test/*' => fn () => throw new ConnectionException('Connection refused'),
            'senopati.test/*' => Http::response($this->completion('Jawaban Senopati', 'senopati-chat')),
        ]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo'])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'senopati')
            ->assertJsonPath('data.ai_response', 'Jawaban Senopati');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'senopati.test')
            && $r->hasHeader('Authorization', 'Bearer its-secret'));
    }

    public function test_returns_503_and_logs_failure_when_all_providers_fail(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo'])
            ->assertStatus(503)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('chat_logs', ['status' => 'failed', 'response' => null]);
    }

    public function test_stream_sends_sse_events_and_logs_full_text(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $sse = implode('', [
            "data: {\"choices\":[{\"delta\":{\"content\":\"Halo \"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{\"content\":\"dunia\"}}]}\n\n",
            "data: {\"choices\":[],\"usage\":{\"prompt_tokens\":5,\"completion_tokens\":2,\"total_tokens\":7}}\n\n",
            "data: [DONE]\n\n",
        ]);
        Http::fake(['ollama.test/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $response = $this->postJson('/api/chat-ai/stream', ['prompt' => 'Sapa aku']);

        $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $body = $response->streamedContent();
        $this->assertStringContainsString("event: token\ndata: {\"delta\":\"Halo \"}", $body);
        $this->assertStringContainsString('event: done', $body);
        $this->assertDatabaseHas('chat_logs', ['response' => 'Halo dunia', 'total_tokens' => 7, 'status' => 'success']);
    }

    public function test_history_only_lists_own_logs(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $me->chatLogs()->create(['prompt' => 'punyaku', 'status' => 'success']);
        $other->chatLogs()->create(['prompt' => 'punya orang', 'status' => 'success']);
        Sanctum::actingAs($me);

        $this->getJson('/api/chat-ai/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.prompt', 'punyaku');
        $this->assertSame(2, ChatLog::count());
    }
}
