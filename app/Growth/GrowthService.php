<?php

namespace App\Growth;

use App\Models\Growth\VgiAssessment;
use App\Models\Growth\VgiFramework;
use App\Models\Growth\VgiResponse;
use App\Models\Registry\OutboxEvent;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/** Assessments (founder side) and rubric versions (staff side). */
class GrowthService
{
    public function start(Startup $startup, User $user): VgiAssessment
    {
        $framework = VgiFramework::current();
        if (! $framework) {
            throw new RegistryException('Hive has not published the growth assessment yet.');
        }
        // One draft at a time: continue it instead of starting another.
        $draft = VgiAssessment::query()->where('startup_uuid', $startup->uuid)->where('status', 'draft')->first();
        if ($draft) {
            return $draft;
        }

        return VgiAssessment::query()->create([
            'startup_uuid' => $startup->uuid, 'framework_id' => $framework->id, 'status' => 'draft', 'started_by' => $user->id,
        ]);
    }

    /**
     * Save answers to a draft. Unknown questions or options are ignored.
     *
     * @param  array<string, string>  $answers
     * @param  array<string, string>  $evidence
     */
    public function saveAnswers(VgiAssessment $assessment, User $user, array $answers, array $evidence): void
    {
        if ($assessment->status !== 'draft') {
            throw new RegistryException('This assessment is already submitted. Start a new one to update your results.');
        }
        $questions = FrameworkDefinition::questions($assessment->framework->definition);
        DB::transaction(function () use ($assessment, $user, $answers, $evidence, $questions) {
            foreach ($questions as $key => $question) {
                $option = $answers[$key] ?? null;
                $validOption = $option !== null && in_array($option, array_column($question['options'], 'key'), true);
                $note = mb_substr(trim((string) ($evidence[$key] ?? '')), 0, 2000);
                if (! $validOption && $note === '') {
                    continue;
                }
                VgiResponse::query()->updateOrCreate(
                    ['assessment_uuid' => $assessment->uuid, 'question_key' => $key],
                    ['option_key' => $validOption ? $option : null, 'evidence_note' => $note !== '' ? $note : null, 'answered_by' => $user->id],
                );
            }
        });
    }

    /** @return list<string> what is still missing before submitting */
    public function missing(VgiAssessment $assessment): array
    {
        $responses = $assessment->responses()->get()->keyBy('question_key');
        $missing = [];
        foreach (FrameworkDefinition::questions($assessment->framework->definition) as $key => $question) {
            $response = $responses->get($key);
            if (! $response || ! $response->option_key) {
                $missing[] = 'Answer: '.$question['prompt'];
            } elseif (($question['evidence'] ?? 'none') === 'required' && ! $response->evidence_note) {
                $missing[] = 'Add evidence for: '.$question['prompt'];
            }
        }

        return $missing;
    }

    public function submit(VgiAssessment $assessment, User $user): VgiAssessment
    {
        $assessment->load('framework', 'responses');
        if ($assessment->status !== 'draft') {
            throw new RegistryException('This assessment is already submitted.');
        }
        if ($missing = $this->missing($assessment)) {
            throw new RegistryException('Please finish the assessment first ('.count($missing).' left).');
        }
        $result = Scorer::score($assessment->framework->definition, $assessment->answers());

        return DB::transaction(function () use ($assessment, $user, $result) {
            $previous = VgiAssessment::query()->where('startup_uuid', $assessment->startup_uuid)->where('status', 'submitted')
                ->latest('submitted_at')->first();
            $assessment->forceFill([
                'status' => 'submitted', 'submitted_by' => $user->id, 'submitted_at' => now(),
                'score' => $result['score'], 'band_label' => $result['band']['label'],
                'result' => ['dimensions' => $result['dimensions'], 'gaps' => $result['gaps'], 'band' => $result['band']],
                'calculation_hash' => $result['hash'],
            ])->save();

            $framework = $assessment->framework;
            OutboxEvent::record('nsp.assessment.completed.v1', $assessment->startup_uuid, [
                'assessment_uuid' => $assessment->uuid, 'framework' => $framework->name, 'framework_version' => $framework->version,
                'band' => $result['band']['label'],
            ]);
            if (! $previous || $previous->displayBand() !== $result['band']['label']) {
                OutboxEvent::record('nsp.growth.stage.changed.v1', $assessment->startup_uuid, [
                    'from' => $previous?->displayBand(), 'to' => $result['band']['label'],
                ]);
            }
            Audit::log('vgi.assessment_submitted', $assessment, ['score' => $result['score'], 'band' => $result['band']['label']]);

            return $assessment;
        });
    }

