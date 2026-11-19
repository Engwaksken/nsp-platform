<?php

namespace App\Support;

/**
 * Links to our own CSS and JS with the file's last-change time as the version
 * (e.g. /css/nsp.css?v=1763712345). Browsers keep caching the file, but fetch the
 * new copy as soon as it changes, with no version number to remember to bump.
 */
class AssetVersion
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function url(string $path): string
    {
        return self::$cache[$path] ??= asset($path).'?v='.(@filemtime(public_path($path)) ?: '1');
    }
}
