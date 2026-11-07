<?php

namespace App\Models\Builder;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Frozen snapshot of a step's responses (with the labels used at the time). Never updated. */
class VentureStepRevision extends Model
{
    public const UPDATED_AT = null;

    public const REASONS = ['submitted' => 'Submitted', 'validated' => 'Validated', 'completed' => 'Completed',
        'revised' => 'Reopened for revision', 'migrated' => 'Moved to a new curriculum version'];

    protected $fillable = ['submission_id', 'startup_uuid', 'number', 'reason', 'status', 'step_key', 'step_title',
        'curriculum_version', 'field_labels', 'responses', 'checklist_state', 'created_by'];

    protected function casts(): array
    {
        return ['field_labels' => 'array', 'responses' => 'array', 'checklist_state' => 'array', 'created_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
