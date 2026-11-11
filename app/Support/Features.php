<?php

namespace App\Support;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Feature flags: config/nsp.php gives defaults, the feature_flags table overrides them. */
class Features
{
    public const CACHE_KEY = 'nsp.feature_flags';

    public static function definitions(): array
    {
        return config('nsp.features', []);
    }

    public static function enabled(string $key): bool
    {
        $definition = self::definitions()[$key] ?? null;
        if ($definition === null) {
            return false;
        }
        $overrides = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('feature_flags')) {
                return [];
            }

            return FeatureFlag::query()->pluck('enabled', 'key')->map(fn ($value) => (bool) $value)->all();
        });

        return $overrides[$key] ?? (bool) $definition['default'];
    }

    public static function set(string $key, bool $enabled, ?int $userId): void
    {
        FeatureFlag::query()->updateOrCreate(['key' => $key], [
            'enabled' => $enabled,
            // The column holds 255 characters; the full text always comes from config/nsp.php.
            'description' => Str::limit((string) (self::definitions()[$key]['description'] ?? ''), 252) ?: null,
            'updated_by' => $userId,
        ]);
        Cache::forget(self::CACHE_KEY);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