    /** Recalculate from the stored answers and rubric; used to prove results are reproducible. */
    public function verify(VgiAssessment $assessment): bool
    {
        $result = Scorer::score($assessment->framework->definition, $assessment->answers());

        return $result['hash'] === $assessment->calculation_hash && abs($result['score'] - (float) $assessment->score) < 0.05;
    }

    public function override(VgiAssessment $assessment, User $staff, ?float $score, ?string $band, string $reason): void
    {
        if ($assessment->status !== 'submitted') {
            throw new RegistryException('Only submitted assessments can be adjusted.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new RegistryException('Explain the adjustment in at least a sentence.');
        }
        $before = ['score' => $assessment->override_score, 'band' => $assessment->override_band_label];
        $assessment->forceFill([
            'override_score' => $score, 'override_band_label' => $band ?: null,
            'override_reason' => mb_substr(trim($reason), 0, 2000), 'override_by' => $staff->id, 'override_at' => now(),
        ])->save();
        Audit::log('vgi.assessment_overridden', $assessment, [
            'calculated' => ['score' => $assessment->score, 'band' => $assessment->band_label],
            'before' => $before, 'after' => ['score' => $score, 'band' => $band], 'reason' => $reason,
        ]);
    }

    public function createDraft(User $user, ?VgiFramework $from = null): VgiFramework
    {
        $base = $from ?? VgiFramework::current();
        $definition = $base?->definition ?? require database_path('data/vgi_starter_draft.php');
        $name = $base?->name ?? 'Hive Venture Growth Index';
        $version = (int) VgiFramework::query()->where('name', $name)->max('version') + 1;

        $framework = new VgiFramework([
            'name' => $name, 'version' => $version, 'status' => 'draft',
            'description' => $base?->description ?? 'Starter rubric proposed for Hive review. Edit before approving.',
            'definition' => $definition, 'definition_hash' => FrameworkDefinition::hash($definition),
        ]);
        $framework->forceFill(['created_by' => $user->id, 'updated_by' => $user->id])->save();
        Audit::log('vgi.framework_draft_created', $framework, ['version' => $version]);

        return $framework;
    }

    public function updateDraft(VgiFramework $framework, User $user, array $definition, ?string $description): void
    {
        if ($framework->status !== 'draft') {
            throw new RegistryException('Approved versions are frozen. Create a new draft version to make changes.');
        }
        if ($errors = FrameworkDefinition::validate($definition)) {
            throw new RegistryException(implode(' ', array_slice($errors, 0, 5)));
        }
        $framework->forceFill([
            'definition' => $definition, 'definition_hash' => FrameworkDefinition::hash($definition),
            'description' => $description, 'updated_by' => $user->id,
        ])->save();
        Audit::log('vgi.framework_draft_updated', $framework, ['hash' => $framework->definition_hash]);
    }

    /** Four-eyes rule: the last editor cannot approve their own changes (super admins may, with a note). */
    public function approve(VgiFramework $framework, User $approver, string $note): void
    {
        if ($framework->status !== 'draft') {
            throw new RegistryException('Only drafts can be approved.');
        }
        if ($errors = FrameworkDefinition::validate($framework->definition)) {
            throw new RegistryException('Fix the rubric first: '.implode(' ', array_slice($errors, 0, 3)));
        }
        if ($framework->updated_by === $approver->id && ! $approver->hasRole('super_admin')) {
            throw new RegistryException('Someone other than the last editor must approve this version.');
        }
        if (mb_strlen(trim($note)) < 10) {
            throw new RegistryException('Write a short approval note (what was reviewed and by whom).');
        }
        DB::transaction(function () use ($framework, $approver, $note) {
            VgiFramework::query()->where('status', 'approved')->update(['status' => 'retired']);
            $framework->forceFill(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now(),
                'approval_note' => mb_substr(trim($note), 0, 2000)])->save();
            Audit::log('vgi.framework_approved', $framework, ['version' => $framework->version, 'hash' => $framework->definition_hash]);
        });
    }
}
