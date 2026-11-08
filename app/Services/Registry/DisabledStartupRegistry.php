<?php

namespace App\Services\Registry;

use App\Contracts\StartupRegistry;
use App\Exceptions\RegistryUnavailable;
use App\Models\User;

/** Bound while the `registry` feature flag is off. */
class DisabledStartupRegistry implements StartupRegistry
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function startupsForUser(User $user): array
    {
        throw new RegistryUnavailable('The startup registry is switched off.');
    }
}
