<?php

namespace App\Models\Experts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The private conversation of one engagement: the expert and the startup's active members only. */
class ExpertConversation extends Model
{
    protected $fillable = ['engagement_uuid'];

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_uuid');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ExpertMessage::class, 'conversation_id')->orderBy('created_at')->orderBy('id');
    }
}
