<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;

/** Every webhook request received: verified or not, processed or a duplicate. Payloads are kept only as a hash. */
class WebhookReceipt extends Model
{
    protected $table = 'payment_webhook_receipts';

    protected $fillable = ['provider', 'event_id', 'claimed_event_id', 'event_type', 'signature_valid', 'payload_hash', 'result', 'processed_at'];

    protected function casts(): array
    {
        return ['signature_valid' => 'boolean', 'processed_at' => 'datetime'];
    }
}
