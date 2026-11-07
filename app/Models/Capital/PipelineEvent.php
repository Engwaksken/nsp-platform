<?php

namespace App\Models\Capital;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only: a pipeline transition can be recorded, never changed or removed. */
class PipelineEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'investment_pipeline_events';

    protected $fillable = ['pipeline_item_id', 'investor_user_id', 'startup_uuid', 'from_stage', 'to_stage', 'actor_user_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Pipeline events are append-only.'));
        static::deleting(fn () => throw new LogicException('Pipeline events are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
