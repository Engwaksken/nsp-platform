<?php

namespace App\Models\Experts;

use App\Experts\Casts\UtcDateTime;
use App\Models\Payments\PaymentIntent;
use App\Models\Registry\Startup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A booked time with an expert. start_at and end_at are UTC; show them with startIn()/endIn().
 * Requested and confirmed bookings hold the expert's time, so they can never overlap.
 */
class ExpertBooking extends Model
{
    use HasUuids;

    protected $table = 'expert_bookings';

    protected $primaryKey = 'uuid';

    public const STATUSES = ['requested' => 'Waiting for the expert', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled',
        'completed' => 'Completed', 'no_show' => 'No-show'];

    /** Statuses that block the expert's calendar. */
    public const HOLDING = ['requested', 'confirmed'];

    public const PAYMENT_STATUSES = ['not_required' => 'No payment', 'requires_payment' => 'Payment needed', 'processing' => 'Waiting for provider confirmation',
        'paid' => 'Paid (sandbox, confirmed by a verified webhook)', 'failed' => 'Payment failed', 'refund_pending' => 'Refund requested',
        'refunded' => 'Refunded', 'partially_refunded' => 'Partly refunded', 'disputed' => 'Disputed'];

    protected $fillable = ['expert_user_id', 'startup_uuid', 'engagement_uuid', 'service_id', 'booked_by', 'topic', 'start_at', 'end_at',
        'founder_timezone', 'expert_timezone', 'status', 'duration_minutes', 'price_minor', 'currency', 'payment_status', 'policy_snapshot'];

    protected function casts(): array
    {
        return [
            'start_at' => UtcDateTime::class, 'end_at' => UtcDateTime::class, 'policy_snapshot' => 'array',
            'cancelled_at' => 'datetime', 'confirmed_at' => 'datetime', 'completed_at' => 'datetime',
            'duration_minutes' => 'integer', 'price_minor' => 'integer',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expert_user_id');
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_uuid');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'service_id');
    }

    public function booker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class, 'booking_uuid')->latest('id');
    }

    public function startIn(string $timezone): CarbonImmutable
    {
        return $this->start_at->setTimezone($timezone);
    }

    public function endIn(string $timezone): CarbonImmutable
    {
        return $this->end_at->setTimezone($timezone);
    }

    public function isPaid(): bool
    {
        return (int) $this->price_minor > 0 && (bool) $this->currency;
    }

    public function priceLabel(): string
    {
        return $this->isPaid() ? Money::format((int) $this->price_minor, (string) $this->currency) : 'Pro bono (free)';
    }
}
