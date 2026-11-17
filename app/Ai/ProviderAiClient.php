<?php

namespace App\Ai;

use Illuminate\Support\Facades\Http;
use Throwable;

/** HTTP adapter for Google Gemini and OpenAI-compatible providers (OpenAI, custom endpoints). */
class ProviderAiClient implements AiClient
{
    public function __construct(private readonly array $config) {}

    public function check(): ?string
    {
        if (! filled($this->config['api_key'] ?? null)) {
            return 'No API key is set.';
        }
        $base = self::normalizeBaseUrl((string) ($this->config['base_url'] ?? ''));
        $model = (string) $this->config['model'];
        try {
            if (($this->config['provider'] ?? '') === 'gemini') {
                $response = Http::withoutRedirecting()->withHeader('x-goog-api-key', $this->config['api_key'])->timeout(20)->get($base.'/models/'.rawurlencode($model));

                return $response->successful() ? null : $this->describeFailure($response->status(), $base.'/models/'.$model);
            }

            // Prefer the model list; not every OpenAI-compatible host offers one, and some
            // list only a subset of models, so fall back to a 1-token chat request.
            $list = Http::withoutRedirecting()->withToken($this->config['api_key'])->acceptJson()->timeout(20)->get($base.'/models');
            if ($list->successful() && collect($list->json('data', []))->contains(fn ($m) => ($m['id'] ?? null) === $model)) {
                return null;
            }
            if (in_array($list->status(), [401, 403], true)) {
                return $this->describeFailure($list->status(), $base.'/models');
            }

            $chat = Http::withoutRedirecting()->withToken($this->config['api_key'])->acceptJson()->timeout(30)->post($base.'/chat/completions', [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 1,
            ]);
            if ($chat->successful()) {
                return null;
            }
            if ($list->successful() && in_array($chat->status(), [400, 404], true)) {
                return 'The key works, but the model "'.$model.'" is not available at '.$base.'. Use the model ID exactly as the provider lists it.';
            }

            return $this->describeFailure($chat->status(), $base.'/chat/completions', $this->providerMessage($chat->json()));
        } catch (Throwable) {
            return 'The AI provider at '.$base.' could not be reached. Check the endpoint and server internet access.';
        }
    }

    /**
     * Accepts what admins commonly paste ("…/v1/", "…/v1/chat/completions", "…/v1/models")
     * and returns the API root that /models and /chat/completions are appended to.
     */
    public static function normalizeBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');

        return (string) preg_replace('#/(chat/completions|completions|models)$#i', '', $url);
    }

    private function describeFailure(int $status, string $url, ?string $detail = null): string
    {
        $suffix = $detail ? ' Provider said: "'.$detail.'"' : '';

        return match (true) {
            $status === 401 || $status === 403 => 'The API key was rejected. Check that it is active and has access to the selected model.'.$suffix,
            $status === 404 => 'Nothing was found at '.$url.'. The base URL usually ends in "/v1" (for example https://api.groq.com/openai/v1), and the model ID must match the provider exactly.'.$suffix,
            $status === 429 => 'The provider is rate limiting this key. Wait a moment and test again.'.$suffix,
            default => 'The AI provider returned HTTP '.$status.' from '.$url.'.'.$suffix,
        };
    }

    private function providerMessage(mixed $json): ?string
    {
        $message = is_array($json) ? ($json['error']['message'] ?? $json['message'] ?? null) : null;

        return is_string($message) && $message !== '' ? mb_strimwidth($message, 0, 200, '…') : null;
    }

    public function complete(string $system, string $user): AiResult
    {
        try {
            $url = self::normalizeBaseUrl((string) $this->config['base_url']);
            $key = (string) $this->config['api_key'];
            $model = (string) $this->config['model'];
            if (($this->config['provider'] ?? '') === 'gemini') {
                $response = Http::withoutRedirecting()->withHeader('x-goog-api-key', $key)->timeout((float) ($this->config['timeout'] ?? 60))->post($url.'/models/'.rawurlencode($model).':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                    'generationConfig' => ['maxOutputTokens' => (int) ($this->config['max_tokens'] ?? 4000)],
                ]);
                $text = collect($response->json('candidates.0.content.parts', []))->pluck('text')->implode('');
                $usageIn = (int) $response->json('usageMetadata.promptTokenCount', 0);
                $usageOut = (int) $response->json('usageMetadata.candidatesTokenCount', 0);
            } else {
                $response = Http::withoutRedirecting()->withToken($key)->timeout((float) ($this->config['timeout'] ?? 60))->post($url.'/chat/completions', [
                    'model' => $model,
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                    'max_tokens' => (int) ($this->config['max_tokens'] ?? 4000),
                ]);
                $text = (string) $response->json('choices.0.message.content', '');
                $usageIn = (int) $response->json('usage.prompt_tokens', 0);
                $usageOut = (int) $response->json('usage.completion_tokens', 0);
            }
            if (! $response->successful()) {
                return new AiResult('error', '', null, 0, 0, 'The AI service had a problem. Try again later.');
            }

            return new AiResult('ok', trim($text), $model, $usageIn, $usageOut);
        } catch (Throwable) {
            return new AiResult('error', '', null, 0, 0, 'The AI service could not be reached. Try again later.');
        }
    }
}
