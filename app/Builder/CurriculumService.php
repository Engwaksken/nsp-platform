<?php

namespace App\Builder;

use App\Models\Builder\BuilderCurriculum;
use App\Models\Builder\BuilderModule;
use App\Models\Builder\BuilderStepDefinition;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/** Draft-only curriculum editing and invariant-checked publication. */
class CurriculumService
{
    public function createDraft(User $creator): BuilderCurriculum
    {
        $base = BuilderCurriculum::current();
        $name = $base?->name ?? 'Hive Venture Builder';
        if (BuilderCurriculum::query()->where('name', $name)->where('status', 'draft')->exists()) {
            throw new RegistryException('A draft curriculum already exists. Edit that draft before creating another.');
        }
        $definition = $base
            ? CurriculumDefinition::fromCurriculum($base)
            : require database_path('data/builder_provisional_curriculum.php');

        return DB::transaction(function () use ($creator, $base, $name, $definition) {
            $curriculum = BuilderCurriculum::query()->create([
                'name' => $name,
                'version' => (int) BuilderCurriculum::query()->where('name', $name)->max('version') + 1,
                'status' => 'draft', 'methodology_status' => 'provisional',
                'description' => $base?->description ?? 'Provisional curriculum draft awaiting owner approval.',
                'copied_from_id' => $base?->id, 'created_by' => $creator->id, 'updated_by' => $creator->id,
            ]);
            $this->replaceDefinition($curriculum, $definition);
            Audit::log('builder.curriculum_draft_created', $curriculum, ['version' => $curriculum->version, 'copied_from_id' => $base?->id]);

            return $curriculum;
        });
    }

    public function seedProvisional(): ?BuilderCurriculum
    {
        if (BuilderCurriculum::query()->exists()) {
            return null;
        }

        $definition = require database_path('data/builder_provisional_curriculum.php');

        return DB::transaction(function () use ($definition) {
            $curriculum = BuilderCurriculum::query()->create([
                'name' => 'Hive Venture Builder', 'version' => 1, 'status' => 'draft',
                'methodology_status' => 'provisional',
                'description' => 'Provisional implementation scaffold awaiting owner approval of official module and step names.',
                'note' => 'Not owner-approved. Do not present this curriculum as official methodology.',
            ]);
            $this->replaceDefinition($curriculum, $definition);

            return $curriculum;
        });
    }

    public function updateDraft(BuilderCurriculum $curriculum, User $editor, array $definition): void
    {
        if (! $curriculum->isDraft()) {
            throw new RegistryException('Published curriculum versions are frozen. Create a new draft version to edit.');
        }
        if ($errors = CurriculumDefinition::structureProblems($definition)) {
            throw new RegistryException(implode(' ', array_slice($errors, 0, 5)));
        }

        DB::transaction(function () use ($curriculum, $editor, $definition) {
            $locked = BuilderCurriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            if (! $locked->isDraft()) {
                throw new RegistryException('This curriculum is no longer a draft.');
            }
            $this->replaceDefinition($locked, $definition);
            $locked->forceFill(['updated_by' => $editor->id])->save();
            Audit::log('builder.curriculum_draft_updated', $locked, ['modules' => count($definition['modules'])]);
        });
    }

    public function publish(BuilderCurriculum $curriculum, User $publisher, string $note): void
    {
        if (! $curriculum->isDraft()) {
            throw new RegistryException('Only a draft curriculum can be published.');
        }
        if (mb_strlen(trim($note)) < 10) {
            throw new RegistryException('Write a publication note explaining what was checked.');
        }
        DB::transaction(function () use ($curriculum, $publisher, $note) {
            $locked = BuilderCurriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            if (! $locked->isDraft()) {
                throw new RegistryException('This curriculum is no longer a draft.');
            }
            $definition = CurriculumDefinition::fromCurriculum($locked);
            if ($errors = CurriculumDefinition::publishProblems($definition)) {
                throw new RegistryException(implode(' ', array_slice($errors, 0, 5)));
            }
            $activeModuleCount = count(array_filter($definition['modules'], fn ($module) => $module['active'] ?? true));
            BuilderCurriculum::query()->where('status', 'published')->update(['status' => 'retired']);
            $locked->forceFill([
                'status' => 'published', 'methodology_status' => 'provisional',
                'published_by' => $publisher->id, 'published_at' => now(),
                'note' => mb_substr(trim($note), 0, 5000),
            ])->save();
            Audit::log('builder.curriculum_published', $locked, [
                'version' => $locked->version, 'methodology_status' => $locked->methodology_status,
                'active_modules' => $activeModuleCount,
                'active_steps' => $locked->orderedSteps()->count(),
            ]);
        });
    }

    private function replaceDefinition(BuilderCurriculum $curriculum, array $definition): void
    {
        // This only runs on a draft, which cannot have founder enrollments. Published versions are immutable.
        $curriculum->modules()->delete();
        foreach (array_values($definition['modules']) as $modulePosition => $moduleData) {
            $module = BuilderModule::query()->create([
                'curriculum_id' => $curriculum->id, 'position' => $modulePosition + 1,
                'key' => $moduleData['key'], 'title' => $moduleData['title'],
                'description' => $moduleData['description'] ?? null, 'outputs' => $moduleData['outputs'] ?? [],
                'is_active' => $moduleData['active'] ?? true,
            ]);
            foreach (array_values($moduleData['steps'] ?? []) as $stepPosition => $stepData) {
                BuilderStepDefinition::query()->create([
                    'curriculum_id' => $curriculum->id, 'module_id' => $module->id, 'position' => $stepPosition + 1,
                    'key' => $stepData['key'], 'title' => $stepData['title'], 'guidance' => $stepData['guidance'] ?? null,
                    'fields' => $stepData['fields'], 'checklist' => $stepData['checklist'] ?? [],
                    'evidence_requirement' => $stepData['evidence_requirement'] ?? 'none',
                    'requires_review' => $stepData['requires_review'] ?? false, 'output_template' => $stepData['output_template'] ?? null,
                    'vgi_dimension_keys' => $stepData['vgi_dimension_keys'] ?? [], 'prerequisites' => $stepData['prerequisites'] ?? null,
                    'task_suggestions' => $stepData['task_suggestions'] ?? [], 'is_active' => $stepData['active'] ?? true,
                ]);
            }
        }
    }
}
