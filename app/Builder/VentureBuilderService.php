<?php

namespace App\Builder;

use App\Models\Builder\BuilderCurriculum;
use App\Models\Builder\BuilderDocument;
use App\Models\Builder\BuilderDocumentVersion;
use App\Models\Builder\BuilderStepEvent;
use App\Models\Builder\VentureBuilderEnrollment;
use App\Models\Builder\VentureStepEvidence;
use App\Models\Builder\VentureStepReview;
use App\Models\Builder\VentureStepRevision;
use App\Models\Builder\VentureStepSubmission;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Registry\RegistryException;
use Illuminate\Support\Facades\DB;

/** Startup-scoped enrollment and step draft workflow for a pinned curriculum. */
class VentureBuilderService
{
    public function enroll(Startup $startup, User $user): VentureBuilderEnrollment
    {
        $existing = VentureBuilderEnrollment::query()->where('startup_uuid', $startup->uuid)->where('status', 'active')->latest('id')->first();
        if ($existing) {
            return $existing;
        }
        $curriculum = BuilderCurriculum::current();
        if (! $curriculum) {
            throw new RegistryException('The Venture Builder curriculum is not published yet.');
        }

        return DB::transaction(function () use ($startup, $user, $curriculum) {
            $enrollment = VentureBuilderEnrollment::query()->create([
                'startup_uuid' => $startup->uuid,
                'curriculum_id' => $curriculum->id,
                'started_by' => $user->id,
                'started_at' => now(),
                'status' => 'active',
            ]);
            foreach ($curriculum->orderedSteps() as $step) {
                VentureStepSubmission::query()->create([
                    'enrollment_id' => $enrollment->id,
                    'startup_uuid' => $startup->uuid,
                    'step_definition_id' => $step->id,
                    'step_key' => $step->key,
                    'status' => 'not_started',
                    'responses' => [],
                    'checklist_state' => [],
                ]);
            }

            return $enrollment;
        });
    }

    /** Save a draft with optimistic locking; unlocked steps only. */
    public function saveDraft(VentureStepSubmission $submission, User $user, array $responses, array $checklist, int $expectedVersion): VentureStepSubmission
    {
        return DB::transaction(function () use ($submission, $user, $responses, $checklist, $expectedVersion) {
            $locked = VentureStepSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            if (! $locked->isEditable()) {
                throw new RegistryException('This step is not editable in its current state.');
            }
            if ($locked->version !== $expectedVersion) {
                throw new RegistryException('This step changed elsewhere. Reload it before saving your draft.');
            }
            $locked->loadMissing('step');
            if (! $this->isUnlocked($locked)) {
                throw new RegistryException('Complete the earlier step before editing this one.');
            }
            $allowed = collect($locked->step->fields ?? [])->pluck('key')->all();
            $cleanResponses = collect($responses)->only($allowed)->all();
            $checklistKeys = collect($locked->step->checklist ?? [])->pluck('key')->all();
            $cleanChecklist = collect($checklist)->only($checklistKeys)->map(fn ($value) => (bool) $value)->all();

            $locked->forceFill([
                'responses' => $cleanResponses,
                'checklist_state' => $cleanChecklist,
                'status' => 'in_progress',
                'started_at' => $locked->started_at ?? now(),
                'updated_by' => $user->id,
                'version' => $locked->version + 1,
            ])->save();
            BuilderStepEvent::query()->create([
                'submission_id' => $locked->id, 'startup_uuid' => $locked->startup_uuid,
                'action' => 'draft_saved', 'from_status' => $submission->status, 'to_status' => 'in_progress', 'user_id' => $user->id,
            ]);

            return $locked->fresh('step');
        });
    }

    /** @return array{done:int,total:int,percent:int} */
    public function progress(VentureBuilderEnrollment $enrollment): array
    {
        $submissions = $enrollment->submissions()->get();
        $total = $submissions->count();
        $done = $submissions->filter(fn (VentureStepSubmission $submission) => $submission->isDone())->count();

        return ['done' => $done, 'total' => $total, 'percent' => $total ? (int) round($done * 100 / $total) : 0];
    }

