<?php

namespace App\Models\Challenges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeBookmark extends Model
{
    protected $fillable = ['challenge_id', 'user_id'];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
