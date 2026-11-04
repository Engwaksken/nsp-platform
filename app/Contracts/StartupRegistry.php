<?php

namespace App\Contracts;

use App\Exceptions\RegistryUnavailable;

/**
 * Client for the Hive Startup Registry (system of record for startup identity).
 * The contract mirrors docs/api/openapi.yaml. Until the registry exists
 * (decision D2), the bound implementation reports that it is unavailable.
 */
interface StartupRegistry
{
    public function isAvailable(): bool;

    /**
     * Startups the signed-in user belongs to, as summaries.
     *
     * @return list<array{uuid: string, display_name: string, role: string}>
     *
     * @throws RegistryUnavailable
     */
    public function startupsForUser(string $userSubject): array;

    /**
     * One startup's fields that the caller is allowed to see.
     *
     * @throws RegistryUnavailable
     */
    public function findStartup(string $startupUuid, string $userSubject): ?array;
}