    /** Submit a step to unlock its dependants; review-required steps remain pending validation. */
    public function submit(VentureStepSubmission $submission, User $user): VentureStepSubmission
    {
        return DB::transaction(function () use ($submission, $user) {
            $locked = VentureStepSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $locked->loadMissing('step');
            if (! $locked->isEditable() || ! $this->isUnlocked($locked)) {
                throw new RegistryException('This step cannot be submitted in its current state.');
            }
            $responses = $locked->responses ?? [];
            $missing = collect($locked->step->fields ?? [])->filter(fn ($field) => ($field['required'] ?? false)
                && trim((string) ($responses[$field['key']] ?? '')) === '')->pluck('label')->all();
            $unchecked = collect($locked->step->checklist ?? [])->filter(fn ($item) => ! ($locked->checklist_state[$item['key']] ?? false))
                ->pluck('label')->all();
            $evidenceMissing = $locked->step->evidence_requirement === 'required' && ! $locked->evidence()->exists();
            if ($missing || $unchecked || $evidenceMissing) {
                throw new RegistryException('Complete required fields and checklist items before submitting.');
            }
            $from = $locked->status;
            $locked->forceFill([
                'status' => 'submitted', 'submitted_at' => now(), 'first_submitted_at' => $locked->first_submitted_at ?? now(),
                'submitted_by' => $user->id, 'updated_by' => $user->id, 'version' => $locked->version + 1,
            ])->save();
            VentureStepRevision::query()->create([
                'submission_id' => $locked->id, 'startup_uuid' => $locked->startup_uuid,
                'number' => (int) $locked->revisions()->count() + 1, 'reason' => 'submitted',
                'status' => 'submitted', 'step_key' => $locked->step_key, 'step_title' => $locked->step->title,
                'curriculum_version' => $locked->step->curriculum()->value('version'), 'field_labels' => $locked->step->fieldLabels(),
                'responses' => $locked->responses, 'checklist_state' => $locked->checklist_state, 'created_by' => $user->id,
            ]);
            BuilderStepEvent::query()->create([
                'submission_id' => $locked->id, 'startup_uuid' => $locked->startup_uuid,
                'action' => 'submitted', 'from_status' => $from, 'to_status' => 'submitted', 'user_id' => $user->id,
            ]);

            return $locked->fresh('step');
        });
    }

    public function isUnlocked(VentureStepSubmission $submission): bool
    {
        $submission->loadMissing('step', 'enrollment.curriculum');
        $step = $submission->step;
        if (is_array($step->prerequisites) && $step->prerequisites !== []) {
            $keys = $step->prerequisites;
        } else {
            $ordered = $submission->enrollment->curriculum->orderedSteps();
            $position = $ordered->search(fn ($item) => $item->id === $step->id);
            $keys = $position > 0 ? [$ordered[$position - 1]->key] : [];
        }
        if ($keys === []) {
            return true;
        }

        return $submission->enrollment->submissions()->whereIn('step_key', $keys)
            ->get()->every(fn (VentureStepSubmission $prior) => $prior->unlocksDependents());
    }

