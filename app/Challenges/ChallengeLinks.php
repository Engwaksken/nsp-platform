<?php

namespace App\Challenges;

use Illuminate\Support\Facades\Route;
use Throwable;

/** Related VGI dimensions, Venture Builder steps and expert categories: links only when those routes exist. */
class ChallengeLinks
{
    /** @return array<string, list<array{key: string, url: ?string}>> label => items */
    public static function for(array $content): array
    {
        $groups = [];
        foreach (config('challenges.links') as $field => $link) {
            $keys = (array) ($content[$field] ?? []);
            if ($keys === []) {
                continue;
            }
            $groups[$link['label']] = array_map(fn ($key) => ['key' => (string) $key, 'url' => self::url($link, (string) $key)], $keys);
        }

        return $groups;
    }

    private static function url(array $link, string $key): ?string
    {
        if (! Route::has($link['route'])) {
            return null;
        }
        try {
            return route($link['route'], [$link['param'] => $key]);
        } catch (Throwable) {
            return null; // route needs other parameters (e.g. a startup): show as text
        }
    }
}
