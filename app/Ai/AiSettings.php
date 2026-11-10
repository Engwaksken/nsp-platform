<?php

namespace App\Ai;

use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * AI assistant settings an administrator manages in Admin → AI assistant.
 * Stored as "ai.*" rows in site_settings (the API key encrypted with APP_KEY).
 * Anything not set there falls back to config/nsp.php (the .env values).
 */
class AiSettings
{
    public const CACHE_KEY = 'nsp.ai_settings';

    public const PREFIX = 'ai.';

    /** Models offered to admins, with prices in US$ per million input / output tokens. */
    public const MODELS = [
        'claude-opus-5-5' => ['label' => 'Claude Opus 5.5 (recommended)', 'input' => 4.00, 'output' => 20.00, 'fallbacks' => true],
        'claude-sonnet-5-5' => ['label' => 'Claude Sonnet 5.5 (faster, lower cost)', 'input' => 2.00, 'output' => 10.00, 'fallbacks' => true],
        'claude-haiku-5-5' => ['label' => 'Claude Haiku 5.5 (fastest, lowest cost)', 'input' => 0.10, 'output' => 0.50, 'fallbacks' => false],
        'claude-fable-5-1' => ['label' => 'Claude Fable 5.1 (most capable, highest cost)', 'input' => 10.00, 'output' => 50.00, 'fallbacks' => true],
    ];

