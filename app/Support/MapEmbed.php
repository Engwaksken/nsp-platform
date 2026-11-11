<?php

namespace App\Support;

/**
 * The location map on the contact page (admin: Branding and settings, Contact details).
 * Admins paste a Google Maps or OpenStreetMap "embed" link, or the whole <iframe> snippet those
 * sites give; only embed addresses on these hosts are accepted, and only they may be framed
 * (see SecurityHeaders, contact page only).
 */
class MapEmbed
{
    /** @var array<string, string> host => required path prefix */
    public const ALLOWED = [
        'www.google.com' => '/maps/embed',
        'maps.google.com' => '/maps',
        'www.openstreetmap.org' => '/export/embed.html',
    ];

    /** The embed address from a pasted link or iframe snippet, or null when it is not an allowed map. */
    public static function normalize(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }
        if (preg_match('/<iframe[^>]*\ssrc=["\']([^"\']+)["\']/i', $input, $match)) {
            $input = $match[1];
        }
        $url = html_entity_decode($input, ENT_QUOTES | ENT_HTML5);
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host'], $parts['path'])) {
            return null;
        }
        $prefix = self::ALLOWED[strtolower($parts['host'])] ?? null;
        if ($prefix === null || ! str_starts_with($parts['path'], $prefix)) {
            return null;
        }
        // Google's plain "maps" links only embed with output=embed.
        if (strtolower($parts['host']) === 'maps.google.com' && ! str_contains((string) ($parts['query'] ?? ''), 'output=embed')) {
            return null;
        }

        return $url;
    }

    /** Origins the contact page may frame (for the Content-Security-Policy). */
    public static function frameSources(): string
    {
        return implode(' ', array_map(fn ($host) => 'https://'.$host, array_keys(self::ALLOWED)));
    }
}