    public function review(VentureStepSubmission $submission, User $reviewer, string $decision, string $feedback): VentureStepSubmission
    {
        if (! in_array($decision, ['changes_requested', 'validated'], true) || mb_strlen(trim($feedback)) < 10) {
            throw new RegistryException('Choose a review decision and provide meaningful feedback.');
        }

        return DB::transaction(function () use ($submission, $reviewer, $decision, $feedback) {
            $locked = VentureStepSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            if ($locked->status !== 'submitted') {
                throw new RegistryException('Only submitted steps can be reviewed.');
            }
            $from = $locked->status;
            $locked->forceFill([
                'status' => $decision,
                'validated_at' => $decision === 'validated' ? now() : null,
                'validated_by' => $decision === 'validated' ? $reviewer->id : null,
                'version' => $locked->version + 1,
            ])->save();
            $review = VentureStepReview::query()->create([
                'submission_id' => $locked->id, 'startup_uuid' => $locked->startup_uuid,
                'reviewer_id' => $reviewer->id, 'decision' => $decision,
                'feedback' => mb_substr(trim($feedback), 0, 5000), 'revision_number' => $locked->revisions()->count(),
            ]);
            $locked->loadMissing('step');
            if ($decision === 'validated') {
                VentureStepRevision::query()->create([
                    'submission_id' => $locked->id, 'startup_uuid' => $locked->startup_uuid,
                    'number' => (int) $locked->revisions()->count() + 1, 'reason' => 'validated',
                    'status' => 'validated', 'step_key' => $locked->step_key, 'step_title' => $locked->step->title,
                    'curriculum_version' => $locked->step->curriculum()->value('version'), 'field_labels' => $locked->step->fieldLabels(),
                    'responses' => $locked->responses, 'checklist_state' => $locked->checklist_state, 'created_by' => $reviewer->id,
                ]);
            }
            BuilderStepEvent::query()->create([
                'submission_id' => $locked->id, 'startup_uuid' => $locked->startup_uuid,
                'action' => 'reviewed', 'from_status' => $from, 'to_status' => $decision, 'user_id' => $reviewer->id,
                'note' => mb_substr(trim($feedback), 0, 500),
            ]);

            return $locked->fresh(['step', 'reviews.reviewer', 'revisions']);
        });
    }

    public function attachLink(VentureStepSubmission $submission, User $user, string $title, string $url, ?string $sourceNote): VentureStepEvidence
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new RegistryException('Evidence links must use HTTP or HTTPS.');
        }

        return VentureStepEvidence::query()->create([
            'submission_id' => $submission->id, 'startup_uuid' => $submission->startup_uuid,
            'kind' => 'link', 'title' => mb_substr(trim($title), 0, 200), 'url' => $url,
            'source_note' => $sourceNote, 'uploaded_by' => $user->id,
        ]);
    }

    public function generateDocument(VentureStepSubmission $submission, User $user): BuilderDocumentVersion
    {
        $submission->loadMissing('step.module');
        $template = $submission->step->output_template;
        if (! is_string($template) || trim($template) === '') {
            throw new RegistryException('This step does not have an output template.');
        }
        $startupName = Startup::query()->where('uuid', $submission->startup_uuid)->value('display_name') ?? 'Startup';
        $values = array_merge([
            'startup_name' => $startupName,
            'module_title' => $submission->step->module->title,
            'step_title' => $submission->step->title,
            'date' => now()->toDateString(),
        ], $submission->responses ?? []);
        $body = preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/', fn ($match) => (string) ($values[$match[1]] ?? ''), $template);

        return DB::transaction(function () use ($submission, $user, $body) {
            $document = BuilderDocument::query()->firstOrCreate(
                ['submission_id' => $submission->id],
                ['startup_uuid' => $submission->startup_uuid, 'title' => $submission->step->title.' output', 'current_version' => 0],
            );

            return $this->appendDocumentVersion($document, $user, $body, 'generated');
        });
    }

    public function saveDocument(BuilderDocument $document, User $user, string $body, int $expectedVersion): BuilderDocumentVersion
    {
        if (mb_strlen($body) > 100000) {
            throw new RegistryException('The document is too long to save.');
        }

        return DB::transaction(function () use ($document, $user, $body, $expectedVersion) {
            $locked = BuilderDocument::query()->lockForUpdate()->findOrFail($document->id);
            if ($locked->current_version !== $expectedVersion) {
                throw new RegistryException('This document changed elsewhere. Reload it before saving.');
            }

            return $this->appendDocumentVersion($locked, $user, $body, 'edited');
        });
    }

    private function appendDocumentVersion(BuilderDocument $document, User $user, string $body, string $source): BuilderDocumentVersion
    {
        $number = $document->current_version + 1;
        $version = $document->versions()->create(['number' => $number, 'source' => $source, 'body' => $body, 'created_by' => $user->id]);
        $document->forceFill(['current_version' => $number])->save();

        return $version;
    }
}
