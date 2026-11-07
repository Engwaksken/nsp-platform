<?php

namespace App\Payments;

use App\Integration\Signature;
use App\Models\Payments\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sandbox gateway: no network calls and no real money. References are random strings.
 * Webhooks use the same HMAC-with-timestamp scheme as the IMS integration (App\Integration\Signature).
 * Staff (and tests) can produce correctly signed sandbox events with signedEvent() to simulate the provider.
 */
class SandboxPaymentGateway implements PaymentGateway
{
    public const TIMESTAMP_HEADER = 'X-Payment-Timestamp';

    public const SIGNATURE_HEADER = 'X-Payment-Signature';

    public const EVENT_TYPES = ['payment.succeeded', 'payment.failed', 'refund.succeeded', 'dispute.opened', 'dispute.closed'];

    public function name(): string
    {
        return 'sandbox';
    }

    public function isSandbox(): bool
    {
        return true;
    }

    public function createPayment(PaymentIntent $intent): string
    {
        return 'sbx_pay_'.Str::lower(Str::random(24));
    }

    public function requestRefund(PaymentIntent $intent, int $amountMinor): string
    {
        return 'sbx_ref_'.Str::lower(Str::random(24));
    }

    public function verifyWebhook(Request $request): bool
    {
        return Signature::verify($this->secret(), $request->header(self::TIMESTAMP_HEADER), $request->header(self::SIGNATURE_HEADER), $request->getContent());
    }

    public function parseEvent(string $rawBody): ?array
    {
        $data = json_decode($rawBody, true);
        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! in_array($data['type'] ?? null, self::EVENT_TYPES, true)) {
            return null;
        }
        $object = $data['data'] ?? [];
        if (! is_array($object) || ! is_string($object['provider_ref'] ?? null) || ! is_int($object['amount_minor'] ?? null) || ! is_string($object['currency'] ?? null)) {
            return null;
        }

        return [
            'id' => mb_substr($data['id'], 0, 100), 'type' => $data['type'], 'provider_ref' => mb_substr($object['provider_ref'], 0, 100),
            'amount_minor' => $object['amount_minor'], 'currency' => strtoupper(mb_substr($object['currency'], 0, 3)),
            'outcome' => isset($object['outcome']) && in_array($object['outcome'], ['won', 'lost'], true) ? $object['outcome'] : null,
        ];
    }

    /**
     * A correctly signed sandbox event, as the provider would send it.
     *
     * @return array{body: string, headers: array<string, string>}
     */
    public function signedEvent(string $type, PaymentIntent $intent, ?int $amountMinor = null, ?string $outcome = null, ?string $eventId = null, ?int $timestamp = null): array
    {
        $body = json_encode([
            'id' => $eventId ?? 'evt_sbx_'.Str::lower(Str::random(20)),
            'type' => $type,
            'data' => array_filter([
                'provider_ref' => $intent->provider_ref, 'amount_minor' => $amountMinor ?? $intent->amount_minor,
                'currency' => $intent->currency, 'outcome' => $outcome,
            ], fn ($v) => $v !== null),
        ], JSON_THROW_ON_ERROR);
        $timestamp ??= time();

        return ['body' => $body, 'headers' => [
            self::TIMESTAMP_HEADER => (string) $timestamp,
            self::SIGNATURE_HEADER => Signature::sign($this->secret(), $timestamp, $body),
        ]];
    }

    /** From the environment; in sandbox only, derived from APP_KEY when unset so local simulation works without a committed secret. */
    private function secret(): string
    {
        $configured = config('experts-marketplace.payments.providers.sandbox.webhook_secret');

        return is_string($configured) && $configured !== ''
            ? $configured
            : hash_hmac('sha256', 'nsp-sandbox-payment-webhooks', (string) config('app.key'));
    }
}
