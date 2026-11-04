<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Creates roles, permissions and placeholder content. It never creates users
 * or passwords: run `php artisan nsp:create-admin` for the first admin.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            StarterContentSeeder::class,
            VgiStarterSeeder::class,
        ]);
    }
}
