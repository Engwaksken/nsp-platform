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
        // Owner-alignment seeders (provisional content, clearly labelled). Each is added by its module.
        foreach ([
            'Database\Seeders\BuilderCurriculumSeeder',
            'Database\Seeders\VgiOwnerDimensionsSeeder',
            'Database\Seeders\KnowledgeTaxonomySeeder',
            'Database\Seeders\ExpertCategoriesSeeder',
            'Database\Seeders\IntelligenceRulesSeeder',
        ] as $seeder) {
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }
    }
}
