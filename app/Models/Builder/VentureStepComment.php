<?php

namespace App\Models\Builder;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentureStepComment extends Model
{
    protected $fillable = ['submission_id', 'startup_uuid', 'user_id', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
