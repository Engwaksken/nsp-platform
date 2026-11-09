<?php

namespace Tests\Feature\Registry;

use App\Models\Registry\OutboxEvent;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupFieldChange;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryService;
use App\Support\Features;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StartupRegistryTest extends TestCase
{
    private function form(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'Green Fields Agritech Ltd', 'display_name' => 'Green Fields', 'registration_number' => '80020001234567',
            'country' => 'UG', 'year_founded' => 2023, 'website' => 'https://greenfields.ug', 'description' => 'Soil testing for smallholder farmers.',
            'sector' => 'Agriculture & Agritech', 'headquarters_location' => 'Gulu', 'team_size' => 6,
            'revenue_band' => 'under_10m', 'traction_summary' => '420 farmers served', 'funding_raised_amount' => 15000000, 'funding_currency' => 'UGX',
            'contact_email' => 'hello@greenfields.ug', 'contact_phone' => '+256700000001',
        ], $overrides);
    }

    private function founder(?string $email = null): User
    {
        return User::factory()->withRole('startup_user')->create($email ? ['email' => $email] : []);
    }

    private function registered(User $owner, array $overrides = []): Startup
    {
        $form = $this->form($overrides);
        $this->actingAs($owner)->post('/workspace/startups', $form)->assertRedirect();

        return Startup::query()->where('display_name', $form['display_name'])->where('created_by', $owner->id)->firstOrFail();
    }

    public function test_founder_registers_a_startup_and_becomes_owner(): void
    {
        $owner = $this->founder();
        $startup = $this->registered($owner);

        $this->assertSame('owner', $startup->membershipOf($owner)->role);
        $this->assertSame('greenfields.ug', $startup->website_domain);
        $this->assertSame('80020001234567', $startup->normalized_registration);
        $this->assertGreaterThan(80, $startup->profile->profile_completion);
        $this->assertTrue(OutboxEvent::query()->where('type', 'registry.startup.created.v1')->where('subject_uuid', $startup->uuid)->exists());
        $this->actingAs($owner)->get("/workspace/startups/{$startup->uuid}")->assertOk()->assertSee('Green Fields')->assertSee('420 farmers served');
    }

    public function test_sensitive_fields_are_encrypted_at_rest(): void
    {
        $startup = $this->registered($this->founder());

        $raw = DB::table('startup_profiles')->where('startup_uuid', $startup->uuid)->first();
        foreach (['revenue_band' => 'under_10m', 'traction_summary' => '420 farmers', 'funding_raised_amount' => '15000000', 'contact_phone' => '700000001'] as $column => $plain) {
            $this->assertStringNotContainsString($plain, (string) $raw->{$column}, "$column is stored in clear text");
        }
        $this->assertSame('420 farmers served', $startup->profile->traction_summary);
    }

    public function test_same_registration_number_or_website_cannot_create_a_second_record(): void
    {
        $this->registered($this->founder());
        $second = $this->founder();

        $this->actingAs($second)->post('/workspace/startups', $this->form(['legal_name' => 'Another Name', 'display_name' => 'Other', 'website' => null, 'registration_number' => '8002-0001-234567']))
            ->assertOk()->assertSee('This startup is already registered')->assertSee('Ask to join');
        $this->actingAs($second)->post('/workspace/startups', $this->form(['legal_name' => 'Another Name', 'display_name' => 'Other', 'registration_number' => null, 'website' => 'http://www.GreenFields.ug/about']))
            ->assertOk()->assertSee('This startup is already registered');
        // Even with the "different company" box ticked, strong evidence blocks a second record.
        $this->actingAs($second)->post('/workspace/startups', $this->form(['registration_number' => '80020001234567', 'confirm_different' => 1]))->assertOk();

        $this->assertSame(1, Startup::query()->count());
    }

    public function test_same_name_only_warns_and_queues_staff_review_when_confirmed(): void
    {
        $this->registered($this->founder());
        $second = $this->founder();
        $sameName = ['registration_number' => null, 'website' => null, 'legal_name' => 'Green Fields Agritech Limited'];

        $this->actingAs($second)->post('/workspace/startups', $this->form($sameName))->assertOk()->assertSee('Is this your startup?');
        $this->assertSame(1, Startup::query()->count());

        $this->actingAs($second)->post('/workspace/startups', $this->form($sameName + ['confirm_different' => 1]))->assertRedirect();
        $this->assertSame(2, Startup::query()->count());
        $this->assertDatabaseHas('startup_duplicate_candidates', ['status' => 'pending', 'score' => 30]);
    }

    public function test_two_founders_join_one_startup_by_invitation_without_duplicates(): void
    {
        $owner = $this->founder();
        $cofounder = $this->founder('cofounder@greenfields.ug');
        $startup = $this->registered($owner);

        $token = app(RegistryService::class)->invite($startup, $owner, 'CoFounder@greenfields.ug', 'founder');
        $this->actingAs($cofounder)->get("/invitations/{$token}")->assertOk()->assertSee('Accept and join');
        $this->actingAs($cofounder)->post("/invitations/{$token}/accept")->assertRedirect("/workspace/startups/{$startup->uuid}/overview");

        $this->assertSame(1, Startup::query()->count());
        $this->assertSame('founder', $startup->membershipOf($cofounder)->role);
        // A used invitation cannot be used again.
        $this->actingAs($cofounder)->post("/invitations/{$token}/accept")->assertSessionHasErrors('invite');
    }

    public function test_invitation_only_works_for_the_invited_email_and_expires(): void
    {
        $owner = $this->founder();
        $startup = $this->registered($owner);
        $stranger = $this->founder('stranger@example.org');
        $invitee = $this->founder('invitee@example.org');
        $service = app(RegistryService::class);

        $token = $service->invite($startup, $owner, 'invitee@example.org', 'team_member');
        $this->actingAs($stranger)->post("/invitations/{$token}/accept")->assertSessionHasErrors('invite');
        $this->assertNull($startup->membershipOf($stranger));

        $this->travel(8)->days();
        $this->actingAs($invitee)->post("/invitations/{$token}/accept")->assertSessionHasErrors('invite');
        $this->assertNull($startup->membershipOf($invitee));
    }

    public function test_founder_of_two_startups_cannot_open_a_third_by_changing_the_url(): void
    {
        $founder = $this->founder();
        $first = $this->registered($founder);
        $second = $this->registered($founder, ['legal_name' => 'Solar Kiosk Ltd', 'display_name' => 'Solar Kiosk', 'registration_number' => '111', 'website' => 'https://solarkiosk.ug']);
        $outsider = $this->registered($this->founder(), ['legal_name' => 'Secret Co', 'display_name' => 'Secret Co', 'registration_number' => '999', 'website' => 'https://secret.example']);

        $this->actingAs($founder)->get('/workspace/startups')->assertSee('Green Fields')->assertSee('Solar Kiosk')->assertDontSee('Secret Co');
        $this->actingAs($founder)->post("/workspace/startups/{$second->uuid}/switch")->assertRedirect();
        foreach (['', '/edit', '/team', '/sharing', '/history'] as $suffix) {
            $this->actingAs($founder)->get("/workspace/startups/{$outsider->uuid}{$suffix}")->assertNotFound();
        }
        $this->actingAs($founder)->put("/workspace/startups/{$outsider->uuid}", $this->form(['version' => 1]))->assertNotFound();
        $this->actingAs($founder)->post("/workspace/startups/{$outsider->uuid}/team", ['email' => 'x@y.z', 'role' => 'owner'])->assertNotFound();
        $this->actingAs($founder)->put("/workspace/startups/{$outsider->uuid}/sharing/ims_prefill", ['grant' => 1])->assertNotFound();
        $this->assertSame('Secret Co', $outsider->fresh()->display_name);
    }

    public function test_viewers_and_team_members_cannot_edit_or_see_editor_only_money(): void
    {
        $owner = $this->founder();
        $viewer = $this->founder('viewer@example.org');
        $startup = $this->registered($owner);
        $token = app(RegistryService::class)->invite($startup, $owner, 'viewer@example.org', 'viewer');
        $this->actingAs($viewer)->post("/invitations/{$token}/accept");

        $this->actingAs($viewer)->get("/workspace/startups/{$startup->uuid}/edit")->assertForbidden();
        $this->actingAs($viewer)->put("/workspace/startups/{$startup->uuid}", $this->form(['version' => 1, 'display_name' => 'Hacked']))->assertForbidden();
        // Financials default to "editors only"; traction defaults to the whole team.
        $this->actingAs($viewer)->get("/workspace/startups/{$startup->uuid}")->assertOk()
            ->assertDontSee('15,000,000')->assertSee('Only owners, founders and admins can see this.')->assertSee('420 farmers served');
    }

    public function test_concurrent_edits_are_refused_instead_of_overwriting(): void
    {
        $owner = $this->founder();
        $startup = $this->registered($owner);

        $this->actingAs($owner)->put("/workspace/startups/{$startup->uuid}", $this->form(['version' => 1, 'display_name' => 'First save']))->assertRedirect();
        $this->actingAs($owner)->put("/workspace/startups/{$startup->uuid}", $this->form(['version' => 1, 'display_name' => 'Stale save']))
            ->assertSessionHasErrors('version');

        $startup->refresh();
        $this->assertSame('First save', $startup->display_name);
        $this->assertSame(2, $startup->version);
    }

    public function test_change_history_never_contains_private_values(): void
    {
        $owner = $this->founder();
        $startup = $this->registered($owner);
        $this->actingAs($owner)->put("/workspace/startups/{$startup->uuid}", $this->form(['version' => 1, 'traction_summary' => 'Now 900 farmers', 'team_size' => 9]));

        $traction = StartupFieldChange::query()->where('startup_uuid', $startup->uuid)->where('field_name', 'traction_summary')->firstOrFail();
        $this->assertNull($traction->old_value);
        $this->assertSame('[changed]', $traction->new_value);
        $this->assertDatabaseHas('startup_field_changes', ['field_name' => 'team_size', 'old_value' => '6', 'new_value' => '9']);
        $event = OutboxEvent::query()->where('type', 'registry.startup.updated.v1')->firstOrFail();
        $this->assertStringNotContainsString('900', json_encode($event->data));
        $this->actingAs($owner)->get("/workspace/startups/{$startup->uuid}/history")->assertOk()->assertDontSee('Now 900 farmers');
    }

    public function test_join_request_needs_owner_approval(): void
    {
        $owner = $this->founder();
        $startup = $this->registered($owner);
        $requester = $this->founder();

        $this->actingAs($requester)->post("/workspace/startups/{$startup->uuid}/join", ['message' => 'I am the CTO'])->assertRedirect('/workspace/startups');
        $this->actingAs($requester)->get("/workspace/startups/{$startup->uuid}")->assertNotFound();

        $request = StartupMembership::query()->where('user_id', $requester->id)->firstOrFail();
        $this->actingAs($owner)->get("/workspace/startups/{$startup->uuid}/team")->assertSee('I am the CTO');
        $this->actingAs($owner)->post("/workspace/startups/{$startup->uuid}/team/{$request->uuid}/decision", ['decision' => 'approve', 'role' => 'admin'])->assertRedirect();

        $this->assertSame('admin', $startup->membershipOf($requester)->role);
        $this->actingAs($requester)->get("/workspace/startups/{$startup->uuid}")->assertOk();
    }

    public function test_role_rules_and_the_last_owner_cannot_leave(): void
    {
        $owner = $this->founder();
        $admin = $this->founder('admin@example.org');
        $startup = $this->registered($owner);
        $service = app(RegistryService::class);
        $this->actingAs($admin)->post('/invitations/'.$service->invite($startup, $owner, 'admin@example.org', 'admin').'/accept');
        $ownerMembership = $startup->membershipOf($owner);

        // An admin cannot invite owners or touch the owner.
        $this->actingAs($admin)->post("/workspace/startups/{$startup->uuid}/team", ['email' => 'new@example.org', 'role' => 'owner'])->assertSessionHasErrors('team');
        $this->actingAs($admin)->delete("/workspace/startups/{$startup->uuid}/team/{$ownerMembership->uuid}")->assertSessionHasErrors('team');
        // The only owner cannot step down or leave.
        $this->actingAs($owner)->put("/workspace/startups/{$startup->uuid}/team/{$ownerMembership->uuid}", ['role' => 'founder'])->assertSessionHasErrors('team');
        $this->actingAs($owner)->delete("/workspace/startups/{$startup->uuid}/team/{$ownerMembership->uuid}")->assertSessionHasErrors('team');
        $this->assertSame('owner', $ownerMembership->fresh()->role);
        $this->assertSame('active', $ownerMembership->fresh()->status);

        // After making the admin an owner, the first owner may leave.
        $adminMembership = $startup->membershipOf($admin);
        $this->actingAs($owner)->put("/workspace/startups/{$startup->uuid}/team/{$adminMembership->uuid}", ['role' => 'owner'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->delete("/workspace/startups/{$startup->uuid}/team/{$ownerMembership->uuid}")->assertRedirect('/workspace/startups');
        $this->assertNull($startup->membershipOf($owner));
    }

    public function test_consent_can_be_granted_and_revoked_and_revocation_is_published(): void
    {
        $owner = $this->founder();
        $member = $this->founder('member@example.org');
        $startup = $this->registered($owner);
        $this->actingAs($member)->post('/invitations/'.app(RegistryService::class)->invite($startup, $owner, 'member@example.org', 'team_member').'/accept');

        $this->actingAs($member)->put("/workspace/startups/{$startup->uuid}/sharing/ims_prefill", ['grant' => 1])->assertSessionHasErrors('consent');
        $this->actingAs($owner)->put("/workspace/startups/{$startup->uuid}/sharing/ims_prefill", ['grant' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull(app(RegistryService::class)->activeConsent($startup, 'ims_prefill'));

        $this->actingAs($owner)->put("/workspace/startups/{$startup->uuid}/sharing/ims_prefill", ['grant' => 0])->assertSessionHasNoErrors();
        $this->assertNull(app(RegistryService::class)->activeConsent($startup, 'ims_prefill'));
        $this->assertTrue(OutboxEvent::query()->where('type', 'registry.consent.revoked.v1')->where('subject_uuid', $startup->uuid)->exists());
        $this->assertDatabaseHas('data_sharing_consents', ['startup_uuid' => $startup->uuid, 'purpose' => 'ims_prefill', 'policy_version' => config('nsp.registry.consent_policy_version')]);
        $this->actingAs($owner)->put("/workspace/startups/{$startup->uuid}/sharing/unknown_purpose", ['grant' => 1])->assertSessionHasErrors('consent');
    }

    public function test_registry_pages_are_hidden_when_the_feature_is_off(): void
    {
        Features::set('registry', false, null);

        $this->actingAs($this->founder())->get('/workspace/startups')->assertNotFound();
        $this->actingAs($this->founder())->get('/workspace')->assertOk()->assertSee('Not available yet');
    }

    public function test_workspace_pages_have_a_sidebar_dashboard_and_startup_switcher(): void
    {
        $owner = $this->founder();
        $first = $this->registered($owner);
        $second = $this->registered($owner, ['legal_name' => 'Blue Wave Ltd', 'display_name' => 'Blue Wave', 'registration_number' => '80020009999999',
            'website' => 'https://bluewave.ug', 'contact_email' => 'hello@bluewave.ug']);

        $page = $this->actingAs($owner)->get("/workspace/startups/{$first->uuid}/overview")->assertOk();
        $page->assertSee('id="workspace-sidebar"', false)
            ->assertSee('Next steps')
            ->assertSee('Switch startup')
            ->assertSee(route('workspace.startups.switch', $second), false)
            ->assertSee('<a href="'.route('workspace.startups.overview', $first).'"  aria-current="page" >Dashboard</a>', false);

        // Deeper pages get the sidebar too, with the right section marked.
        $this->actingAs($owner)->get("/workspace/startups/{$first->uuid}/team")->assertOk()
            ->assertSee('id="workspace-sidebar"', false)
            ->assertSee('aria-current="page" >Team</a>', false);

        // Switching goes to the other startup's dashboard.
        $this->actingAs($owner)->post("/workspace/startups/{$second->uuid}/switch")->assertRedirect("/workspace/startups/{$second->uuid}/overview");

        // Registering a new startup has no startup yet, so no sidebar.
        $this->actingAs($owner)->get('/workspace/startups/new')->assertOk()->assertDontSee('id="workspace-sidebar"', false);
    }
}
