<?php

namespace App\Support;

/** WCAG 2.1 contrast ratio helpers for admin-chosen brand colours. */
class Contrast
{
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

    private static function luminance(string $hex): ?float
    {
        if (! preg_match('/^#([0-9a-f]{6})$/i', $hex, $match)) {
            return null;
        }
        $channels = array_map(function (string $pair) {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split($match[1], 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
