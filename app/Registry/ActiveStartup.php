<?php

namespace App\Registry;

use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;

/**
 * The startup a founder is currently working on (the startup selector).
 * It is re-checked on every request, so removing someone from a team takes
 * effect immediately even if their session still points at it.
 */
class ActiveStartup
{
    private const SESSION_KEY = 'active_startup_uuid';

    public static function membership(?User $user): ?StartupMembership
    {
        if (! $user) {
            return null;
        }
        $uuid = session(self::SESSION_KEY);
        $query = StartupMembership::query()->with('startup')
            ->where('user_id', $user->id)->where('status', 'active')
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'));

        $membership = $uuid ? (clone $query)->where('startup_uuid', $uuid)->first() : null;
        if (! $membership) {
            $membership = $query->orderBy('accepted_at')->first();
            session([self::SESSION_KEY => $membership?->startup_uuid]);
        }

        return $membership;
    }

    public static function switchTo(Startup $startup): void
    {
        session([self::SESSION_KEY => $startup->uuid]);
    }
}
