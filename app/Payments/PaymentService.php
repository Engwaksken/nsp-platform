<?php

namespace App\Payments;

use App\Models\Experts\ExpertBooking;
use App\Models\Payments\LedgerEntry;
use App\Models\Payments\PaymentIntent;
use App\Models\Payments\WebhookReceipt;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Payment rules for expert bookings (sandbox first):
 * - starting a payment creates an intent and a provider reference; nothing is "paid" yet;
 * - paid, failed, refunded and disputed come ONLY from a webhook whose signature verified;
 * - a webhook event id is processed once; replays are acknowledged and ignored;
 * - every money movement is a new row in the append-only ledger.
 */
class PaymentService
{
    public function gateway(): PaymentGateway
    {
        return Gateways::active();
    }

    /** Creates (or reuses) the intent for a confirmed, priced booking. */
    public function start(ExpertBooking $booking, User $user): PaymentIntent
    {
        if (! $booking->isPaid()) {
            throw new RegistryException('This booking is free.');
        }
        if ($booking->status !== 'confirmed') {
            throw new RegistryException('You can pay once the expert has confirmed the booking.');
        }
        if (! in_array($booking->payment_status, ['requires_payment', 'processing', 'failed'], true)) {
            throw new RegistryException('This booking does not need a payment now.');
        }
        $gateway = $this->gateway();

        return DB::transaction(function () use ($booking, $user, $gateway) {
            $open = PaymentIntent::query()->where('booking_uuid', $booking->uuid)->whereIn('status', ['requires_payment', 'processing'])->lockForUpdate()->first();
            if ($open) {
                return $open;
            }
            $attempt = PaymentIntent::query()->where('booking_uuid', $booking->uuid)->count() + 1;
            $intent = PaymentIntent::query()->create([
                'booking_uuid' => $booking->uuid, 'provider' => $gateway->name(), 'amount_minor' => (int) $booking->price_minor,
                'currency' => (string) $booking->currency, 'status' => 'requires_payment',
                'idempotency_key' => 'booking:'.$booking->uuid.':attempt:'.$attempt, 'created_by' => $user->id,
            ]);
            $intent->forceFill(['provider_ref' => $gateway->createPayment($intent), 'status' => 'processing'])->save();
            $booking->forceFill(['payment_status' => 'processing'])->save();
            Audit::log('payments.intent_created', $intent, ['booking' => $booking->uuid, 'provider' => $intent->provider]);

            return $intent;
        });
    }

    /** Asks the provider for a refund. The refund is recorded only when its webhook arrives. */
    public function requestRefund(ExpertBooking $booking, int $amountMinor, string $reason): ?PaymentIntent
    {
        $intent = PaymentIntent::query()->where('booking_uuid', $booking->uuid)->whereIn('status', ['succeeded', 'partially_refunded'])->latest('id')->first();
        if (! $intent || $amountMinor <= 0) {
            return null;
        }
        $amountMinor = min($amountMinor, $intent->amount_minor - $intent->refunded_minor);
        $reference = Gateways::for($intent->provider)?->requestRefund($intent, $amountMinor);
        $booking->forceFill(['payment_status' => 'refund_pending'])->save();
        Audit::log('payments.refund_requested', $intent, ['amount_minor' => $amountMinor, 'reason' => $reason, 'provider_refund_ref' => $reference]);

        return $intent;
    }

    /**
     * Handles one webhook request.
     *
     * @return array{0: int, 1: string} HTTP status and a short result
     */
    public function handleWebhook(string $provider, Request $request): array
    {
        $gateway = Gateways::for($provider);
        if (! $gateway) {
            return [404, 'unknown_provider'];
        }
        $raw = $request->getContent();
        $hash = hash('sha256', $raw);

        if (! $gateway->verifyWebhook($request)) {
            $claimed = json_decode($raw, true);
            WebhookReceipt::query()->create([
                'provider' => $provider, 'event_id' => null, 'signature_valid' => false, 'payload_hash' => $hash, 'result' => 'invalid_signature',
                'claimed_event_id' => is_array($claimed) && is_string($claimed['id'] ?? null) ? mb_substr($claimed['id'], 0, 100) : null,
            ]);

            return [400, 'invalid_signature'];
        }
        $event = $gateway->parseEvent($raw);
        if (! $event) {
            WebhookReceipt::query()->create(['provider' => $provider, 'signature_valid' => true, 'payload_hash' => $hash, 'result' => 'malformed']);

            return [422, 'malformed'];
        }
        if (WebhookReceipt::query()->where('provider', $provider)->where('event_id', $event['id'])->exists()) {
            return [200, 'duplicate'];
        }

        try {
            $result = DB::transaction(function () use ($provider, $event, $hash) {
                $receipt = WebhookReceipt::query()->create([
                    'provider' => $provider, 'event_id' => $event['id'], 'event_type' => $event['type'], 'signature_valid' => true, 'payload_hash' => $hash,
                ]);
                $result = $this->apply($provider, $event, $receipt);
                $receipt->forceFill(['result' => $result, 'processed_at' => now()])->save();

                return $result;
            });
        } catch (UniqueConstraintViolationException) {
            return [200, 'duplicate'];
        }

        return [200, $result];
    }

