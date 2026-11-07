<?php

namespace App\Models\Growth;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user's response to one VGI-driven action suggestion for a pinned assessment. */
class VgiGuidanceFeedback extends Model
{
    protected $table = 'vgi_guidance_feedback';

    protected $fillable = ['startup_uuid', 'assessment_uuid', 'user_id', 'dimension_key', 'feedback', 'explanation'];

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(VgiAssessment::class, 'assessment_uuid', 'uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
