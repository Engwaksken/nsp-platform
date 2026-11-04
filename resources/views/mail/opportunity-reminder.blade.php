Hello,

An opportunity you saved on {{ \App\Support\Settings::get('site_name') }} closes soon:

{{ $opportunity->title }}@if ($opportunity->organisation) ({{ $opportunity->organisation }})@endif

Deadline: {{ $opportunity->deadline_at?->format('l j F Y, H:i') }}

Details: {{ route('opportunities.show', $opportunity) }}

You get this reminder once because you saved this opportunity. Unsave it on the page to stop reminders for it.