    /** @var array<string, array{label: string, protocol: string, base_url: string, models: array<string, array{label: string, input: float, output: float}>}> */
    public const PROVIDERS = [
        'anthropic' => ['label' => 'Anthropic', 'protocol' => 'anthropic', 'base_url' => 'https://api.anthropic.com', 'models' => self::MODELS],
        'openai' => ['label' => 'OpenAI', 'protocol' => 'openai', 'base_url' => 'https://api.openai.com/v1', 'models' => [
            'gpt-4.1' => ['label' => 'GPT-4.1', 'input' => 2.00, 'output' => 8.00],
            'gpt-4.1-mini' => ['label' => 'GPT-4.1 mini', 'input' => .40, 'output' => 1.60],
            'gpt-4.1-nano' => ['label' => 'GPT-4.1 nano', 'input' => .10, 'output' => .40],
        ]],
        'gemini' => ['label' => 'Google Gemini', 'protocol' => 'gemini', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta', 'models' => [
            'gemini-2.5-pro' => ['label' => 'Gemini 2.5 Pro', 'input' => 1.25, 'output' => 10.00],
            'gemini-2.5-flash' => ['label' => 'Gemini 2.5 Flash', 'input' => .30, 'output' => 2.50],
        ]],
        'custom' => ['label' => 'Custom OpenAI-compatible API', 'protocol' => 'openai', 'base_url' => '', 'models' => []],
    ];

    public const EFFORTS = [
        'low' => 'Low: quickest and cheapest',
        'medium' => 'Medium: balanced (recommended)',
        'high' => 'High: most careful, uses more tokens',
    ];

    /** @return array<string, string|null> stored "ai.*" values without the prefix */
    public static function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('site_settings')) {
                return [];
            }

            return SiteSetting::query()->where('key', 'like', self::PREFIX.'%')->pluck('value', 'key')
                ->mapWithKeys(fn ($value, $key) => [substr($key, strlen(self::PREFIX)) => $value])->all();
        });
    }

    /** The settings the AI client and assistant use: admin values first, then .env. */
    public static function config(): array
    {
        $stored = self::stored();
        $config = config('nsp.ai');
        $limits = $config['daily_limits'];
        foreach (['user', 'startup', 'platform'] as $scope) {
            if (filled($stored['limit_'.$scope] ?? null)) {
                $limits[$scope] = (int) $stored['limit_'.$scope];
            }
        }
        $provider = filled($stored['provider'] ?? null) && isset(self::PROVIDERS[$stored['provider']]) ? $stored['provider'] : 'anthropic';
        $models = self::PROVIDERS[$provider]['models'];
        $model = filled($stored['model'] ?? null) && self::supportsModel($provider, $stored['model'])
            ? $stored['model'] : (self::supportsModel($provider, $config['model']) ? $config['model'] : (array_key_first($models) ?? $config['model']));

        return array_merge($config, [
            'provider' => $provider,
            'api_key' => self::storedKey($provider) ?? ($provider === 'anthropic' ? $config['api_key'] : null),
            'base_url' => $provider === 'custom' && filled($stored['base_url'] ?? null) ? $stored['base_url'] : (self::PROVIDERS[$provider]['base_url'] ?: ($config['base_url'] ?? null)),
            'model' => $model,
            'effort' => filled($stored['effort'] ?? null) && isset(self::EFFORTS[$stored['effort']]) ? $stored['effort'] : $config['effort'],
            'daily_limits' => $limits,
            'legal_approved' => filled($stored['legal_approved_at'] ?? null) || (bool) $config['legal_approved'],
        ]);
    }

    /** Where the API key comes from: "admin", "env" or null when there is none. */
    public static function keySource(): ?string
    {
        if (self::storedKey(self::config()['provider']) !== null) {
            return 'admin';
        }

        return self::config()['provider'] === 'anthropic' && filled(config('nsp.ai.api_key')) ? 'env' : null;
    }

    /** The last four characters only, for display. Never show the full key. */
    public static function maskedKey(): ?string
    {
        $key = self::config()['api_key'];

        return filled($key) ? '••••'.substr((string) $key, -4) : null;
    }

    /** @return array{by: string|null, at: string, reference: string|null}|null */
    public static function legalApproval(): ?array
    {
        $stored = self::stored();
        if (filled($stored['legal_approved_at'] ?? null)) {
            return ['by' => $stored['legal_approved_by'] ?? null, 'at' => $stored['legal_approved_at'], 'reference' => $stored['legal_reference'] ?? null];
        }

        return config('nsp.ai.legal_approved') ? ['by' => null, 'at' => '', 'reference' => 'Recorded in .env (NSP_AI_LEGAL_APPROVED)'] : null;
    }

    public static function supportsFallbacks(string $model): bool
    {
        return self::MODELS[$model]['fallbacks'] ?? false;
    }

    public static function supportsModel(string $provider, mixed $model): bool
    {
        return $provider === 'custom' ? is_string($model) && (bool) preg_match('/^[a-zA-Z0-9._:-]{1,100}$/', $model)
            : isset(self::PROVIDERS[$provider]['models'][$model]);
    }

    /** Estimated cost in US$ for a number of tokens on a model (unknown models use the default model's price). */
    public static function cost(?string $model, int $inputTokens, int $outputTokens): float
    {
        $price = self::MODELS[$model] ?? self::MODELS[self::priceKey($model)] ?? null;
        if (! $price) {
            foreach (self::PROVIDERS as $provider) {
                if (isset($provider['models'][$model])) {
                    $price = $provider['models'][$model];
                    break;
                }
            }
        }
        $price ??= self::MODELS['claude-opus-5-5'];

        return ($inputTokens * $price['input'] + $outputTokens * $price['output']) / 1_000_000;
    }

    /** @param array<string, string|null> $values keys without the "ai." prefix; null removes a value */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (($key === 'api_key' || str_starts_with($key, 'api_key_')) && $value !== null) {
                $value = Crypt::encryptString($value);
            }
            if ($value === null) {
                SiteSetting::query()->where('key', self::PREFIX.$key)->delete();
            } else {
                SiteSetting::query()->updateOrCreate(['key' => self::PREFIX.$key], ['value' => (string) $value]);
            }
        }
        Cache::forget(self::CACHE_KEY);
    }

    private static function storedKey(string $provider = 'anthropic'): ?string
    {
        $stored = self::stored();
        $encrypted = $stored['api_key_'.$provider] ?? ($provider === 'anthropic' ? ($stored['api_key'] ?? null) : null);
        if (! filled($encrypted)) {
            return null;
        }
        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null; // APP_KEY changed: the admin has to enter the key again.
        }
    }

    /** Responses can name a dated or aliased model; match it to a priced model by prefix. */
    private static function priceKey(?string $model): string
    {
        foreach (array_keys(self::MODELS) as $key) {
            if ($model && str_starts_with($model, $key)) {
                return $key;
            }
        }

        return 'claude-opus-5-5';
    }
}
