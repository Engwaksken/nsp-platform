<?php

namespace App\Support;

/** Validates public IMS opportunity embed URLs against the configured IMS origin. */
class ImsOpportunityEmbed
{
    public static function isValid(?string $url): bool
    {
        return self::normalize($url) !== null;
    }

    public static function normalize(?string $url): ?string
    {
        $configured = parse_url((string) config('nsp.ims_url'));
        $candidate = parse_url((string) $url);
        if (! is_array($configured) || ! is_array($candidate)
            || ! isset($configured['scheme'], $configured['host'], $candidate['scheme'], $candidate['host'], $candidate['path'])
            || isset($candidate['user']) || isset($candidate['pass']) || isset($candidate['fragment'])
            || strtolower($candidate['scheme']) !== strtolower($configured['scheme'])
            || strtolower($candidate['host']) !== strtolower($configured['host'])
            || ($candidate['port'] ?? null) !== ($configured['port'] ?? null)
            || ! in_array(strtolower($candidate['scheme']), ['https', 'http'], true)
            || ! preg_match('~(?:^|/)opportunity-embed(?:\.php)?/?$~i', $candidate['path'])) {
            return null;
        }

        return $url;
    }

    public static function origin(): ?string
    {
        $parts = parse_url((string) config('nsp.ims_url'));
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['https', 'http'], true)) {
            return null;
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
