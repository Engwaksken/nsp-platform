<?php

namespace App\Models\Challenges;

use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A proposed solution / expression of interest. Private to the submitter, their startup team and challenge reviewers. */
class ChallengeSolutionSubmission extends Model
{
    public const STATUSES = [
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'shortlisted' => 'Shortlisted',
        'declined' => 'Declined',
    ];

    protected $fillable = ['challenge_id', 'user_id', 'startup_uuid', 'summary', 'approach', 'status'];

    protected function casts(): array
    {
        return ['status_changed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (ChallengeSolutionSubmission $s) => $s->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid', 'uuid');
    }

    public function isViewableBy(User $user): bool
    {
        if ($user->id === $this->user_id || $user->can('review-challenges')) {
            return true;
        }

        return $this->startup_uuid !== null && StartupMembership::query()
            ->where('startup_uuid', $this->startup_uuid)->where('user_id', $user->id)->where('status', 'active')->exists();
    }
}
