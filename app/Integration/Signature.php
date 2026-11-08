<?php

namespace App\Integration;

/** HMAC signatures for events in both directions (contract §4). */
class Signature
{
    public const TOLERANCE_SECONDS = 300;

    public static function sign(string $secret, int $timestamp, string $rawBody): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }

    /** Constant-time check of the signature and a 5-minute window against replays. */
    public static function verify(string $secret, ?string $timestampHeader, ?string $signatureHeader, string $rawBody, ?int $now = null): bool
    {
        if ($timestampHeader === null || $signatureHeader === null || ! ctype_digit($timestampHeader)) {
            return false;
        }
        $timestamp = (int) $timestampHeader;
        if (abs(($now ?? time()) - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $rawBody), $signatureHeader);
    }
}
