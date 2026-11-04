<?php

namespace Tests\Feature;

use App\Support\Features;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    public function test_modules_that_are_not_built_cannot_be_switched_on(): void
    {
        $this->actingAs($this->userWithRole('platform_admin'))
            ->put('/admin/features/funding', ['enabled' => 1])
            ->assertSessionHasErrors('flags');

        $this->assertFalse(Features::enabled('funding'));
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
