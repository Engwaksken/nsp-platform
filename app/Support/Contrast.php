<?php

namespace App\Support;

/** WCAG 2.1 contrast ratio helpers for admin-chosen brand colours. */
class Contrast
{
    /** Dark text used on light brand colours (matches --ink-strong in light mode). */
    public const DARK_TEXT = '#1f1f21';

    /**
     * Surfaces the brand colours are drawn on (keep in step with the tokens in public/css/nsp.css).
     * Text colours are checked against the darkest light surface and the lightest dark surface.
     */
    public const LIGHT_SURFACE = '#e8ebf0';   // --surface-sunken (light)

    public const DARK_SURFACE = '#262a30';    // --surface-sunken (dark)

    public const DARK_PAGE = '#121316';       // --page-bg (dark)

    public static function againstWhite(?string $hex): float
    {
        return self::ratio((string) $hex, '#ffffff');
    }

    public static function ratio(string $first, string $second): float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);
        if ($a === null || $b === null) {
            return 0.0;
        }

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Text colour for $background: white when it reaches AA (4.5:1), then the dark ink, and for
     * mid tones where neither does, black (white or black always reaches at least 4.58:1).
     */
    public static function textOn(?string $background): string
    {
        $background = (string) $background;
        $white = self::ratio($background, '#ffffff');
        if ($white >= 4.5 || self::luminance($background) === null) {
            return '#ffffff';
        }
        if (self::ratio($background, self::DARK_TEXT) >= 4.5) {
            return self::DARK_TEXT;
        }

        return self::ratio($background, '#000000') > $white ? '#000000' : '#ffffff';
    }

    /**
     * $hex itself when it already reaches $min against $background; otherwise $hex mixed
     * towards white (dark backgrounds) or black (light backgrounds) in 5% steps until it does.
     */
    public static function readable(?string $hex, string $background, float $min = 4.5): string
    {
        $hex = (string) $hex;
        $bg = self::luminance($background);
        if (self::luminance($hex) === null || $bg === null) {
            return $hex;
        }
        $towards = $bg < 0.18 ? '#ffffff' : '#000000';
        for ($step = 0; $step <= 20; $step++) {
            $candidate = self::mix($hex, $towards, $step / 20);
            if (self::ratio($candidate, $background) >= $min) {
                return $candidate;
            }
        }

        return $towards;
    }

    /** Mixes $weight (0 to 1) of $other into $hex, in sRGB like CSS color-mix(in srgb, ...). */
    public static function mix(string $hex, string $other, float $weight): string
    {
        $a = self::channels($hex);
        $b = self::channels($other);
        if ($a === null || $b === null) {
            return $hex;
        }

        return '#'.implode('', array_map(
            fn (int $x, int $y) => str_pad(dechex((int) round($x + ($y - $x) * $weight)), 2, '0', STR_PAD_LEFT),
            $a, $b
        ));
    }

    /**
     * CSS custom properties derived from the brand colours, so text on brand backgrounds and
     * brand-coloured text stay readable in light and dark mode (written by partials/head).
     *
     * @param  array{primary_color: ?string, secondary_color: ?string, accent_color: ?string}  $brand
     * @return array<string, string>
     */
    public static function brandTokens(array $brand): array
    {
        $primary = (string) $brand['primary_color'];
        $secondary = (string) $brand['secondary_color'];
        $accent = (string) $brand['accent_color'];
        if (self::luminance($primary) === null || self::luminance($secondary) === null || self::luminance($accent) === null) {
            return [];
        }
        // Dark mode buttons: the primary colour, lightened just enough to stand out from the page (3:1, WCAG 1.4.11).
        $darkButton = self::readable($primary, self::DARK_PAGE, 3.0);

        return [
            '--on-primary' => self::textOn($primary),
            '--on-secondary' => self::textOn($secondary),
            '--on-accent' => self::textOn($accent),
            '--brand-link-light' => self::readable($primary, self::LIGHT_SURFACE),
            '--brand-accent-text-light' => self::readable($accent, self::LIGHT_SURFACE),
            '--brand-link-dark' => self::readable($primary, self::DARK_SURFACE),
            '--brand-accent-text-dark' => self::readable($accent, self::DARK_SURFACE),
            '--brand-button-dark' => $darkButton,
            '--on-button-dark' => self::textOn($darkButton),
        ];
    }

    /** @return array{int, int, int}|null */
    private static function channels(string $hex): ?array
    {
        if (! preg_match('/^#([0-9a-f]{6})$/i', $hex, $match)) {
            return null;
        }

        return array_map('hexdec', str_split($match[1], 2));
    }

    private static function luminance(string $hex): ?float
    {
        $channels = self::channels($hex);
        if ($channels === null) {
            return null;
        }
        $channels = array_map(function (int $value) {
            $value /= 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
