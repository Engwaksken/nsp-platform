<?php

namespace App\Models\Builder;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Status history of a step submission (who changed what, when). */
class BuilderStepEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['submission_id', 'startup_uuid', 'action', 'from_status', 'to_status', 'user_id', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
