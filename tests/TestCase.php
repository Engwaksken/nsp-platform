<?php

namespace Tests;

use App\Models\User;
use App\Support\Features;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::forget(Settings::CACHE_KEY);
        Features::flush();
        // Most tests act as an admin who already passed two-factor sign-in. MfaTest covers the real flow.
        $this->withSession(['mfa_verified' => true]);
    }

    protected function userWithRole(string ...$roles): User
    {
        return User::factory()->withRole(...$roles)->create();
    }
}
