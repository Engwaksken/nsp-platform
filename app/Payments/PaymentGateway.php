<?php

namespace App\Payments;

use App\Models\Payments\PaymentIntent;
use Illuminate\Http\Request;

/**
 * Provider-agnostic payment gateway (M07). A real provider is added only after the owner decides
 * provider, currency, fees, refunds, settlement and compliance (owner decision 5).
 *
 * Rules every implementation follows:
 * - createPayment() and requestRefund() never change our payment state to paid or refunded;
 *   only a verified webhook does;
 * - verifyWebhook() checks the provider signature and a replay window;
 * - parseEvent() turns a verified body into a normalised event.
 */
interface PaymentGateway
{
    /** Provider key used in the webhook URL, e.g. "sandbox". */
    public function name(): string;

    /** True when no real money can move. The UI must then say "Sandbox — no real money". */
    public function isSandbox(): bool;

    /** Registers the payment with the provider and returns the provider reference. */
    public function createPayment(PaymentIntent $intent): string;

    /** Asks the provider to refund; the outcome arrives later by webhook. Returns the provider refund reference. */
    public function requestRefund(PaymentIntent $intent, int $amountMinor): string;

    public function verifyWebhook(Request $request): bool;

    /**
     * @return array{id: string, type: string, provider_ref: string, amount_minor: int, currency: string, outcome: ?string}|null
     */
    public function parseEvent(string $rawBody): ?array;
}
