<?php

namespace App\Models\Challenges;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A source supporting a challenge: a link or a private file. Submitting evidence never implies it was validated. */
class ChallengeEvidence extends Model
{
    protected $table = 'challenge_evidence';

    protected $fillable = ['challenge_id', 'source_title', 'url', 'disk', 'path', 'original_name', 'mime_type', 'size', 'evidence_type', 'added_by'];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return ['validated' => 'boolean', 'validated_at' => 'datetime', 'size' => 'integer'];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function hasFile(): bool
    {
        return $this->path !== null;
    }
}
