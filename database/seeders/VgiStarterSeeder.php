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
        // The old six-area draft remains in the repository as historical
        // reference; new work must start from the owner's exact ten dimensions.
        $definition = require database_path('data/vgi_owner_dimensions_draft.php');
        VgiFramework::query()->create([
            'name' => 'Hive Venture Growth Index',
            'version' => 1,
            'status' => 'draft',
            'description' => 'Provisional owner-dimension rubric for Hive review. Questions, weights, bands and readiness rules are not owner-approved; edit and obtain sign-off before presenting methodology as official.',
            'definition' => $definition,
            'definition_hash' => FrameworkDefinition::hash($definition),
        ]);
    }
}