    private function apply(string $provider, array $event, WebhookReceipt $receipt): string
    {
        $intent = PaymentIntent::query()->where('provider', $provider)->where('provider_ref', $event['provider_ref'])->lockForUpdate()->first();
        if (! $intent) {
            return 'unknown_payment';
        }
        $booking = $intent->booking;
        if ($event['currency'] !== $intent->currency) {
            Audit::log('payments.currency_mismatch', $intent, ['event' => $event['id']]);

            return 'currency_mismatch';
        }
        $ledger = fn (string $type, int $amount, string $note = '') => LedgerEntry::query()->create([
            'payment_intent_id' => $intent->id, 'booking_uuid' => $intent->booking_uuid, 'entry_type' => $type, 'amount_minor' => $amount,
            'currency' => $intent->currency, 'source' => 'webhook', 'webhook_receipt_id' => $receipt->id, 'note' => $note ?: null,
        ]);
        $setStatus = function (string $intentStatus, string $bookingStatus) use ($intent, $booking) {
            $intent->forceFill(['status' => $intentStatus])->save();
            $booking?->forceFill(['payment_status' => $bookingStatus])->save();
        };

        switch ($event['type']) {
            case 'payment.succeeded':
                if (! in_array($intent->status, ['requires_payment', 'processing', 'failed'], true)) {
                    return 'no_change';
                }
                if ($event['amount_minor'] !== $intent->amount_minor) {
                    Audit::log('payments.amount_mismatch', $intent, ['event' => $event['id']]);

                    return 'amount_mismatch';
                }
                $setStatus('succeeded', 'paid');
                $ledger('payment_succeeded', $intent->amount_minor);
                break;
            case 'payment.failed':
                if (! in_array($intent->status, ['requires_payment', 'processing'], true)) {
                    return 'no_change';
                }
                $setStatus('failed', 'failed');
                $ledger('payment_failed', 0);
                break;
            case 'refund.succeeded':
                if (! in_array($intent->status, ['succeeded', 'partially_refunded'], true)) {
                    return 'no_change';
                }
                $amount = min(max(0, $event['amount_minor']), $intent->amount_minor - $intent->refunded_minor);
                if ($amount === 0) {
                    return 'no_change';
                }
                $intent->refunded_minor += $amount;
                $full = $intent->refunded_minor >= $intent->amount_minor;
                $setStatus($full ? 'refunded' : 'partially_refunded', $full ? 'refunded' : 'partially_refunded');
                $ledger('refund_succeeded', -$amount);
                break;
            case 'dispute.opened':
                if (! in_array($intent->status, ['succeeded', 'partially_refunded'], true)) {
                    return 'no_change';
                }
                $setStatus('disputed', 'disputed');
                $ledger('dispute_opened', -($intent->amount_minor - $intent->refunded_minor), 'Funds held by the provider during the dispute');
                break;
            case 'dispute.closed':
                if ($intent->status !== 'disputed' || ! $event['outcome']) {
                    return 'no_change';
                }
                if ($event['outcome'] === 'won') {
                    $setStatus($intent->refunded_minor > 0 ? 'partially_refunded' : 'succeeded', $intent->refunded_minor > 0 ? 'partially_refunded' : 'paid');
                    $ledger('dispute_won', $intent->amount_minor - $intent->refunded_minor, 'Funds released back after the dispute');
                } else {
                    $setStatus('refunded', 'refunded');
                    $ledger('dispute_lost', 0, 'Dispute lost: the payer keeps the funds');
                }
                break;
            default:
                return 'ignored';
        }
        Audit::log('payments.'.str_replace('.', '_', $event['type']), $intent, ['event' => $event['id'], 'booking' => $intent->booking_uuid]);

        return 'applied';
    }
}
