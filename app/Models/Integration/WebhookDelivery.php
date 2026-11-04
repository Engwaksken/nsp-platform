<?php

namespace App\Models\Integration;

use App\Models\Registry\OutboxEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    /** Minutes to wait after each failed attempt; then every 12 hours (contract §4.3). */
    public const BACKOFF_MINUTES = [1, 5, 15, 60, 240];

    public const MAX_ATTEMPTS = 8;

    protected $fillable = ['event_id', 'api_client_id', 'status', 'attempts', 'next_attempt_at', 'delivered_at', 'last_status_code', 'last_error'];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(OutboxEvent::class, 'event_id', 'event_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public static function delayAfter(int $attempts): int
    {
        return self::BACKOFF_MINUTES[$attempts - 1] ?? 720;
    }
}
