<?php

namespace App\Http\Controllers;

use App\Communication\Notifier;
use App\Models\Communication\UserNotification;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The notification centre, preferences and one-click unsubscribe. */
class NotificationController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    public function index(Request $request): View
    {
        $notifications = UserNotification::query()->where('user_id', $request->user()->id)
            ->when($request->query('show') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest('id')->paginate(30)->withQueryString();

        return view('notifications.index', ['notifications' => $notifications, 'show' => $request->query('show')]);
    }

    /** Opens the linked page and marks the notification read. Only local links are followed. */
    public function open(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        $url = (string) $notification->url;
        $target = parse_url($url);
        $site = parse_url(url('/'));
        $local = $url !== '' && is_array($target) && ($target['scheme'] ?? null) === ($site['scheme'] ?? null)
            && strcasecmp($target['host'] ?? '', $site['host'] ?? '') === 0 && ($target['port'] ?? null) === ($site['port'] ?? null)
            && ! isset($target['user']);

        return $local ? redirect()->to($url) : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        UserNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }

    public function preferences(Request $request): View
    {
        $user = $request->user();
        $preferences = [];
        foreach (array_keys(Notifier::types()) as $type) {
            $preferences[$type] = $this->notifier->preferenceFor($user, $type);
        }

        return view('notifications.preferences', ['types' => Notifier::types(), 'preferences' => $preferences]);
    }

    public function savePreferences(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['nullable', 'array'], 'in_app' => ['nullable', 'array']]);
        foreach (array_keys(Notifier::types()) as $type) {
            $this->notifier->setPreference($request->user(), $type, (bool) $request->input("email.$type"), (bool) $request->input("in_app.$type"));
        }
        Audit::log('notifications.preferences_saved', $request->user());

        return back()->with('status', 'Your choices were saved.');
    }

    /** Signed link from an email. GET asks to confirm (link scanners must not unsubscribe); POST does it (also RFC 8058 one-click). */
    public function unsubscribe(Request $request, User $user, string $type): View
    {
        abort_unless(array_key_exists($type, Notifier::types()), 404);

        return view('notifications.unsubscribe', ['user' => $user, 'type' => $type, 'label' => Notifier::types()[$type]['label'], 'done' => false,
            'action' => $request->fullUrl()]);
    }

    public function confirmUnsubscribe(Request $request, User $user, string $type): View
    {
        abort_unless(array_key_exists($type, Notifier::types()), 404);
        $current = $this->notifier->preferenceFor($user, $type);
        $this->notifier->setPreference($user, $type, false, $current['in_app']);
        Audit::log('notifications.unsubscribed', $user, ['type' => $type]);

        return view('notifications.unsubscribe', ['user' => $user, 'type' => $type, 'label' => Notifier::types()[$type]['label'], 'done' => true, 'action' => null]);
    }
}
