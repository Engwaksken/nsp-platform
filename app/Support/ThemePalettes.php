<?php

namespace App\Support;

use App\Models\User;

/**
 * Colour palettes a member can pick for their own view of the site (My profile → Appearance).
 * A palette replaces the brand's primary (sidebar, headers) and accent (highlights, badges)
 * colours for that member only; readable text colours are still worked out by Contrast.
 * Light, dark or device theme is a separate, per-browser choice (public/js/theme.js).
 */
class ThemePalettes
{
    /** The site's own brand colours (set by admins). */
    public const DEFAULT = 'brand';

    /** @var array<string, array{label: string, primary: string, accent: string}> */
    public const PALETTES = [
        'ocean' => ['label' => 'Ocean', 'primary' => '#1d3557', 'accent' => '#2a7de1'],
        'forest' => ['label' => 'Forest', 'primary' => '#1f3d2c', 'accent' => '#2f9e5c'],
        'teal' => ['label' => 'Lagoon', 'primary' => '#123c42', 'accent' => '#14a3a3'],
        'plum' => ['label' => 'Plum', 'primary' => '#35264a', 'accent' => '#8b5cf6'],
        'rose' => ['label' => 'Rose', 'primary' => '#3d2030', 'accent' => '#e0457b'],
        'ember' => ['label' => 'Ember', 'primary' => '#3b2420', 'accent' => '#e8572a'],
        'graphite' => ['label' => 'Graphite', 'primary' => '#2b2d31', 'accent' => '#6b7280'],
    ];

    /** @return list<string> every value a member may save, including the site default */
    public static function keys(): array
    {
        return [self::DEFAULT, ...array_keys(self::PALETTES)];
    }

    /** The brand settings with the member's palette applied (unchanged for guests and the default). */
    public static function apply(array $brand, ?User $user): array
    {
        $palette = self::PALETTES[$user?->theme_palette ?? ''] ?? null;
        if ($palette === null) {
            return $brand;
        }

        return array_merge($brand, [
            'primary_color' => $palette['primary'],
            'secondary_color' => $palette['primary'],
            'accent_color' => $palette['accent'],
        ]);
    }
}
