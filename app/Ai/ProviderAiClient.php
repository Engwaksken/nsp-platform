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
        try {
            $response = $this->config['provider'] === 'gemini'
                ? Http::withoutRedirecting()->withHeader('x-goog-api-key', $this->config['api_key'])->timeout(20)->get(rtrim($this->config['base_url'], '/').'/models/'.rawurlencode($this->config['model']))
                : Http::withoutRedirecting()->withToken($this->config['api_key'])->timeout(20)->get(rtrim($this->config['base_url'], '/').'/models');
            if ($response->successful()) {
                if (($this->config['provider'] ?? '') !== 'gemini') {
                    $available = collect($response->json('data', []))->contains(fn ($model) => ($model['id'] ?? null) === $this->config['model']);
                    if (! $available) {
                        return 'The key works, but the selected model is not available to this account.';
                    }
                }

                return null;
            }
            if ($response->status() === 401 || $response->status() === 403) {
                return 'The API key was rejected. Check that it is active and has access to the selected model.';
            }
            if ($response->status() === 404) {
                return 'The API endpoint or model was not found. Check the provider URL and selected model.';
            }

            return 'The AI provider returned HTTP '.$response->status().'. Check the provider configuration and try again.';
        } catch (Throwable) {
            return 'The AI provider could not be reached. Check the endpoint and server internet access.';
        }
    }

    public function complete(string $system, string $user): AiResult
    {
        try {
            $url = rtrim((string) $this->config['base_url'], '/');
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
