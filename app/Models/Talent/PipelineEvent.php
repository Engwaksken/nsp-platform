<?php

namespace App\Models\Talent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One status change of an application. The message, if any, is shown to the candidate. */
class PipelineEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'hiring_pipeline_events';

    protected $fillable = ['application_uuid', 'from_status', 'to_status', 'actor_id', 'message'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
