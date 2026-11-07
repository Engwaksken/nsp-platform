<?php

namespace App\Models\Experts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Terms for one engagement at one terms version, with each side's recorded acceptance
 * (who, when, and the hash of the exact text). This is not a legal e-signature.
 */
class ExpertContract extends Model
{
    protected $fillable = ['engagement_uuid', 'booking_uuid', 'terms_version', 'terms_hash', 'terms_text'];

    protected function casts(): array
    {
        return ['expert_accepted_at' => 'datetime', 'startup_accepted_at' => 'datetime'];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_uuid');
    }

    public function expertAcceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expert_accepted_by');
    }

    public function startupAcceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'startup_accepted_by');
    }

    public function isFullyAccepted(): bool
    {
        return $this->expert_accepted_at !== null && $this->startup_accepted_at !== null;
    }
}
