<?php

namespace App\Models\Payments;

use App\Models\Experts\ExpertBooking;
use App\Models\Experts\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One attempt to collect money for a booking. Paid, refunded and disputed states come only from verified provider webhooks. */
class PaymentIntent extends Model
{
    public const STATUSES = ['requires_payment' => 'Payment needed', 'processing' => 'Processing', 'succeeded' => 'Succeeded',
        'failed' => 'Failed', 'refunded' => 'Refunded', 'partially_refunded' => 'Partly refunded', 'disputed' => 'Disputed'];

    protected $fillable = ['booking_uuid', 'provider', 'amount_minor', 'currency', 'status', 'provider_ref', 'idempotency_key', 'created_by'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'refunded_minor' => 'integer'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(ExpertBooking::class, 'booking_uuid');
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'payment_intent_id')->orderBy('id');
    }

    public function amountLabel(): string
    {
        return Money::format($this->amount_minor, $this->currency);
    }
}
