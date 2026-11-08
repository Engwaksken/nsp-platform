<?php

namespace App\Models\Experts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A concern raised by a party about an engagement, booking, payment, review or conduct. */
class ExpertComplaint extends Model
{
    public const KINDS = ['conduct' => 'Conduct or abuse', 'conversation' => 'Messages', 'booking' => 'Booking or no-show',
        'payment' => 'Payment or refund', 'review' => 'Disputed review', 'other' => 'Something else'];

    public const STATUSES = ['open' => 'Open', 'reviewing' => 'Being reviewed', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];

    protected $fillable = ['engagement_uuid', 'booking_uuid', 'review_id', 'raised_by', 'kind', 'description'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_uuid');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ExpertReview::class, 'review_id');
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
