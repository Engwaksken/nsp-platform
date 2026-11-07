<?php

namespace Tests\Feature\Organizations;

use App\Models\Organizations\OrganizationMembership;
use App\Models\User;
use App\Organizations\OrganizationException;
use App\Organizations\OrganizationService;
use App\Organizations\PortfolioAccess;
use App\Registry\RegistryService;
use App\Support\Features;
use Tests\TestCase;

class PortfolioAccessTest extends TestCase
{
    public function test_startup_consent_is_scope_limited_and_revocation_takes_effect_immediately(): void
    {
        Features::set('organizations', true, null);
        Features::set('registry', true, null);

        $owner = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($owner, [
            'legal_name' => 'Consent Boundary Ltd', 'display_name' => 'Consent Boundary', 'registration_number' => null,
            'country' => 'UG', 'year_founded' => 2024, 'description' => null, 'website' => null,
        ], []);
        $staff = User::factory()->withRole('platform_admin')->create();
        $service = app(OrganizationService::class);
        $organization = $service->createOrganization($staff, [
            'name' => 'Verified ESO', 'type' => array_key_first(config('organizations.types')),
        ]);
        $service->changeStatus($organization, $staff, 'verify');
        $member = User::factory()->create();
        $token = $service->inviteMember($organization, $staff, $member->email, 'org_admin', true);
        $service->acceptInvitation($token, $member);
        $relationship = $service->share($startup, $owner, $organization, 'portfolio', ['profile_summary', 'funding_summary'], null, 'Test consent.');
        $access = app(PortfolioAccess::class);

        $this->assertTrue($access->userCanView($member, $organization, $startup, 'profile_summary'));
        $this->assertTrue($access->userCanView($member, $organization, $startup, 'funding_summary'));
        $this->assertFalse($access->userCanView($member, $organization, $startup, 'vgi_summary'));

        $secondAdmin = User::factory()->create();
        $adminToken = $service->inviteMember($organization, $staff, $secondAdmin->email, 'org_admin', true);
        $service->acceptInvitation($adminToken, $secondAdmin);
        $service->changeRole($organization, $staff, $organization->membershipOf($member), 'member', true);
        $this->assertFalse($access->userCanView($member, $organization, $startup, 'profile_summary'));
        $service->changeRole($organization, $staff, $organization->membershipOf($member), 'analyst', true);
        $this->assertTrue($access->userCanView($member, $organization, $startup, 'profile_summary'));

        $this->actingAs($member)->get(route('organizations.show', $organization))
            ->assertOk()->assertSee('Consent Boundary')->assertSee('Portfolio');

        $service->revoke($relationship, $owner);
        $this->assertFalse($access->canView($organization, $startup, 'profile_summary'));
        $this->assertSame([], $access->startupUuids($organization, 'profile_summary'));
        $this->actingAs($member)->get(route('organizations.show', $organization))
            ->assertOk()->assertSee('No startups have shared information');
        $this->actingAs($member)->get(route('organizations.startup', [$organization, $startup]))->assertNotFound();
    }

    public function test_expired_invitation_and_grant_and_suspended_organization_are_denied(): void
    {
        Features::set('organizations', true, null);
        Features::set('registry', true, null);

        $owner = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($owner, [
            'legal_name' => 'Expiry Boundary Ltd', 'display_name' => 'Expiry Boundary', 'registration_number' => null,
            'country' => 'UG', 'year_founded' => 2024, 'description' => null, 'website' => null,
        ], []);
        $staff = User::factory()->withRole('platform_admin')->create();
        $service = app(OrganizationService::class);
        $organization = $service->createOrganization($staff, ['name' => 'Expiry ESO', 'type' => 'eso']);
        $service->changeStatus($organization, $staff, 'verify');

        $invitee = User::factory()->create();
        $expiredToken = $service->inviteMember($organization, $staff, $invitee->email, 'analyst', true);
        $this->travel(8)->days();
        try {
            $service->acceptInvitation($expiredToken, $invitee);
            $this->fail('Expired invitations must not be accepted.');
        } catch (OrganizationException $exception) {
            $this->assertStringContainsString('no longer valid', $exception->getMessage());
        }

        $this->travelBack();
        $token = $service->inviteMember($organization, $staff, $invitee->email, 'org_admin', true);
        $service->acceptInvitation($token, $invitee);
        $relationship = $service->share($startup, $owner, $organization, 'portfolio', ['profile_summary'], now()->addDay(), 'One-day test grant.');
        $access = app(PortfolioAccess::class);
        $this->assertTrue($access->canView($organization, $startup, 'profile_summary'));
        $this->travel(2)->days();
        $this->assertFalse($access->canView($organization, $startup, 'profile_summary'));
        $this->assertSame('expired', $relationship->fresh()->displayStatus());
        $this->actingAs($invitee)->get(route('organizations.show', $organization))
            ->assertOk()->assertSee('No startups have shared information');

        $this->travelBack();
        $service->changeStatus($organization, $staff, 'suspend');
        $this->assertFalse($access->canView($organization, $startup, 'profile_summary'));
        $this->assertSame([], $access->startupUuids($organization, 'profile_summary'));
        $this->actingAs($invitee)->get(route('organizations.show', $organization))
            ->assertOk()->assertSee('Portfolio access is paused');
    }

