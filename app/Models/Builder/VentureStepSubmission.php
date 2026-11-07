<?php

namespace App\Models\Builder;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A startup's work on one step. `version` guards against stale (concurrent) saves. */
class VentureStepSubmission extends Model
{
    public const STATUSES = [
        'not_started' => 'Not started',
        'in_progress' => 'In progress',
        'submitted' => 'Submitted for review',
        'changes_requested' => 'Changes requested',
        'validated' => 'Validated',
        'completed' => 'Completed',
    ];

    /** Founders may edit responses in these states. */
    public const EDITABLE = ['not_started', 'in_progress', 'changes_requested'];

    /** Counted as done for progress. */
    public const DONE = ['validated', 'completed'];

    /** Satisfies a prerequisite of a later step ("submitted or better"). */
    public const UNLOCKING = ['submitted', 'changes_requested', 'validated', 'completed'];

    protected $fillable = ['enrollment_id', 'startup_uuid', 'step_definition_id', 'step_key', 'status', 'responses', 'checklist_state', 'version'];

    protected function casts(): array
    {
        return [
            'responses' => 'array', 'checklist_state' => 'array', 'version' => 'integer',
            'first_submitted_at' => 'datetime', 'started_at' => 'datetime', 'submitted_at' => 'datetime',
            'validated_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    public function isDone(): bool
    {
        return in_array($this->status, self::DONE, true);
    }

    /** Once a step has been submitted (or completed) its dependants stay unlocked, even while it is being revised. */
    public function unlocksDependents(): bool
    {
        return $this->first_submitted_at !== null || in_array($this->status, self::UNLOCKING, true);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(VentureBuilderEnrollment::class, 'enrollment_id');
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(BuilderStepDefinition::class, 'step_definition_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(VentureStepRevision::class, 'submission_id')->orderByDesc('number');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(VentureStepEvidence::class, 'submission_id')->latest('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VentureStepReview::class, 'submission_id')->latest('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(VentureStepComment::class, 'submission_id')->oldest('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BuilderStepEvent::class, 'submission_id')->latest('id');
    }

    public function document(): HasOne
    {
        return $this->hasOne(BuilderDocument::class, 'submission_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
