<?php

namespace App\Contracts;

use App\Exceptions\RegistryUnavailable;
use App\Models\User;

/**
 * Read access to the Hive Startup Registry for NSP pages. The registry is
 * hosted inside NSP for now (decision D2); if it moves to its own service,
 * an HTTP implementation of this interface (docs/api/openapi.yaml) replaces
 * the local one without changing callers.
 */
interface StartupRegistry
{
    public function isAvailable(): bool;

    /**
     * Startups the user is an active member of.
     *
     * @return list<array{uuid: string, display_name: string, role: string}>
     *
     * @throws RegistryUnavailable
     */
    public function startupsForUser(User $user): array;
}
