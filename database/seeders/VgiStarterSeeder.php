<?php

namespace Database\Seeders;

use App\Growth\FrameworkDefinition;
use App\Models\Growth\VgiFramework;
use Illuminate\Database\Seeder;

/**
 * Adds the starter rubric as an unapproved DRAFT (only if no rubric exists).
 * Founders see nothing until a Hive administrator reviews and approves it.
 */
class VgiStarterSeeder extends Seeder
{
    public function run(): void
    {
        if (VgiFramework::query()->exists()) {
            return;
        }
        $definition = require database_path('data/vgi_starter_draft.php');
        VgiFramework::query()->create([
            'name' => 'Hive Venture Growth Index',
            'version' => 1,
            'status' => 'draft',
            'description' => 'Starter rubric proposed for Hive review. Edit the questions, weights and bands, then have another administrator approve it.',
            'definition' => $definition,
            'definition_hash' => FrameworkDefinition::hash($definition),
        ]);
    }
}
