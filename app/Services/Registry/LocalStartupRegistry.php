<?php

namespace App\Services\Registry;

use App\Contracts\StartupRegistry;
use App\Models\Registry\StartupMembership;
use App\Models\User;

/** The registry module hosted in this application. */
class LocalStartupRegistry implements StartupRegistry
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function startupsForUser(User $user): array
    {
        return StartupMembership::query()
            ->where('user_id', $user->id)->where('status', 'active')
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'))
            ->with('startup:uuid,display_name')
            ->get()
            ->map(fn (StartupMembership $membership) => [
                'uuid' => $membership->startup_uuid,
                'display_name' => $membership->startup->display_name,
                'role' => $membership->role,
            ])
            ->sortBy('display_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
