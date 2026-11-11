<?php

namespace Tests\Feature\Organizations;

use App\Models\Organizations\Organization;
use App\Support\Features;
use Tests\TestCase;

/** The admin Organizations list and detail pages render, and an organization can be created from them. */
class AdminOrganizationPagesTest extends TestCase
{
    public function test_admin_can_list_create_and_open_organizations(): void
    {
        Features::set('organizations', true, null);
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->get(route('admin.organizations.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.organizations.store'), [
            'name' => 'Makerere Innovation Hub', 'type' => array_key_first(config('organizations.types')), 'country' => 'UG',
            'website' => 'https://hub.example.ug', 'description' => 'University incubator.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $organization = Organization::query()->where('name', 'Makerere Innovation Hub')->sole();

        $this->actingAs($admin)->get(route('admin.organizations.index'))->assertOk()->assertSee('Makerere Innovation Hub');
        $this->actingAs($admin)->get(route('admin.organizations.index', ['q' => 'Makerere', 'status' => $organization->status]))->assertOk()->assertSee('Makerere Innovation Hub');
        $this->actingAs($admin)->get(route('admin.organizations.show', $organization))->assertOk()->assertSee('Makerere Innovation Hub');

        // Verify it, invite its first admin, and see both on the page.
        $this->actingAs($admin)->put(route('admin.organizations.update', $organization), ['action' => 'verify'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('active', $organization->fresh()->status);
        $this->actingAs($admin)->post(route('admin.organizations.members', $organization), ['email' => 'lead@hub.example.ug', 'role' => 'org_admin'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.organizations.show', $organization))->assertOk()
            ->assertSee('lead@hub.example.ug')
            ->assertSee('data-tab="Startup access"', false);
    }
}
