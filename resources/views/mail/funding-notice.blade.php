Hello,

@foreach ($lines as $line)
{{ $line }}

@endforeach
Open NSP: {{ $url }}

Hive NSP connects startups and investors. It does not promise or arrange funding.
@if (! empty($unsubscribeUrl))

You can turn off these emails here: {{ $unsubscribeUrl }}
Or choose what you receive: {{ route('notifications.preferences') }}
@endif
