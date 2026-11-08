<?php

namespace Tests\Feature\Integration;

use App\Models\Registry\StartupAlias;
use App\Models\User;
use App\Registry\RegistryService;
use Illuminate\Support\Str;

class RegistryApiTest extends IntegrationTestCase
{
    public function test_clients_must_authenticate_and_have_the_scope(): void
    {
        $startup = $this->startupWithConsent();

        $this->getJson("/api/v1/startups/{$startup->uuid}")->assertStatus(401);
        $this->withHeaders(['Authorization' => 'Bearer ims-test.wrong-secret'])->getJson("/api/v1/startups/{$startup->uuid}")->assertStatus(401);
        $this->withHeaders(['Authorization' => 'Bearer nobody.'.$this->clientSecret])->getJson("/api/v1/startups/{$startup->uuid}")->assertStatus(401);

        $this->client->forceFill(['scopes' => ['events.write']])->save();
        $this->withHeaders($this->bearer())->getJson("/api/v1/startups/{$startup->uuid}")->assertStatus(403)->assertJsonPath('error', 'insufficient_scope');

        $this->client->forceFill(['scopes' => ['registry.read'], 'is_active' => false])->save();
        $this->withHeaders($this->bearer())->getJson("/api/v1/startups/{$startup->uuid}")->assertStatus(401);
    }

    public function test_startup_fields_need_consent_and_exclude_private_figures(): void
    {
        $withConsent = $this->startupWithConsent();
        $without = $this->startupWithConsent(consent: false);

        $this->withHeaders($this->bearer())->getJson("/api/v1/startups/{$without->uuid}")->assertStatus(403)->assertJsonPath('error', 'consent_required');
        $response = $this->withHeaders($this->bearer())->getJson("/api/v1/startups/{$withConsent->uuid}")->assertOk()
            ->assertJsonPath('fields.headquarters_location', 'Gulu')->assertJsonMissingPath('fields.revenue_band');
        $this->assertStringNotContainsString('Secret traction', $response->getContent());
        $this->withHeaders($this->bearer())->getJson('/api/v1/startups/'.Str::uuid())->assertNotFound();
    }

    public function test_merged_startups_point_to_the_surviving_record(): void
    {
        $reviewer = User::factory()->withRole('super_admin')->create();
        $source = $this->startupWithConsent(core: ['registration_number' => 'UG-1']);
        $target = $this->startupWithConsent(core: ['registration_number' => 'UG-2']);
        app(RegistryService::class)->merge($source, $target, $reviewer, 'Confirmed same company.');

        $this->withHeaders($this->bearer())->getJson("/api/v1/startups/{$source->uuid}")
            ->assertStatus(301)->assertJsonPath('startup_uuid', $target->uuid);
    }

    public function test_links_are_idempotent_and_conflicts_are_refused(): void
    {
        $a = $this->startupWithConsent();
        $b = $this->startupWithConsent();

        $this->withHeaders($this->bearer())->postJson('/api/v1/startup-links', ['startup_uuid' => $a->uuid, 'external_id' => 'APP-1'])->assertCreated();
        $this->withHeaders($this->bearer())->postJson('/api/v1/startup-links', ['startup_uuid' => $a->uuid, 'external_id' => 'APP-1'])->assertOk();
        $this->withHeaders($this->bearer())->postJson('/api/v1/startup-links', ['startup_uuid' => $b->uuid, 'external_id' => 'APP-1'])->assertStatus(409);
        $this->withHeaders($this->bearer())->postJson('/api/v1/startup-links', ['startup_uuid' => $a->uuid])->assertStatus(422);
        $this->assertSame(1, StartupAlias::query()->where('source_system', 'ims')->count());
    }
}
