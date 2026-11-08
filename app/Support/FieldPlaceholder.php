<?php

namespace App\Support;

/**
 * Suggests placeholder text for a text-like form field from its visible label and input type.
 * Placeholders are a hint only: every field keeps its <label>, so nothing depends on this text.
 */
class FieldPlaceholder
{
    /** Input types where a placeholder is not shown by browsers or would be misleading. */
    private const NONE = ['select', 'checkbox', 'radio', 'date', 'datetime-local', 'time', 'month', 'week', 'file', 'color', 'hidden', 'range'];

    public static function for(string $label, string $type = 'text'): ?string
    {
        if (in_array($type, self::NONE, true)) {
            return null;
        }

        // Drop asides such as "(optional)" or "(shown to the startup)".
        $plain = trim((string) preg_replace('/\s*\([^)]*\)/u', '', $label));
        if ($plain === '') {
            return null;
        }
        // Lower-case the first letter of ordinary words, but keep acronyms like "SEO" or "URL".
        $lower = preg_match('/^\p{Lu}\p{Ll}/u', $plain)
            ? mb_strtolower(mb_substr($plain, 0, 1)) . mb_substr($plain, 1)
            : $plain;
        $haystack = mb_strtolower($label);

        return match (true) {
            $type === 'email' || str_contains($haystack, 'email') => 'name@example.com',
            $type === 'search' => 'Search…',
            $type === 'password' && str_starts_with($lower, 'repeat') => 'Re-enter the password',
            $type === 'password' => 'Enter ' . (str_starts_with($lower, 'your ') ? $lower : 'your ' . $lower),
            str_contains($haystack, 'slug') => 'e.g. my-page-name',
            $type === 'url' || (bool) preg_match('/^website|linkedin|\(link\)|\burl$/u', $haystack) => 'https://',
            str_contains($haystack, 'comma') => 'Separate items with commas',
            (bool) preg_match('/\bphone\b/u', $haystack) => 'e.g. +256 700 000000',
            (bool) preg_match('/\byear\b/u', $haystack) && $type === 'number' => 'e.g. ' . date('Y'),
            str_contains($haystack, '(minutes)') => 'e.g. 60',
            (bool) preg_match('/^code\b/u', $haystack) => 'e.g. 123456',
            str_ends_with($plain, '?') => $type === 'textarea' ? 'Type your answer here…' : 'Type your answer',
            $type === 'textarea' && (bool) preg_match('/^(your|about|what|why|how)\b/u', $lower) => 'Write ' . $lower . '…',
            $type === 'textarea' => 'Enter ' . $lower . '…',
            default => 'Enter ' . $lower,
        };
    }
}
