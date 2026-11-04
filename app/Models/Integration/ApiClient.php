<?php

namespace App\Models\Integration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** A connected system (for example Hive IMS). See docs/integration-contract-v1.md §1. */
class ApiClient extends Model
{
    public const SCOPES = ['registry.read', 'registry.link', 'events.write'];

    protected $fillable = ['client_id', 'name', 'redirect_uris', 'webhook_url', 'recipient_system', 'scopes', 'is_active'];

    protected $hidden = ['secret_hash', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'scopes' => 'array',
            'is_active' => 'boolean',
            'webhook_secret' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Set new secrets and return them in plain text (shown once).
     *
     * @return array{client_secret: string, webhook_secret: string}
     */
    public function rotateSecrets(): array
    {
        $clientSecret = Str::random(48);
        $webhookSecret = Str::random(48);
        $this->secret_hash = Hash::make($clientSecret);
        $this->webhook_secret = $webhookSecret;
        $this->save();

        return ['client_secret' => $clientSecret, 'webhook_secret' => $webhookSecret];
    }

    public function checkSecret(string $secret): bool
    {
        return Hash::check($secret, $this->secret_hash);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function allowsRedirect(string $uri): bool
    {
        return in_array($uri, $this->redirect_uris ?? [], true);
    }

    /** The data-sharing purpose that covers this client's system (for IMS: ims_prefill). */
    public function consentPurpose(): ?string
    {
        foreach (config('nsp.registry.consent_purposes') as $purpose => $definition) {
            if ($definition['recipient'] === $this->recipient_system) {
                return $purpose;
            }
        }

        return null;
    }
}