    public function test_tenant_portal_hides_other_organizations_and_request_approval_can_only_narrow_scopes(): void
    {
        Features::set('organizations', true, null);
        Features::set('registry', true, null);

        $owner = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($owner, [
            'legal_name' => 'Request Boundary Ltd', 'display_name' => 'Request Boundary', 'registration_number' => null,
            'country' => 'UG', 'year_founded' => 2024, 'description' => null, 'website' => null,
        ], []);
        $staff = User::factory()->withRole('platform_admin')->create();
        $service = app(OrganizationService::class);
        $organization = $service->createOrganization($staff, ['name' => 'Requesting ESO', 'type' => 'eso']);
        $service->changeStatus($organization, $staff, 'verify');
        $anotherOrganization = $service->createOrganization($staff, ['name' => 'Separate ESO', 'type' => 'eso']);
        $service->changeStatus($anotherOrganization, $staff, 'verify');
        $member = User::factory()->create();
        $token = $service->inviteMember($organization, $staff, $member->email, 'manager', true);
        $service->acceptInvitation($token, $member);

        $this->actingAs($member)->get(route('organizations.show', $anotherOrganization))->assertNotFound();
        $this->actingAs($member)->get(route('organizations.members', $anotherOrganization))->assertNotFound();

        $requested = $service->requestAccess($organization, $member, $startup, 'portfolio', ['profile_summary', 'funding_summary'], 'Please share a summary.');
        $service->decide($requested, $owner, true, ['profile_summary'], null);
        $access = app(PortfolioAccess::class);
        $this->assertTrue($access->canView($organization, $startup, 'profile_summary'));
        $this->assertFalse($access->canView($organization, $startup, 'funding_summary'));
        $this->assertSame(['profile_summary'], $requested->fresh()->scopes);
    }

    public function test_invitation_email_and_single_use_and_last_admin_rules_hold_through_routes(): void
    {
        Features::set('organizations', true, null);
        $staff = User::factory()->withRole('platform_admin')->create();
        $service = app(OrganizationService::class);
        $organization = $service->createOrganization($staff, ['name' => 'Membership ESO', 'type' => 'eso']);
        $service->changeStatus($organization, $staff, 'verify');

        $admin = User::factory()->create();
        $token = $service->inviteMember($organization, $staff, $admin->email, 'org_admin', true);
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->get(route('organizations.invitations.show', $token))
            ->assertOk()->assertSee('sent to a different email address');
        $this->actingAs($otherUser)->post(route('organizations.invitations.accept', $token))
            ->assertRedirect(route('organizations.index'));
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $organization->id, 'status' => 'invited', 'user_id' => null]);

        $this->actingAs($admin)->post(route('organizations.invitations.accept', $token))
            ->assertRedirect(route('organizations.show', $organization));
        $membership = $organization->membershipOf($admin);
        $this->assertNotNull($membership);
        $this->actingAs($admin)->post(route('organizations.invitations.accept', $token))
            ->assertRedirect(route('organizations.index'));

        $this->actingAs($admin)->put(route('organizations.members.role', [$organization, $membership]), ['role' => 'analyst'])
            ->assertSessionHasErrors('role');
        $this->actingAs($admin)->from(route('organizations.members', $organization))->delete(route('organizations.members.remove', [$organization, $membership]))
            ->assertRedirect(route('organizations.members', $organization))->assertSessionHasErrors('member');
        $this->assertSame('active', $membership->fresh()->status);

        $member = User::factory()->create();
        $memberToken = $service->inviteMember($organization, $admin, $member->email, 'member');
        $service->acceptInvitation($memberToken, $member);
        $this->actingAs($member)->delete(route('organizations.members.remove', [$organization, $organization->membershipOf($member)]))
            ->assertRedirect(route('organizations.index'));
        $this->assertSame('revoked', $organization->memberships()->where('user_id', $member->id)->firstOrFail()->status);

        $this->assertInstanceOf(OrganizationMembership::class, $membership);
    }
}
