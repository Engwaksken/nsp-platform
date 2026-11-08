<?php

namespace App\Communication;

use App\Mail\ServiceNotice;
use App\Models\Communication\EmailDelivery;
use App\Models\Communication\NotificationPreference;
use App\Models\Communication\UserNotification;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * The one place activity notices go through (Phase 6a):
 * - an in-app notification unless the person turned that type off;
 * - an email unless they turned it off (announcements are opt-in);
 * - every email is logged in email_deliveries and carries a one-click unsubscribe.
 *
 * Security and account emails (password reset, invitations to an address) do not
 * use this: they must always be delivered.
 */
class Notifier
{
    /** @return array<string, array{label: string, description: string, email: bool, in_app: bool}> */
    public static function types(): array
    {
        return config('nsp.notifications.types', []);
    }

    /** @return array{email: bool, in_app: bool} */
    public function preferenceFor(User $user, string $type): array
    {
        $defaults = self::types()[$type] ?? ['email' => true, 'in_app' => true];
        $saved = NotificationPreference::query()->where('user_id', $user->id)->where('type', $type)->first();

        return ['email' => $saved ? $saved->email : (bool) $defaults['email'], 'in_app' => $saved ? $saved->in_app : (bool) $defaults['in_app']];
    }

    public function setPreference(User $user, string $type, bool $email, bool $inApp): void
    {
        NotificationPreference::query()->updateOrCreate(['user_id' => $user->id, 'type' => $type], ['email' => $email, 'in_app' => $inApp]);
    }

    /**
     * @param  list<string>  $lines  plain sentences; never private content such as messages or notes
     * @param  Mailable|null  $mailable  a specific email to send instead of the generic notice
     */
    public function notify(?User $user, string $type, string $title, array $lines, ?string $url = null, ?Mailable $mailable = null): void
    {
        if (! $user?->is_active) {
            return;
        }
        $preference = $this->preferenceFor($user, $type);

        if ($preference['in_app']) {
            UserNotification::query()->create([
                'user_id' => $user->id, 'type' => $type, 'title' => mb_substr($title, 0, 200),
                'body' => mb_substr(implode(' ', $lines), 0, 2000), 'url' => $url,
            ]);
        }
        if ($preference['email']) {
            $this->email($user, $type, $mailable ?? new ServiceNotice($title, array_values($lines), (string) $url));
        }
    }

    private function email(User $user, string $type, Mailable $mailable): void
    {
        $subject = method_exists($mailable, 'envelope') ? (string) ($mailable->envelope()->subject ?? '') : '';
        $delivery = EmailDelivery::query()->create([
            'user_id' => $user->id, 'type' => $type, 'mailable' => class_basename($mailable), 'subject' => mb_substr($subject, 0, 255), 'status' => 'queued',
        ]);
        if (in_array(TracksDelivery::class, class_uses_recursive($mailable), true)) {
            $mailable->deliveryId = $delivery->id;
            $mailable->unsubscribeUrl = URL::signedRoute('notifications.unsubscribe', ['user' => $user->id, 'type' => $type]);
        }
        try {
            Mail::to($user->email)->queue($mailable);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 1000), 'failed_at' => now()]);
            Log::warning('[notify] email could not be queued: '.$exception->getMessage());
        }
    }
}
