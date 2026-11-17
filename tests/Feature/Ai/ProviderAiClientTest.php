<?php

namespace Tests\Feature\Ai;

use App\Ai\ProviderAiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderAiClientTest extends TestCase
{
    public function test_openai_compatible_provider_checks_model_and_normalizes_completion(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-4.1-mini']]]),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'A concise answer.']]],
                'usage' => ['prompt_tokens' => 13, 'completion_tokens' => 8],
            ]),
        ]);
        $client = new ProviderAiClient(['provider' => 'openai', 'api_key' => 'test-key', 'base_url' => 'https://api.openai.com/v1', 'model' => 'gpt-4.1-mini']);

        $this->assertNull($client->check());
        $result = $client->complete('system', 'user text');

        $this->assertSame(['ok', 'A concise answer.', 'gpt-4.1-mini', 13, 8], [$result->status, $result->text, $result->model, $result->inputTokens, $result->outputTokens]);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions' && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_custom_endpoint_url_is_normalized_and_chat_probe_is_used_without_model_list(): void
    {
        Http::fake([
            'https://api.example.com/v1/models' => Http::response(['error' => ['message' => 'Not found']], 404),
            'https://api.example.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'p']]]]),
        ]);
        $client = new ProviderAiClient(['provider' => 'custom', 'api_key' => 'test-key', 'base_url' => 'https://api.example.com/v1/chat/completions/', 'model' => 'openai/gpt-oss-120b']);

        $this->assertNull($client->check());
        Http::assertSent(fn ($request) => $request->url() === 'https://api.example.com/v1/chat/completions' && $request['model'] === 'openai/gpt-oss-120b' && $request['max_tokens'] === 1);
    }

    public function test_custom_endpoint_404_names_the_url_and_provider_message(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Unknown route']], 404)]);
        $client = new ProviderAiClient(['provider' => 'custom', 'api_key' => 'test-key', 'base_url' => 'https://api.example.com', 'model' => 'openai/gpt-oss-120b']);

        $error = $client->check();
        $this->assertStringContainsString('https://api.example.com/chat/completions', $error);
        $this->assertStringContainsString('/v1', $error);
        $this->assertStringContainsString('Unknown route', $error);
    }

    public function test_listed_provider_reports_unknown_model_clearly(): void
    {
        Http::fake([
            'https://api.example.com/v1/models' => Http::response(['data' => [['id' => 'other-model']]]),
            'https://api.example.com/v1/chat/completions' => Http::response(['error' => ['message' => 'model not found']], 404),
        ]);
        $client = new ProviderAiClient(['provider' => 'custom', 'api_key' => 'test-key', 'base_url' => 'https://api.example.com/v1', 'model' => 'openai/gpt-oss-120b']);

        $this->assertStringContainsString('model "openai/gpt-oss-120b" is not available', $client->check());
    }

    public function test_gemini_uses_api_key_header_and_extracts_text_and_usage(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash' => Http::response(['name' => 'models/gemini-2.5-flash']),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Gemini answer.']]]]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 6],
            ]),
        ]);
        $client = new ProviderAiClient(['provider' => 'gemini', 'api_key' => 'test-key', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta', 'model' => 'gemini-2.5-flash']);

        $this->assertNull($client->check());
        $result = $client->complete('system', 'user text');

        $this->assertSame(['ok', 'Gemini answer.', 'gemini-2.5-flash', 10, 6], [$result->status, $result->text, $result->model, $result->inputTokens, $result->outputTokens]);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), ':generateContent') && $request->hasHeader('x-goog-api-key', 'test-key'));
    }
}
