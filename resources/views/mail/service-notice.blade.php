Hello,

@foreach ($lines as $line)
{{ $line }}

@endforeach
Open NSP: {{ $url }}

You receive this because you take part in Hive NSP. Manage your account at {{ route('workspace') }}.
@if (! empty($unsubscribeUrl))

You can turn off these emails here: {{ $unsubscribeUrl }}
Or choose what you receive: {{ route('notifications.preferences') }}
@endif
