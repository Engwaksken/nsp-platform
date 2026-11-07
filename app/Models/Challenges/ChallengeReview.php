<?php

namespace App\Models\Challenges;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeReview extends Model
{
    public const DECISIONS = [
        'approve' => 'Approve',
        'request_changes' => 'Request changes',
        'reject' => 'Reject',
        'comment' => 'Comment only',
    ];

    protected $fillable = ['challenge_id', 'challenge_version_id', 'reviewer_id', 'decision', 'reason', 'comments', 'source_validation_notes'];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ChallengeVersion::class, 'challenge_version_id');
    }

    /** Decisions whose comments are shared with the submitter (comments and source notes stay internal). */
    public function isSharedWithSubmitter(): bool
    {
        return in_array($this->decision, ['reject', 'request_changes', 'approve'], true);
    }
}
