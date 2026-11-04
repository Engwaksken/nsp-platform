<?php

namespace App\Services\Registry;

use App\Contracts\StartupRegistry;
use App\Exceptions\RegistryUnavailable;

/** Bound while the registry is not built or the `registry` feature flag is off. */
class DisabledStartupRegistry implements StartupRegistry
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function startupsForUser(string $userSubject): array
    {
        throw new RegistryUnavailable('The startup registry is not connected yet.');
    }

    public function findStartup(string $startupUuid, string $userSubject): ?array
    {
        throw new RegistryUnavailable('The startup registry is not connected yet.');
    }
}
