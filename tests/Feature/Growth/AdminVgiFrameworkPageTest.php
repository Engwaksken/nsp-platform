<?php

namespace Tests\Feature\Growth;

use App\Models\Growth\VgiFramework;
use Database\Seeders\VgiStarterSeeder;
use Tests\TestCase;

/** The admin growth framework editor opens for the seeded framework and for a new draft. */
class AdminVgiFrameworkPageTest extends TestCase
{
    public function test_admin_can_open_the_framework_editor(): void
    {
        $this->seed(VgiStarterSeeder::class);
        $admin = $this->userWithRole('platform_admin');
        $framework = VgiFramework::query()->firstOrFail();

        $this->actingAs($admin)->get(route('admin.vgi.edit', $framework))->assertOk();

        $this->actingAs($admin)->post(route('admin.vgi.create'))->assertRedirect();
        $draft = VgiFramework::query()->latest('id')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.vgi.edit', $draft))->assertOk();
    }
}
