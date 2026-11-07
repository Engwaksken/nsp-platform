<?php

namespace Tests\Unit;

use App\Builder\CurriculumDefinition;
use Tests\TestCase;

class CurriculumDefinitionTest extends TestCase
{
    public function test_provisional_curriculum_has_six_modules_and_twenty_four_steps(): void
    {
        $definition = require database_path('data/builder_provisional_curriculum.php');

        $this->assertSame([], CurriculumDefinition::publishProblems($definition));
        $this->assertCount(6, array_filter($definition['modules'], fn ($module) => $module['active'] ?? true));
        $this->assertSame(24, array_sum(array_map(
            fn ($module) => count(array_filter($module['steps'], fn ($step) => $step['active'] ?? true)),
            array_filter($definition['modules'], fn ($module) => $module['active'] ?? true),
        )));
        $this->assertStringContainsString('PROVISIONAL', file_get_contents(database_path('data/builder_provisional_curriculum.php')));
    }

    public function test_a_curriculum_with_wrong_active_counts_cannot_be_published(): void
    {
        $definition = require database_path('data/builder_provisional_curriculum.php');
        $definition['modules'][0]['steps'][0]['active'] = false;

        $problems = CurriculumDefinition::publishProblems($definition);

        $this->assertNotSame([], $problems);
        $this->assertStringContainsString('exactly 24 active steps', implode(' ', $problems));
    }
}
