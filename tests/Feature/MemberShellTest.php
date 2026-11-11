<?php

namespace Tests\Feature;

use App\Models\Funding\InvestorProfile;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Support\Features;
use App\Support\MemberNavigation;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The signed-in member area: app shell, role- and feature-aware sidebar, dashboard. */
class MemberShellTest extends TestCase
{
    private function member(): User
    {
        return User::factory()->withRole('startup_user')->create();
    }

    private function founderWithStartup(string $name = 'Green Fields', string $reg = '80020001234567', ?User $owner = null): array
    {
        $owner ??= $this->member();
        $this->actingAs($owner)->post('/workspace/startups', [
            'legal_name' => $name.' Ltd', 'display_name' => $name, 'registration_number' => $reg,
            'country' => 'UG', 'year_founded' => 2023, 'website' => 'https://'.strtolower(str_replace(' ', '', $name)).'.ug', 'description' => 'Soil testing.',
            'sector' => 'Agriculture & Agritech', 'headquarters_location' => 'Gulu', 'team_size' => 6,
            'contact_email' => 'hello@'.strtolower(str_replace(' ', '', $name)).'.ug',
        ])->assertRedirect();

        return [$owner, Startup::query()->where('display_name', $name)->firstOrFail()];
    }

    /** Every sidebar link of the given page. */
    private function sidebarLinks(string $html): array
    {
        $start = strpos($html, 'id="app-sidebar"');
        $sidebar = substr($html, $start, strpos($html, '</aside>', $start) - $start);
        preg_match_all('#<a href="([^"]+)"#', $sidebar, $matches);

        return array_unique($matches[1]);
    }

