<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only payment ledger. Corrections are new entries; existing entries are never changed or removed. */
class LedgerEntry extends Model
{
    protected $table = 'payment_ledger_entries';

    public const UPDATED_AT = null;

    protected $fillable = ['payment_intent_id', 'booking_uuid', 'entry_type', 'amount_minor', 'currency', 'source', 'webhook_receipt_id', 'note'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('The payment ledger is append-only.');
        });
        static::deleting(function () {
            throw new LogicException('The payment ledger is append-only.');
        });
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }
}
