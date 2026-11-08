<?php

namespace Tests\Feature\Integration;

use App\Models\Registry\OutboxEvent;
use App\Models\User;
use App\Registry\RegistryService;
use App\Support\Features;

class ConnectFlowTest extends IntegrationTestCase
{
    private const STATE = 'state-abcdefghijklmnop';

    private function connectUrl(array $overrides = []): string
    {
        return '/connect/ims-test?'.http_build_query(array_merge(['redirect_uri' => self::REDIRECT, 'state' => self::STATE], $overrides));
    }

    private function codeFrom(string $location): string
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(self::STATE, $query['state'] ?? null);

        return (string) ($query['code'] ?? '');
    }

    public function test_owner_turns_sharing_on_and_ims_receives_only_consented_fields(): void
    {
        $owner = User::factory()->withRole('startup_user')->create();
        $startup = $this->startupWithConsent($owner, consent: false);

        $this->actingAs($owner)->get($this->connectUrl())->assertOk()->assertSee('Green Fields')->assertSee('sharing will be turned on');
        $response = $this->actingAs($owner)->post('/connect/ims-test', ['redirect_uri' => self::REDIRECT, 'state' => self::STATE, 'startup_uuid' => $startup->uuid, 'decision' => 'approve']);
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT.'?', $location);
        $this->assertNotNull(app(RegistryService::class)->activeConsent($startup, 'ims_prefill'));

        $token = $this->withHeaders($this->bearer())->postJson('/api/v1/connect/token', ['code' => $this->codeFrom($location), 'redirect_uri' => self::REDIRECT]);
        $token->assertOk()->assertJsonPath('startup_uuid', $startup->uuid)->assertJsonPath('fields.sector', 'Health')
            ->assertJsonPath('fields.team_size', 6)->assertJsonPath('consent.purpose', 'ims_prefill')->assertJsonPath('user.email', $owner->email);
        $json = json_encode($token->json());
        foreach (['revenue_band', 'funding_raised_amount', 'traction_summary', 'Secret traction', '15000000', 'contact_phone'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'integration.profile_shared']);
    }

    public function test_codes_are_single_use_and_bound_to_client_and_redirect(): void
    {
        $owner = User::factory()->withRole('startup_user')->create();
        $startup = $this->startupWithConsent($owner);
        $issue = fn () => $this->codeFrom($this->actingAs($owner)->post('/connect/ims-test', ['redirect_uri' => self::REDIRECT, 'state' => self::STATE, 'startup_uuid' => $startup->uuid, 'decision' => 'approve'])->headers->get('Location'));

        $code = $issue();
        $this->withHeaders($this->bearer())->postJson('/api/v1/connect/token', ['code' => $code, 'redirect_uri' => 'https://evil.test/cb'])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $this->withHeaders($this->bearer())->postJson('/api/v1/connect/token', ['code' => $code, 'redirect_uri' => self::REDIRECT])->assertStatus(400);

        $code = $issue();
        $this->withHeaders($this->bearer())->postJson('/api/v1/connect/token', ['code' => $code, 'redirect_uri' => self::REDIRECT])->assertOk();
        $this->withHeaders($this->bearer())->postJson('/api/v1/connect/token', ['code' => $code, 'redirect_uri' => self::REDIRECT])->assertStatus(400);

        $code = $issue();
        $this->travel(6)->minutes();
        $this->withHeaders($this->bearer())->postJson('/api/v1/connect/token', ['code' => $code, 'redirect_uri' => self::REDIRECT])->assertStatus(400);
    }

    public function test_unregistered_redirects_and_bad_state_are_refused_without_redirecting(): void
    {
        $user = User::factory()->withRole('startup_user')->create();

        $this->actingAs($user)->get($this->connectUrl(['redirect_uri' => 'https://evil.test/steal']))->assertStatus(400);
        $this->actingAs($user)->get($this->connectUrl(['redirect_uri' => self::REDIRECT.'/extra']))->assertStatus(400);
        $this->actingAs($user)->get($this->connectUrl(['state' => 'short']))->assertStatus(400);
        $this->actingAs($user)->get('/connect/unknown-client?'.http_build_query(['redirect_uri' => self::REDIRECT, 'state' => self::STATE]))->assertStatus(400);
    }

    public function test_cancel_returns_access_denied(): void
    {
        $owner = User::factory()->withRole('startup_user')->create();
        $this->startupWithConsent($owner);

        $location = $this->actingAs($owner)->post('/connect/ims-test', ['redirect_uri' => self::REDIRECT, 'state' => self::STATE, 'decision' => 'deny'])->headers->get('Location');
        $this->assertSame(self::REDIRECT.'?error=access_denied&state='.self::STATE, $location);
    }

    public function test_only_owners_founders_and_admins_can_connect_and_founders_need_sharing_on(): void
    {
        $owner = User::factory()->withRole('startup_user')->create();
        $member = User::factory()->withRole('startup_user')->create(['email' => 'member@example.org']);
        $founder = User::factory()->withRole('startup_user')->create(['email' => 'founder@example.org']);
        $startup = $this->startupWithConsent($owner, consent: false);
        $registry = app(RegistryService::class);
        $registry->acceptInvitation($registry->invite($startup, $owner, 'member@example.org', 'team_member'), $member);
        $registry->acceptInvitation($registry->invite($startup, $owner, 'founder@example.org', 'founder'), $founder);
        $post = fn (User $user) => $this->actingAs($user)->post('/connect/ims-test', ['redirect_uri' => self::REDIRECT, 'state' => self::STATE, 'startup_uuid' => $startup->uuid, 'decision' => 'approve']);

        // A team member cannot connect the startup, whether sharing is on or not.
        $post($member)->assertForbidden();
        $registry->grantConsent($startup, $owner, 'ims_prefill');
        $post($member)->assertForbidden();

        // A founder can connect once sharing is on.
        $this->assertStringStartsWith(self::REDIRECT.'?code=', $post($founder)->headers->get('Location'));
    }

    public function test_cannot_connect_a_startup_you_are_not_on(): void
    {
        $startup = $this->startupWithConsent();
        $stranger = User::factory()->withRole('startup_user')->create();

        $this->actingAs($stranger)->post('/connect/ims-test', ['redirect_uri' => self::REDIRECT, 'state' => self::STATE, 'startup_uuid' => $startup->uuid, 'decision' => 'approve'])->assertNotFound();
    }

    public function test_revoked_consent_makes_an_issued_code_useless(): void
    {
        $owner = User::factory()->withRole('startup_user')->create();
        $startup = $this->startupWithConsent($owner);
        $code = $this->codeFrom($this->actingAs($owner)->post('/connect/ims-test', ['redirect_uri' => self::REDIRECT, 'state' => self::STATE, 'startup_uuid' => $startup->uuid, 'decision' => 'approve'])->headers->get('Location'));

        app(RegistryService::class)->revokeConsent($startup, $owner, 'ims_prefill');
        $this->withHeaders($this->bearer())->postJson('/api/v1/connect/token', ['code' => $code, 'redirect_uri' => self::REDIRECT])->assertStatus(400);
        $this->assertTrue(OutboxEvent::query()->where('type', 'registry.consent.revoked.v1')->exists());
    }

    public function test_connect_page_allows_the_form_redirect_back_to_ims(): void
    {
        $owner = User::factory()->withRole('startup_user')->create();
        $this->startupWithConsent($owner);

        $csp = $this->actingAs($owner)->get($this->connectUrl())->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/form-action 'self'[^;]*https:\/\/ims\.test/", $csp);
        $this->assertDoesNotMatchRegularExpression('/ims\.test/', (string) $this->get('/')->headers->get('Content-Security-Policy'));
    }

    public function test_everything_is_off_when_the_feature_is_off(): void
    {
        Features::set('ims_integration', false, null);
        $owner = User::factory()->withRole('startup_user')->create();

        $this->actingAs($owner)->get($this->connectUrl())->assertNotFound();
        $this->withHeaders($this->bearer())->postJson('/api/v1/connect/token', ['code' => 'x', 'redirect_uri' => self::REDIRECT])->assertNotFound();
    }
}
