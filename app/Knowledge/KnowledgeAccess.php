<?php

namespace App\Knowledge;

use App\Models\Knowledge\KnowledgeResource;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who may open which Knowledge Hub resource.
 *  public   - everyone
 *  members  - any signed-in, active user
 *  startups - signed-in users with an active membership of an active startup
 *  staff    - users who can use the admin area
 */
class KnowledgeAccess
{
    public static function visibilitiesFor(?User $user): array
    {
        $levels = ['public'];
        if (! $user || ! $user->is_active) {
            return $levels;
        }
        $levels[] = 'members';
        if (self::hasActiveStartup($user)) {
            $levels[] = 'startups';
        }
        if ($user->canUseAdmin()) {
            $levels[] = 'staff';
        }

        return $levels;
    }

    public static function canView(?User $user, KnowledgeResource $resource): bool
    {
        return $resource->isLive() && in_array($resource->visibility, self::visibilitiesFor($user), true);
    }

    public static function hasActiveStartup(User $user): bool
    {
        return self::activeMemberships($user)->exists();
    }

    /** Active startups the user belongs to (most recently joined first). */
    public static function activeStartups(User $user): Collection
    {
        $uuids = self::activeMemberships($user)->latest('accepted_at')->pluck('startup_uuid');

        return Startup::query()->whereIn('uuid', $uuids)->get();
    }

    private static function activeMemberships(User $user)
    {
        return StartupMembership::query()->where('user_id', $user->id)->where('status', 'active')
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'));
    }
}
