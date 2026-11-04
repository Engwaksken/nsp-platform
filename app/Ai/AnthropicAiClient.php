<?php

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Calls the Claude Messages API through the official PHP SDK.
 * - The SDK retries 408/409/429/5xx and connection errors (maxRetries) with backoff.
 * - Server-side refusal fallbacks are on ("default" routing), so a request a safety
 *   classifier declines on the main model is retried on a suitable model by the API.
 * - No tools are given to the model: its output is only ever text shown to the user.
 */
class AnthropicAiClient implements AiClient
{
    public function __construct(private readonly array $config) {}

    public function complete(string $system, string $user): AiResult
    {
        try {
            $client = new Client(apiKey: (string) $this->config['api_key'], baseUrl: $this->config['base_url'] ?? null, requestOptions: [
                'timeout' => (float) ($this->config['timeout'] ?? 60),
                'maxRetries' => (int) ($this->config['max_retries'] ?? 2),
            ]);
            $message = $client->beta->messages->create(
                maxTokens: (int) ($this->config['max_tokens'] ?? 4000),
                messages: [['role' => 'user', 'content' => $user]],
                model: (string) $this->config['model'],
                system: $system,
                outputConfig: ['effort' => (string) ($this->config['effort'] ?? 'medium')],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException $e) {
            return $this->failure('The AI service rejected our credentials.', $e);
        } catch (RateLimitException $e) {
            return $this->failure('The AI service is busy. Try again in a few minutes.', $e);
        } catch (BadRequestException $e) {
            return $this->failure('The AI service could not process this request.', $e);
        } catch (APIStatusException $e) {
            return $this->failure('The AI service had a problem. Try again later.', $e);
        } catch (APIConnectionException $e) {
            return $this->failure('The AI service could not be reached. Try again later.', $e);
        } catch (Throwable $e) {
            return $this->failure('The AI service had a problem. Try again later.', $e);
        }

        $usageIn = (int) ($message->usage->inputTokens ?? 0);
        $usageOut = (int) ($message->usage->outputTokens ?? 0);
        if ($message->stopReason === 'refusal') {
            return new AiResult('refused', '', $message->model, $usageIn, $usageOut);
        }
        $text = '';
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }

        return new AiResult('ok', trim($text), $message->model, $usageIn, $usageOut);
    }

    private function failure(string $message, Throwable $e): AiResult
    {
        Log::warning('[ai] request failed: '.class_basename($e).' '.mb_substr($e->getMessage(), 0, 300));

        return new AiResult('error', '', null, 0, 0, $message);
    }
}
