Hello,

{{ $inviterName }} invited you to join {{ $startupName }} as {{ $roleLabel }} on {{ \App\Support\Settings::get('site_name') }}.

Accept the invitation (link valid for {{ config('nsp.registry.invite_days', 7) }} days):
{{ $acceptUrl }}

Sign in with this email address to accept. If you were not expecting this, you can ignore this email.
