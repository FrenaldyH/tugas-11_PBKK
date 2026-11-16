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

        // Same order as production (Senopati first), with Ollama enabled to exercise the fallback
        config([
            'llm.order' => ['senopati', 'ollama'],
            'llm.retries' => 1,
            'llm.providers.senopati.base_url' => 'https://senopati.test/v1',
            'llm.providers.senopati.model' => 'qwen3:14b',
            'llm.providers.senopati.api_key' => 'its-secret',
            'llm.providers.ollama.base_url' => 'http://ollama.test/v1',
            'llm.providers.ollama.model' => 'qwen3:14b',
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
        $this->postJson('/api/chat-ai', ['prompt' => 'x', 'think' => 'mungkin'])
            ->assertJsonPath('errors.think.0', 'Mode berpikir harus true atau false.');
        Http::assertNothingSent();
    }

    public function test_chat_returns_resource_and_logs_to_database(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Http::fake(['senopati.test/*' => Http::response($this->completion('Middleware adalah...', 'qwen3:14b'))]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Jelaskan middleware!', 'theme' => 'prompt_engineer'])
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.model_used', 'qwen3:14b')
            ->assertJsonPath('data.prompt', 'Jelaskan middleware!')
            ->assertJsonPath('data.ai_response', 'Middleware adalah...')
            ->assertJsonPath('data.tokens.total', 20)
            ->assertJsonStructure(['status', 'data' => ['session_id', 'model_used', 'prompt', 'ai_response', 'timestamp']])
            ->assertJsonPath('data.think', false) // not sent -> LLM_THINK default (false)
            ->assertJsonMissingPath('data.user_id');

        $this->assertDatabaseHas('chat_logs', [
            'user_id' => $user->id, 'status' => 'success', 'provider' => 'senopati',
            'model' => 'qwen3:14b', 'prompt_tokens' => 12, 'completion_tokens' => 8, 'total_tokens' => 20,
        ]);

        // Same request shape as the campus curl example, plus the theme's system prompt as first message
        Http::assertSent(fn (Request $r) => $r->url() === 'https://senopati.test/v1/chat/completions'
            && $r->hasHeader('Authorization', 'Bearer its-secret')
            && $r['model'] === 'qwen3:14b'
            && $r['messages'][0]['role'] === 'system'
            && $r['messages'][0]['content'] === config('llm.themes.prompt_engineer')
            && $r['think'] === false
            && $r['stream'] === false);
    }

    public function test_think_flag_is_sent_to_gateway_and_logged(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Http::fake(['senopati.test/*' => Http::response($this->completion('Jakarta', 'qwen3:14b'))]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Ibu kota?', 'think' => true])
            ->assertCreated()
            ->assertJsonPath('data.think', true);

        Http::assertSent(fn (Request $r) => $r['think'] === true);
        $this->assertDatabaseHas('chat_logs', ['think' => true]);
    }

    public function test_falls_back_to_ollama_when_senopati_is_down(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Http::fake([
            'senopati.test/*' => fn () => throw new ConnectionException('Connection refused'),
            'ollama.test/*' => Http::response($this->completion('Jawaban Ollama', 'qwen3:14b')),
        ]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo'])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'ollama')
            ->assertJsonPath('data.ai_response', 'Jawaban Ollama')
            ->assertJsonPath('attempts', [
                ['provider' => 'senopati', 'ok' => false, 'reason' => 'tidak dapat dihubungi'],
                ['provider' => 'ollama', 'ok' => true, 'reason' => null],
            ]);
    }

    public function test_simulated_primary_outage_falls_back_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        Sanctum::actingAs(User::factory()->create());
        Http::fake([
            'senopati.test/*' => Http::response($this->completion('Jawaban Senopati', 'qwen3:14b')),
            'ollama.test/*' => Http::response($this->completion('Jawaban Ollama', 'deephat:latest')),
        ]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo', 'simulate_primary_down' => true])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'ollama')
            ->assertJsonPath('data.model_used', 'deephat:latest')
            ->assertJsonPath('attempts.0', ['provider' => 'senopati', 'ok' => false, 'reason' => 'disimulasikan mati']);

        // The simulated outage happens before any request, so Senopati is never called
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'senopati.test'));
    }

    public function test_simulation_is_ignored_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Sanctum::actingAs(User::factory()->create());
        Http::fake([
            'senopati.test/*' => Http::response($this->completion('Jawaban Senopati', 'qwen3:14b')),
            'ollama.test/*' => Http::response($this->completion('Jawaban Ollama', 'deephat:latest')),
        ]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo', 'simulate_primary_down' => true])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'senopati');
    }

    public function test_timeout_is_not_retried_and_falls_back(): void
    {
        // A timeout already used the whole LLM_TIMEOUT; retrying would multiply the wait
        config(['llm.retries' => 3, 'llm.timeout' => 60]);
        Sanctum::actingAs(User::factory()->create());
        $calls = 0;
        Http::fake([
            'senopati.test/*' => function () use (&$calls) {
                $calls++;
                throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds');
            },
            'ollama.test/*' => Http::response($this->completion('Jawaban Ollama', 'deephat:latest')),
        ]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo'])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'ollama')
            ->assertJsonPath('attempts.0.reason', 'timeout 60 s');

        $this->assertSame(1, $calls);
    }

    public function test_stream_timeout_is_recognised_by_elapsed_time(): void
    {
        // In stream mode a timeout surfaces as "Connection refused", like the real Senopati outage
        config(['llm.timeout' => 1]);
        Sanctum::actingAs(User::factory()->create());
        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Dari Ollama\"}}]}\n\ndata: [DONE]\n\n";
        Http::fake([
            'senopati.test/*' => function () {
                usleep(950_000);
                throw new ConnectionException('Connection refused for URI https://senopati.test/v1/chat/completions');
            },
            'ollama.test/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $body = $this->postJson('/api/chat-ai/stream', ['prompt' => 'Halo'])->streamedContent();

        $this->assertStringContainsString('"provider":"senopati","ok":false,"reason":"timeout 1 s"', $body);
        $this->assertStringContainsString('"provider":"ollama","ok":true', $body);
    }

    public function test_wrong_senopati_token_is_not_retried(): void
    {
        // A 401 will not heal by retrying, so only one request may be sent even with retries enabled
        config(['llm.order' => ['senopati'], 'llm.retries' => 3]);
        Sanctum::actingAs(User::factory()->create());
        Http::fake(['senopati.test/*' => Http::response(['error' => ['message' => 'Authentication Error']], 401)]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo'])->assertStatus(503);

        Http::assertSentCount(1);
    }

    public function test_returns_503_and_logs_failure_when_all_providers_fail(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo'])
            ->assertStatus(503)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('attempts.0.reason', 'HTTP 500')
            ->assertJsonPath('attempts.1.reason', 'HTTP 500');

        $this->assertDatabaseHas('chat_logs', ['status' => 'failed', 'response' => null]);
    }

    public function test_stream_sends_sse_events_and_logs_full_text(): void
    {
        Sanctum::actingAs(User::factory()->create());
        // Same shape as the real gateway in reasoning mode: reasoning_content chunks first, then content
        $sse = implode('', [
            "data: {\"choices\":[{\"delta\":{\"reasoning_content\":\"RAHASIA-PIKIRAN\"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{\"reasoning_content\":\" lanjut\"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{\"content\":\"Halo \"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{\"content\":\"dunia\"}}]}\n\n",
            "data: {\"choices\":[],\"usage\":{\"prompt_tokens\":5,\"completion_tokens\":2,\"total_tokens\":7}}\n\n",
            "data: [DONE]\n\n",
        ]);
        Http::fake(['senopati.test/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $response = $this->postJson('/api/chat-ai/stream', ['prompt' => 'Sapa aku', 'think' => true]);

        $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $body = $response->streamedContent();
        $this->assertStringContainsString("event: attempt\ndata: {\"provider\":\"senopati\",\"ok\":true,\"reason\":null}", $body);
        $this->assertSame(1, substr_count($body, 'event: thinking'), 'thinking is signalled exactly once');
        $this->assertStringContainsString("event: token\ndata: {\"delta\":\"Halo \"}", $body);
        $this->assertStringContainsString('event: done', $body);
        // The model's reasoning text must never reach the client or the database
        $this->assertStringNotContainsString('RAHASIA-PIKIRAN', $body);
        $this->assertDatabaseHas('chat_logs', [
            'response' => 'Halo dunia', 'think' => true, 'total_tokens' => 7, 'status' => 'success',
        ]);
    }

    public function test_stream_reports_fallback_path_before_tokens(): void
    {
        config(['app.debug' => true]);
        Sanctum::actingAs(User::factory()->create());
        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Dari Ollama\"}}]}\n\ndata: [DONE]\n\n";
        Http::fake(['ollama.test/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $body = $this->postJson('/api/chat-ai/stream', ['prompt' => 'Halo', 'simulate_primary_down' => true])
            ->streamedContent();

        $failed = strpos($body, '"provider":"senopati","ok":false,"reason":"disimulasikan mati"');
        $ok = strpos($body, '"provider":"ollama","ok":true');
        $token = strpos($body, 'event: token');
        $this->assertTrue($failed !== false && $ok !== false && $token !== false, $body);
        $this->assertTrue($failed < $ok && $ok < $token, 'path is reported before the answer starts');
        $this->assertDatabaseHas('chat_logs', ['provider' => 'ollama', 'response' => 'Dari Ollama']);
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
