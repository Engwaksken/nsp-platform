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
    }

    protected function userWithRole(string ...$roles): User
    {
        return User::factory()->withRole(...$roles)->create();
    }
}
