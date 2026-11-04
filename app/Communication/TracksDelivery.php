<?php

namespace App\Communication;

use App\Models\Communication\EmailDelivery;
use Illuminate\Mail\Mailables\Headers;
use Throwable;

/**
 * For notice mailables sent through the Notifier: adds the delivery id header
 * (so the MessageSent listener can mark it sent), RFC 8058 one-click unsubscribe
 * headers, retries with backoff, and marks the delivery failed when retries run out.
 */
trait TracksDelivery
{
    public ?int $deliveryId = null;

    public ?string $unsubscribeUrl = null;

    public int $tries = 3;

    /** @return list<int> seconds between attempts */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function headers(): Headers
    {
        $text = [];
        if ($this->deliveryId) {
            $text['X-NSP-Delivery'] = (string) $this->deliveryId;
        }
        if ($this->unsubscribeUrl) {
            $text['List-Unsubscribe'] = '<'.$this->unsubscribeUrl.'>';
            $text['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new Headers(text: $text);
    }

    public function failed(Throwable $exception): void
    {
        if ($this->deliveryId) {
            EmailDelivery::query()->whereKey($this->deliveryId)->update([
                'status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 1000), 'failed_at' => now(),
            ]);
        }
    }
}
