<?php

namespace App\Payments;

/** Known payment gateways. Only the sandbox exists until owner decision 5 is made. */
class Gateways
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const PROVIDERS = ['sandbox' => SandboxPaymentGateway::class];

    public static function for(string $provider): ?PaymentGateway
    {
        $class = self::PROVIDERS[$provider] ?? null;

        return $class ? app($class) : null;
    }

    /** The gateway new payments use. Falls back to the sandbox if config names an unknown provider. */
    public static function active(): PaymentGateway
    {
        return self::for((string) config('experts-marketplace.payments.gateway', 'sandbox')) ?? app(SandboxPaymentGateway::class);
    }
}
