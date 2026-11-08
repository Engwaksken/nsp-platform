Hello,

{{ $requesterName }} asked to join {{ $startupName }} on {{ \App\Support\Settings::get('site_name') }}.

Approve or decline the request on your team page:
{{ $teamUrl }}

Only approve people you know work with your startup.
@if (! empty($unsubscribeUrl))

You can turn off these emails here: {{ $unsubscribeUrl }}
Or choose what you receive: {{ route('notifications.preferences') }}
@endif
