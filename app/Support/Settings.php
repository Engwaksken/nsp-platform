<?php

namespace App\Support;

use App\Ai\AiSettings;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Site settings (branding, contact details, routing) stored as key/value rows.
 * Defaults use the Hive palette; administrators may override brand colors.
 */
class Settings
{
    public const CACHE_KEY = 'nsp.site_settings';

    public const FONTS = [
        'Poppins' => "'Poppins', system-ui, sans-serif",
        'Outfit' => "'Outfit', system-ui, sans-serif",
        'System' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
    ];

    public const DEFAULTS = [
        'site_name' => 'Hive NSP',
        'site_tagline' => 'Startup growth and ecosystem platform',
        'primary_color' => '#3a3a3c',
        'secondary_color' => '#f7941d',
        'accent_color' => '#f1592a',
        'font_family' => 'Poppins',
        'logo_media_id' => null,
        'favicon_media_id' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'contact_address' => null,
        'footer_text' => null,
        'social_links' => null,
        'contact_routing' => null,
        'meta_description' => null,
    ];

    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('site_settings')) {
                return [];
            }

            // "ai.*" rows (including the encrypted API key) belong to App\Ai\AiSettings and never reach page views.
            return SiteSetting::query()->where('key', 'not like', AiSettings::PREFIX.'%')->pluck('value', 'key')->all();
        });

        return array_merge(self::DEFAULTS, array_filter($stored, fn ($value) => $value !== null && $value !== ''));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /** JSON-encoded settings (social links, contact routing) as arrays. */
    public static function json(string $key): array
    {
        $decoded = json_decode((string) self::get($key, ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function fontStack(): string
    {
        return self::FONTS[self::get('font_family')] ?? self::FONTS['System'];
    }
}
