<?php

namespace App\Support;

use App\Mail\ServiceNotice;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Security emails about the person's own account. Always sent (not subject to notification
 * preferences, no unsubscribe), so a change made by someone else is noticed quickly.
 */
class SecurityAlert
{
    public static function send(User $user, string $subject, string $what): void
    {
        try {
            Mail::to($user->email)->queue(new ServiceNotice($subject, [
                $what.' on '.now()->format('j F Y, H:i').' (server time).',
                'If this was you, you do not need to do anything.',
                'If it was not you, reset your password now and contact Hive at once: someone may have your password.',
            ], route('mfa.setup')));
        } catch (Throwable $exception) {
            Log::warning('[security] alert email failed: '.$exception->getMessage());
        }
    }
}
