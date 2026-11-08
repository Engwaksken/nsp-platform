<?php

namespace App\Experts\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a moment as UTC wall time ("Y-m-d H:i:s") whatever the app time zone is, and reads
 * it back as a UTC CarbonImmutable. Convert with ->setTimezone() only for display.
 */
class UtcDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::createFromFormat('Y-m-d H:i:s', $value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        $moment = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value, 'UTC');

        return $moment->utc()->format('Y-m-d H:i:s');
    }
}
