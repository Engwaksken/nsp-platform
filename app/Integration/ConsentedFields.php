<?php

namespace App\Integration;

use App\Models\Integration\ApiClient;
use App\Models\Registry\DataSharingConsent;
use App\Models\Registry\Startup;

/** The startup fields a client may receive: only what the startup consented to share with that system. */
class ConsentedFields
{
    public static function consent(Startup $startup, ApiClient $client): ?DataSharingConsent
    {
        $purpose = $client->consentPurpose();

        return $purpose === null ? null : DataSharingConsent::query()
            ->where('startup_uuid', $startup->uuid)->where('purpose', $purpose)->whereNull('revoked_at')->first();
    }

    /** @return array<string, mixed> */
    public static function for(Startup $startup, DataSharingConsent $consent): array
    {
        $profile = $startup->profile;
        $fields = [];
        // The scope stored with the consent is what the startup agreed to, even if the wording changes later.
        foreach ($consent->scope as $field) {
            if (in_array($field, Startup::CORE_FIELDS, true)) {
                $fields[$field] = $startup->{$field};
            } elseif ($profile && in_array($field, ['sector', 'sub_sector', 'headquarters_location', 'team_size'], true)) {
                $fields[$field] = $profile->{$field};
            }
            // Anything else (for example financial fields) is never sent through this API.
        }

        return $fields;
    }

    public static function payload(Startup $startup, DataSharingConsent $consent): array
    {
        return [
            'startup_uuid' => $startup->uuid,
            'version' => $startup->version,
            'fields' => self::for($startup, $consent),
            'consent' => [
                'purpose' => $consent->purpose,
                'policy_version' => $consent->policy_version,
                'granted_at' => $consent->granted_at->toIso8601String(),
            ],
        ];
    }
}
