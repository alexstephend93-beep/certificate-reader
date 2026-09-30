<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression tests for the AI Assistant widget.
 *
 * The original implementation only called keyless "free" providers, so every
 * request silently fell back to a canned reply. These tests pin down the
 * provider chain, the offline fallback and the image-error guard.
 */
class AIChatControllerTest extends TestCase
{
    private string $conversationId;

    protected function setUp(): void
    {
        parent::setUp();

        // The chat endpoint lives in the "web" group; the CSRF middleware is not
        // bypassed automatically in this Laravel version.
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Keep tests hermetic: the app's default cache store is the database, which
        // would leak conversation history between runs.
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->conversationId = 'test-'.uniqid();

        // Make the provider chain deterministic instead of depending on .env.
        config([
            'services.gemini.key' => 'test-gemini-key',
            'services.gemini.model' => 'gemini-test-model',
            'services.gemini.fallback_models' => [],
            'services.github.token' => 'test-github-token',
            'services.github.model' => 'gpt-4o-mini',
            'services.ai.api_key' => '',
            'services.ai.budget' => 10,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiPayload(string $text): array
    {
        return [
            'candidates' => [
                ['content' => ['parts' => [['text' => $text]]]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function githubPayload(string $text): array
    {
        return [
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => $text]],
            ],
        ];
    }

    public function test_it_answers_from_gemini_instead_of_the_canned_fallback(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                $this->geminiPayload('Run: sudo snap install postman')
            ),
            '*' => Http::response('', 500),
        ]);

        $this->postJson('/api/chat/send', ['message' => 'Ubuntu how to install postman'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider', 'gemini')
            ->assertJsonPath('response', 'Run: sudo snap install postman');

        Http::assertSent(
            fn (Request $request) => str_contains($request->url(), 'generativelanguage.googleapis.com')
        );
    }

    public function test_it_falls_back_to_github_models_when_gemini_is_unavailable(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429),
            'models.github.ai/*' => Http::response(
                $this->githubPayload('Install it with snap.')
            ),
            '*' => Http::response('', 500),
        ]);

        $this->postJson('/api/chat/send', ['message' => 'How do I install postman?'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider', 'github-models')
            ->assertJsonPath('response', 'Install it with snap.');
    }

    public function test_it_tries_the_next_gemini_model_when_one_is_overloaded(): void
    {
        config([
            'services.gemini.model' => 'gemini-primary',
            'services.gemini.fallback_models' => ['gemini-backup'],
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'gemini-primary')) {
                return Http::response(['error' => ['status' => 'UNAVAILABLE']], 503);
            }

            if (str_contains($request->url(), 'gemini-backup')) {
                return Http::response($this->geminiPayload('Served by the backup model'));
            }

            return Http::response('', 500);
        });

        $this->postJson('/api/chat/send', ['message' => 'hello'])
            ->assertOk()
            ->assertJsonPath('provider', 'gemini')
            ->assertJsonPath('response', 'Served by the backup model');
    }

    public function test_it_returns_an_honest_offline_reply_when_every_provider_fails(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $response = $this->postJson('/api/chat/send', ['message' => 'A totally unrelated question']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider', null);

        $this->assertStringContainsString(
            "couldn't reach any live AI model",
            $response->json('response')
        );
    }

    public function test_a_valid_answer_mentioning_model_or_unsupported_is_not_an_image_error(): void
    {
        // The old isApiImageInputError() matched the words "this model" and
        // "unsupported", which used to replace valid answers with an error.
        $answer = 'This model of certificate is unsupported by that CA, so ask the issuer.';

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiPayload($answer)),
            '*' => Http::response('', 500),
        ]);

        $this->postJson('/api/chat/send', ['message' => 'Explain my CA options'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('response', $answer)
            ->assertJsonMissingPath('is_image_error');
    }

    public function test_an_image_error_is_surfaced_when_the_user_references_a_file(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                $this->geminiPayload('image_url input is not allowed')
            ),
            '*' => Http::response('', 500),
        ]);

        $this->postJson('/api/chat/send', ['message' => 'Please read my report.pdf'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('is_image_error', true);
    }

    public function test_conversation_history_is_forwarded_as_gemini_turns(): void
    {
        $fake = fn () => Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiPayload('ok')),
            '*' => Http::response('', 500),
        ]);

        $fake();
        $this->postJson('/api/chat/send', [
            'message' => 'first',
            'conversation_id' => $this->conversationId,
        ]);

        $fake();
        $this->postJson('/api/chat/send', [
            'message' => 'second',
            'conversation_id' => $this->conversationId,
        ]);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }

            $contents = $request->data()['contents'] ?? [];

            return count($contents) === 3
                && $contents[0]['role'] === 'user'
                && $contents[1]['role'] === 'model'
                && $contents[2]['role'] === 'user';
        });
    }

    public function test_test_ai_endpoint_reports_the_working_provider(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                $this->geminiPayload('Hello! AI is working!')
            ),
            '*' => Http::response('', 500),
        ]);

        $this->getJson('/test-ai')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider', 'gemini');
    }
}
