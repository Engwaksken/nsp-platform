<?php

namespace App\Models\Experts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpertMessage extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'body'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ExpertConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
