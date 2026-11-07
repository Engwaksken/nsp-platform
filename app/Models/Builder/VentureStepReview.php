<?php

namespace App\Models\Builder;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentureStepReview extends Model
{
    public const UPDATED_AT = null;

    public const DECISIONS = ['changes_requested' => 'Changes requested', 'validated' => 'Validated'];

    protected $fillable = ['submission_id', 'startup_uuid', 'reviewer_id', 'decision', 'feedback', 'revision_number'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
