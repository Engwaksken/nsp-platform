<?php

namespace App\Models\Experts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Feedback from the startup after an engagement. Comments are seen by the expert and Hive staff only. */
class ExpertReview extends Model
{
    protected $fillable = ['engagement_uuid', 'startup_uuid', 'expert_user_id', 'reviewer_id', 'rating', 'comment', 'helped'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'helped' => 'boolean', 'hidden_at' => 'datetime'];
    }

    /** Reviews staff have not hidden after a dispute. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_uuid');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
