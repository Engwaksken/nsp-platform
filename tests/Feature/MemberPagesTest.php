<?php

namespace Tests\Feature;

use App\Challenges\ChallengeWorkflow;
use App\Models\Challenges\Challenge;
use App\Models\User;
use App\Organizations\OrganizationService;
use App\Registry\RegistryService;
use App\Support\Features;
use Tests\TestCase;

/** "My challenges", the member challenge form, and the startup Organizations page. */
class MemberPagesTest extends TestCase
{
    private function content(string $title): array
    {
        return [
            'title' => $title, 'problem_description' => 'A sufficiently clear description of the challenge problem.',
            'sector' => 'Agriculture & Agritech', 'country' => 'UG', 'priority' => 'high', 'category' => 'innovation', 'visibility' => 'public',
        ];
    }

    public function test_my_challenges_lists_submissions_proposals_and_bookmarks(): void
    {
        Features::set('challenges', true, null);
        $member = User::factory()->withRole('startup_user')->create();

        $this->actingAs($member)->get(route('challenges.mine'))->assertOk()
            ->assertSee('You have not submitted a challenge yet')
            ->assertSee(route('challenges.create'), false);

        $workflow = app(ChallengeWorkflow::class);
        $reviewer = User::factory()->withRole('platform_admin')->create();
        $mine = $workflow->saveContent(new Challenge, $this->content('Cold storage for fishing villages'), $member);
        $workflow->submit($mine, $member);
        $workflow->review($mine->fresh(), $reviewer, 'request_changes', 'Please add a source for the loss figures.', null, 'Internal comment.');

        $issuer = User::factory()->withRole('startup_user')->create();
        $other = $workflow->saveContent(new Challenge, $this->content('Rural clinic records'), $issuer);
        $workflow->submit($other, $issuer);
        $workflow->review($other->fresh(), $reviewer, 'approve', 'OK', null, 'Checked.');
        $workflow->publish($other->fresh(), $reviewer);
        $other = $other->fresh();

        $this->actingAs($member)->post(route('challenges.bookmark', $other))->assertRedirect();
        $this->actingAs($member)->post(route('challenges.propose', $other), [
            'summary' => 'Offline-first patient records synced over SMS.', 'approach' => 'Pilot in two clinics.', 'confirm' => '1',
        ])->assertRedirect();

        $this->actingAs($member)->get(route('challenges.mine'))->assertOk()
            ->assertSee('Cold storage for fishing villages')
            ->assertSee('Changes requested')
            ->assertSee('Please add a source for the loss figures.')
            ->assertDontSee('Internal comment.')
            ->assertSee('Rural clinic records')
            ->assertSee(route('challenges.edit', $mine), false);
    }

    public function test_member_can_create_a_draft_and_open_the_edit_form(): void
    {
        Features::set('challenges', true, null);
        $member = User::factory()->withRole('startup_user')->create();

        $this->actingAs($member)->get(route('challenges.create'))->assertOk()->assertSee('Submit a challenge')->assertSee('Save draft');

        $this->actingAs($member)->post(route('challenges.store'), $this->content('Pest alerts for smallholders') + ['action' => 'save'])
            ->assertRedirect();
        $challenge = Challenge::query()->where('submitted_by', $member->id)->sole();

        $this->actingAs($member)->get(route('challenges.edit', $challenge))->assertOk()
            ->assertSee('Pest alerts for smallholders')
            ->assertSee('Supporting evidence')
            ->assertSee(route('challenges.evidence.store', $challenge), false);
        $this->actingAs(User::factory()->create())->get(route('challenges.edit', $challenge))->assertNotFound();
    }

    public function test_my_challenges_shows_in_the_sidebar(): void
    {
        Features::set('challenges', true, null);
        $member = User::factory()->withRole('startup_user')->create();

        $this->actingAs($member)->get(route('workspace'))->assertOk()->assertSee(route('challenges.mine'), false);
    }

    private function startupWithRequest(): array
    {
        Features::set('organizations', true, null);
        Features::set('registry', true, null);
        $owner = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($owner, [
            'legal_name' => 'Solar Mills Ltd', 'display_name' => 'Solar Mills', 'registration_number' => null,
            'country' => 'UG', 'year_founded' => 2024, 'description' => null, 'website' => null,
        ], []);
        $staff = User::factory()->withRole('platform_admin')->create();
        $service = app(OrganizationService::class);
        $organization = $service->createOrganization($staff, ['name' => 'Kampala Hub', 'type' => array_key_first(config('organizations.types'))]);
        $service->changeStatus($organization, $staff, 'verify');
        $orgAdmin = User::factory()->create();
        $service->acceptInvitation($service->inviteMember($organization, $staff, $orgAdmin->email, 'org_admin', true), $orgAdmin);
        $request = $service->requestAccess($organization, $orgAdmin, $startup, 'portfolio', ['profile_summary'], 'We would like to support you.');

        return [$owner, $startup, $request];
    }

    public function test_startup_owner_sees_and_approves_organization_requests(): void
    {
        [$owner, $startup, $request] = $this->startupWithRequest();

        $this->actingAs($owner)->get(route('workspace.organizations', $startup))->assertOk()
            ->assertSee('Kampala Hub')
            ->assertSee('We would like to support you.')
            ->assertSee('Approve');

        $this->actingAs($owner)->post(route('workspace.organizations.decide', [$startup, $request]), ['decision' => 'approve', 'scopes' => ['profile_summary']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('active', $request->fresh()->status);

        $this->actingAs($owner)->get(route('workspace.organizations', $startup))->assertOk()
            ->assertSee('Who can see your startup')
            ->assertSee('Revoke');

        $this->actingAs($owner)->get(route('workspace'))->assertSee(route('workspace.organizations', $startup), false);
    }

    public function test_outsiders_cannot_open_a_startups_organizations_page(): void
    {
        [, $startup] = $this->startupWithRequest();

        $this->actingAs(User::factory()->withRole('startup_user')->create())
            ->get(route('workspace.organizations', $startup))->assertNotFound();
    }
}
