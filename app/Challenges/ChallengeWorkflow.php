<?php

namespace App\Challenges;

use App\Models\Challenges\Challenge;
use App\Models\Challenges\ChallengeEvidence;
use App\Models\Challenges\ChallengeReview;
use App\Models\Challenges\ChallengeVersion;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moderation workflow: draft → submitted → under_review → approved / rejected / changes_requested → published → archived.
 * Every transition and review decision is written to the audit log.
 */
class ChallengeWorkflow
{
    /** Save the working copy. Editing a published (or approved/rejected) challenge starts a new draft revision; the public keeps the published snapshot. */
    public function saveContent(Challenge $challenge, array $data, User $actor): Challenge
    {
        if ($challenge->exists && ! $challenge->isEditable()) {
            throw ValidationException::withMessages(['status' => 'This challenge is with reviewers and cannot be edited until they respond.']);
        }
        $creating = ! $challenge->exists;
        $previous = $challenge->status;
        $challenge->fill($data);
        if ($creating) {
            $challenge->status = 'draft';
            $challenge->submitted_by ??= $actor->id;
        } elseif (in_array($previous, ['published', 'approved', 'rejected'], true)) {
            $challenge->status = 'draft';
        }
        $challenge->save();

        if ($creating) {
            Audit::log('challenge.created', $challenge);
        } elseif ($previous === 'published') {
            Audit::log('challenge.revision_started', $challenge, ['published_version' => $challenge->publishedVersion?->version]);
        } else {
            Audit::log('challenge.updated', $challenge, ['status' => $challenge->status]);
        }

        return $challenge;
    }

    /** Submit for review: writes an immutable version snapshot. Submission never implies validation. */
    public function submit(Challenge $challenge, User $actor): ChallengeVersion
    {
        if (! in_array($challenge->status, ['draft', 'changes_requested', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Only drafts can be submitted for review.']);
        }

        return DB::transaction(function () use ($challenge, $actor) {
            $version = $this->snapshot($challenge, $actor);
            $challenge->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();
            Audit::log('challenge.submitted', $challenge, ['version' => $version->version]);

            return $version;
        });
    }

    /** Reviewer decision: approve, reject (reason required), request_changes or comment. */
    public function review(Challenge $challenge, User $reviewer, string $decision, ?string $comments, ?string $reason = null, ?string $sourceNotes = null): ChallengeReview
    {
        if (! array_key_exists($decision, ChallengeReview::DECISIONS)) {
            throw ValidationException::withMessages(['decision' => 'Choose a decision.']);
        }
        if ($decision !== 'comment' && ! in_array($challenge->status, ['submitted', 'under_review'], true)) {
            throw ValidationException::withMessages(['decision' => 'Only submitted challenges can be approved, rejected or sent back.']);
        }
        if (in_array($decision, ['reject', 'request_changes'], true) && blank($comments)) {
            throw ValidationException::withMessages(['comments' => 'Explain to the submitter what is missing.']);
        }
        if ($decision === 'reject' && ! array_key_exists((string) $reason, config('challenges.rejection_reasons'))) {
            throw ValidationException::withMessages(['reason' => 'Choose a reason for rejecting.']);
        }

        return DB::transaction(function () use ($challenge, $reviewer, $decision, $comments, $reason, $sourceNotes) {
            $version = $challenge->versions()->first();
            $review = ChallengeReview::query()->create([
                'challenge_id' => $challenge->id, 'challenge_version_id' => $version?->id, 'reviewer_id' => $reviewer->id,
                'decision' => $decision, 'reason' => $decision === 'reject' ? $reason : null,
                'comments' => $comments, 'source_validation_notes' => $sourceNotes,
            ]);
            $status = match ($decision) {
                'approve' => 'approved',
                'reject' => 'rejected',
                'request_changes' => 'changes_requested',
                default => $challenge->status === 'submitted' ? 'under_review' : $challenge->status,
            };
            $challenge->forceFill(['status' => $status, 'reviewed_at' => now(), 'last_reviewed_at' => now()])->save();
            if ($decision === 'approve' && $version) {
                $version->forceFill(['approved_by' => $reviewer->id, 'approved_at' => now()])->save();
            }
            Audit::log('challenge.review.'.$decision, $challenge, array_filter([
                'version' => $version?->version, 'status' => $status, 'reason' => $review->reason,
            ], fn ($v) => $v !== null));

            return $review;
        });
    }

    /** Publish the approved version. The previous published snapshot stays in history. */
    public function publish(Challenge $challenge, User $reviewer): ChallengeVersion
    {
        $version = $challenge->versions()->first();
        if ($challenge->status !== 'approved' || ! $version || ! $version->approved_at) {
            throw ValidationException::withMessages(['status' => 'Approve the latest version before publishing it.']);
        }

        return DB::transaction(function () use ($challenge, $reviewer, $version) {
            $version->forceFill(['published_by' => $reviewer->id, 'published_at' => now()])->save();
            $challenge->forceFill([
                'status' => 'published', 'published_version_id' => $version->id, 'published_at' => now(),
                'archived_at' => null, 'last_reviewed_at' => now(),
            ])->save();
            Audit::log('challenge.published', $challenge, ['version' => $version->version]);

            return $version;
        });
    }

    public function archive(Challenge $challenge, User $actor): void
    {
        $challenge->forceFill(['status' => 'archived', 'archived_at' => now()])->save();
        Audit::log('challenge.archived', $challenge);
    }

    public function validateEvidence(ChallengeEvidence $evidence, User $reviewer, bool $validated, ?string $note): void
    {
        $evidence->forceFill([
            'validated' => $validated,
            'validated_by' => $validated ? $reviewer->id : null,
            'validated_at' => $validated ? now() : null,
            'validation_note' => $note,
        ])->save();
        $evidence->challenge->forceFill(['last_reviewed_at' => now()])->save();
        Audit::log($validated ? 'challenge.evidence_validated' : 'challenge.evidence_unvalidated', $evidence->challenge, ['evidence_id' => $evidence->id]);
    }

    private function snapshot(Challenge $challenge, User $actor): ChallengeVersion
    {
        $number = (int) $challenge->versions()->max('version') + 1;
        $content = $challenge->contentSnapshot();
        $version = ChallengeVersion::query()->create([
            'challenge_id' => $challenge->id, 'version' => $number,
            'title' => $challenge->title, 'problem_description' => $challenge->problem_description,
            'sector' => $challenge->sector, 'country' => $challenge->country, 'region' => $challenge->region,
            'priority' => $challenge->priority, 'category' => $challenge->category,
            'issuer_name' => $challenge->issuer_name, 'issuer_type' => $challenge->issuer_type,
            'visibility' => $challenge->visibility ?: 'public',
            'tags_text' => implode(' ', (array) ($challenge->tags ?? [])) ?: null,
            'content' => $content, 'created_by' => $actor->id,
        ]);
        $challenge->forceFill(['current_version' => $number])->save();

        return $version;
    }
}
