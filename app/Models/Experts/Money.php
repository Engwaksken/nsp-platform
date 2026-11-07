<?php

namespace App\Models\Experts;

/** Formats amounts kept in minor units. */
class Money
{
    public static function unitsPer(string $currency): int
    {
        return (int) (config('experts-marketplace.payments.minor_units')[$currency] ?? 100);
    }

    public static function format(int $minor, string $currency): string
    {
        $per = self::unitsPer($currency);

        return $currency.' '.number_format($minor / $per, $per === 1 ? 0 : 2);
    }

    public static function toMinor(string|float|int $major, string $currency): int
    {
        return (int) round(((float) $major) * self::unitsPer($currency));
    }
}
