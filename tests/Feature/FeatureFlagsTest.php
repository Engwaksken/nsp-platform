<?php

namespace Tests\Feature;

use App\Support\Features;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    public function test_locked_modules_cannot_be_switched_on(): void
    {
        $this->actingAs($this->userWithRole('platform_admin'))
            ->put('/admin/features/ai_assistant', ['enabled' => 1])
            ->assertSessionHasErrors('flags');

        $this->assertFalse(Features::enabled('ai_assistant'));
    }

    public function test_local_login_cannot_be_turned_off_while_sso_is_off(): void
    {
        $this->actingAs($this->userWithRole('platform_admin'))
            ->put('/admin/features/local_login', ['enabled' => 0])
            ->assertSessionHasErrors('flags');

        $this->assertTrue(Features::enabled('local_login'));
    }

    public function test_flag_changes_are_saved_and_audited(): void
    {
        $this->actingAs($this->userWithRole('platform_admin'))->put('/admin/features/oidc', ['enabled' => 1])->assertSessionHasNoErrors();

        $this->assertTrue(Features::enabled('oidc'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'feature_flag.changed']);
    }

    public function test_admin_page_lists_missing_setup_for_external_features(): void
    {
        config(['nsp.ai.api_key' => null, 'nsp.ai.legal_approved' => false, 'nsp.oidc.issuer' => null, 'nsp.ims_url' => null]);

        $this->actingAs($this->userWithRole('platform_admin'))
            ->get('/admin/features')
            ->assertOk()
            ->assertSee('Setup needed')
            ->assertSee('Add the Anthropic API key in Admin → AI assistant.')
            ->assertSee('Set OIDC_ISSUER in .env.')
            ->assertSee('Register Hive IMS as a connected system', false);
    }

    public function test_unfinished_capital_exchange_is_locked_and_its_pages_are_404(): void
    {
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->put('/admin/features/capital_exchange', ['enabled' => 1])->assertSessionHasErrors('flags');
        $this->assertFalse(Features::enabled('capital_exchange'));
        $this->actingAs($admin)->get('/workspace/startups/00000000-0000-0000-0000-000000000000/capital')->assertNotFound();
        $this->actingAs($admin)->get('/investors/pipeline')->assertNotFound();
    }

    public function test_unknown_flags_are_404(): void
    {
        $this->actingAs($this->userWithRole('platform_admin'))->put('/admin/features/nope', ['enabled' => 1])->assertNotFound();
        $this->assertFalse(Features::enabled('nope'));
    }

    public function test_workspace_marks_unbuilt_services_as_unavailable(): void
    {
        $this->actingAs($this->userWithRole('startup_user'))
            ->get('/workspace')
            ->assertOk()
            ->assertSee('My Startup Profile')
            ->assertSee('Funding &amp; Investors', false)
            ->assertSee('Not available yet');
    }
}
