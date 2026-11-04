<?php

namespace Tests\Feature\Integration;

use App\Integration\Signature;
use App\Models\Integration\ApiClient;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Registry\RegistryService;
use App\Support\Features;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected ApiClient $client;

    protected string $clientSecret;

    protected string $webhookSecret;

    protected const REDIRECT = 'https://ims.test/nsp-callback';

    protected function setUp(): void
    {
        parent::setUp();
        Features::set('ims_integration', true, null);
        $this->client = new ApiClient;
        $this->client->forceFill([
            'client_id' => 'ims-test', 'name' => 'Hive IMS', 'recipient_system' => 'ims',
            'redirect_uris' => [self::REDIRECT], 'webhook_url' => 'https://ims.test/nsp-webhook',
            'scopes' => ApiClient::SCOPES, 'is_active' => true, 'secret_hash' => '', 'webhook_secret' => '',
        ])->save();
        ['client_secret' => $this->clientSecret, 'webhook_secret' => $this->webhookSecret] = $this->client->rotateSecrets();
    }

    protected function bearer(): array
    {
        return ['Authorization' => 'Bearer ims-test.'.$this->clientSecret, 'Accept' => 'application/json'];
    }

    protected function startupWithConsent(?User $owner = null, bool $consent = true, array $core = []): Startup
    {
        $owner ??= User::factory()->withRole('startup_user')->create();
        $registry = app(RegistryService::class);
        $startup = $registry->createStartup($owner, array_merge([
            'legal_name' => 'Green Fields Ltd '.Str::random(4), 'display_name' => 'Green Fields', 'registration_number' => null,
            'country' => 'UG', 'year_founded' => 2023, 'description' => 'Soil testing.', 'website' => null,
        ], $core), ['sector' => 'Health', 'headquarters_location' => 'Gulu', 'team_size' => 6,
            'revenue_band' => 'under_10m', 'funding_raised_amount' => 15000000, 'traction_summary' => 'Secret traction'], true);
        if ($consent) {
            $registry->grantConsent($startup, $owner, 'ims_prefill');
        }

        return $startup->fresh('profile');
    }

    protected function sendEvent(array $envelope, ?int $timestamp = null, ?string $secret = null, ?string $body = null): TestResponse
    {
        $body ??= json_encode($envelope);
        $timestamp ??= time();
        $signature = Signature::sign($secret ?? $this->webhookSecret, $timestamp, $body);

        return $this->call('POST', '/api/v1/events', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ims-test.'.$this->clientSecret,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HIVE_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_HIVE_SIGNATURE' => $signature,
        ], $body);
    }

    protected function envelope(string $type, string $subject, array $data, ?string $eventId = null): array
    {
        return [
            'event_id' => $eventId ?? (string) Str::uuid(), 'type' => $type, 'schema_version' => 1,
            'occurred_at' => now()->toIso8601String(), 'source' => 'ims', 'subject_uuid' => $subject,
            'correlation_id' => null, 'data' => $data,
        ];
    }
}