    public function test_new_member_gets_the_app_shell_and_a_dashboard_that_invites_them_to_register_a_startup(): void
    {
        $page = $this->actingAs($this->member())->get('/workspace')->assertOk();

        $page->assertSee('id="app-sidebar"', false)
            ->assertSee('id="app-user-menu"', false)
            ->assertDontSee('class="site-header"', false)
            ->assertDontSee('class="app-tabbar"', false)
            ->assertSee('Put your startup on the map')
            ->assertSee('Register a startup')
            ->assertSee('Quick actions')
            ->assertSee('Turn it on') // two-factor sign-in next step
            ->assertDontSee('Working on')
            ->assertDontSee('Admin area')
            ->assertDontSee('Deal flow');
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('workspace'), '#').'"\s+aria-current="page"#', $page->getContent());
    }

    public function test_founder_sidebar_folds_in_the_startup_links_and_every_link_opens(): void
    {
        [$owner, $startup] = $this->founderWithStartup();
        [, $second] = $this->founderWithStartup('Blue Wave', '80020009999999', $owner);
        $this->actingAs($owner)->post(route('workspace.startups.switch', $startup))->assertRedirect();

        $html = $this->actingAs($owner)->get('/workspace')->assertOk()
            ->assertSee('Working on')
            ->assertSee('Switch startup')
            ->assertSee(route('workspace.startups.switch', $second), false)
            ->assertSee(route('workspace.startups.team', $startup), false)
            ->assertSee('Your startups')
            ->assertSee('Profile complete')
            ->assertDontSee('Put your startup on the map')
            ->getContent();

        $links = $this->sidebarLinks($html);
        $this->assertGreaterThan(15, count($links));
        foreach ($links as $url) {
            $status = $this->actingAs($owner)->get($url)->getStatusCode();
            $this->assertLessThan(400, $status, "Sidebar link {$url} returned {$status}.");
        }
    }

    public function test_sidebar_hides_sections_whose_feature_is_off_and_the_dashboard_marks_them_not_available(): void
    {
        [$owner, $startup] = $this->founderWithStartup();
        Features::set('talent', false, null);
        Features::set('knowledge', false, null);

        $page = $this->actingAs($owner)->get('/workspace')->assertOk();
        $html = $page->getContent();
        $sidebar = implode(' ', $this->sidebarLinks($html));
        $this->assertStringNotContainsString('/jobs', $sidebar);
        $this->assertStringNotContainsString('/talent', $sidebar);
        $this->assertStringNotContainsString('/knowledge', $sidebar);
        $this->assertStringContainsString(route('workspace.funding', $startup), $sidebar);
        $page->assertSee('Not available yet');
    }

    public function test_role_sections_appear_only_for_members_with_that_role(): void
    {
        $investor = $this->member();
        $profile = new InvestorProfile(['organisation' => 'Seed Fund', 'investor_type' => 'vc', 'bio' => 'Early-stage fund.', 'currency' => 'USD',
            'ticket_min' => 10000, 'ticket_max' => 100000, 'sectors' => [], 'countries' => ['UG'], 'instruments' => ['equity'], 'stages' => [], 'is_listed' => true]);
        $profile->user_id = $investor->id;
        $profile->status = 'pending';
        $profile->save();

        // Unverified: only the profile (the deal-flow page would send them back to it).
        $this->actingAs($investor)->get('/workspace')->assertOk()
            ->assertSee('Investor profile')->assertDontSee('Deal flow')->assertSee('Waiting for verification');

        $profile->forceFill(['status' => 'verified'])->save();
        $html = $this->actingAs($investor)->get('/notifications')->assertOk()->assertSee('Deal flow')->getContent();
        $this->assertContains(route('investor.dashboard'), $this->sidebarLinks($html));

        // A member without the role is offered it on the dashboard instead.
        $this->actingAs($this->member())->get('/workspace')->assertOk()
            ->assertDontSee('Deal flow')->assertSee('Invest in startups');
    }

    public function test_admins_get_a_link_to_the_admin_area(): void
    {
        $this->actingAs($this->userWithRole('platform_admin'))->get('/workspace')->assertOk()
            ->assertSee('Admin area')->assertSee(route('admin.dashboard'), false);
    }

    public function test_member_pages_use_the_app_shell_and_public_pages_keep_the_site_header(): void
    {
        $member = $this->member();
        foreach (['/notifications', '/notifications/preferences', '/account/security', '/talent', '/marketplace/messages', '/knowledge/saved', '/organizations', '/workspace/startups'] as $url) {
            $this->actingAs($member)->get($url)->assertOk()
                ->assertSee('id="app-sidebar"', false)
                ->assertDontSee('class="site-header"', false);
        }
        foreach (['/', '/opportunities', '/jobs', '/knowledge'] as $url) {
            $this->actingAs($member)->get($url)->assertOk()
                ->assertSee('class="site-header"', false)
                ->assertDontSee('id="app-sidebar"', false);
        }
    }

    public function test_navigation_never_lists_a_route_that_does_not_exist(): void
    {
        [$owner] = $this->founderWithStartup();
        $this->actingAs($owner)->get('/workspace');
        $nav = MemberNavigation::for($owner);
        foreach ($nav->groups() as $group) {
            $this->assertNotEmpty($group['links'], "Group {$group['key']} is empty.");
        }
        // Not built yet: the challenge "mine" page and the startup organizations page have no view.
        $labels = collect($nav->groups())->pluck('links')->flatten(1)->pluck('label');
        $this->assertSame(view()->exists('challenges.mine'), $labels->contains('My challenges'));
        $this->assertTrue(Route::has('workspace.startups.overview'));
    }

    public function test_all_my_startups_keeps_the_sidebar_on_the_current_startup(): void
    {
        [$owner, $first] = $this->founderWithStartup('Green Fields', '80020001234567');
        [, $second] = $this->founderWithStartup('Blue Rivers', '80020007654321', $owner);
        $this->actingAs($owner)->post(route('workspace.startups.switch', $first))->assertRedirect();

        $html = $this->actingAs($owner)->get(route('workspace.startups.index'))->assertOk()->getContent();
        $start = strpos($html, 'id="app-sidebar"');
        $sidebar = substr($html, $start, strpos($html, '</aside>', $start) - $start);
        $this->assertStringContainsString(route('workspace.startups.overview', $first), $sidebar);
        $this->assertStringNotContainsString(route('workspace.startups.overview', $second), $sidebar);
    }

    public function test_rejected_investor_profile_is_not_described_as_waiting(): void
    {
        $investor = $this->member();
        $profile = new InvestorProfile(['organisation' => 'Seed Fund', 'investor_type' => 'vc', 'bio' => 'Early-stage fund.', 'currency' => 'USD',
            'ticket_min' => 10000, 'ticket_max' => 100000, 'sectors' => [], 'countries' => ['UG'], 'instruments' => ['equity'], 'stages' => [], 'is_listed' => true]);
        $profile->user_id = $investor->id;
        $profile->status = 'rejected';
        $profile->save();

        $this->actingAs($investor)->get('/workspace')->assertOk()
            ->assertSee('Not verified')
            ->assertSee('Hive did not verify your investor profile')
            ->assertDontSee('waiting for Hive to verify');
    }
}
