<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Records sensitive changes. Never pass passwords, tokens or secrets in $changes. */
class Audit
{
    private const REDACT = ['password', 'password_confirmation', 'remember_token', 'client_secret', 'token', 'secret'];

    public static function log(string $action, Model|string|null $subject = null, array $changes = []): AuditLog
    {
        $request = request();
        foreach (array_keys($changes) as $key) {
            if (in_array(strtolower((string) $key), self::REDACT, true)) {
                $changes[$key] = '[redacted]';
            }
        }

        return AuditLog::query()->create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject instanceof Model ? $subject->getMorphClass() : $subject,
            'subject_id' => $subject instanceof Model ? (string) $subject->getKey() : null,
            'changes' => $changes ?: null,
            'ip_hash' => self::hashIdentifier($request?->ip()),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    /** Keyed hash so IPs and emails in logs can be correlated without storing them in clear. */
    public static function hashIdentifier(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash_hmac('sha256', strtolower($value), (string) config('app.key'));
    }
}
