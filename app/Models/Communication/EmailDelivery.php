<?php

namespace App\Models\Communication;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One queued email and what happened to it. Stores no message body and no address. */
class EmailDelivery extends Model
{
    protected $fillable = ['user_id', 'type', 'mailable', 'subject', 'status', 'error', 'sent_at', 'failed_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
