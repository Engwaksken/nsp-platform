Hello,

{{ $inviterName }} invited you to join {{ $organizationName }} as {{ $roleLabel }} on {{ \App\Support\Settings::get('site_name') }}.

Accept the invitation (link valid for {{ $days }} days):
{{ $acceptUrl }}

Sign in with this email address to accept. If you were not expecting this, you can ignore this email.
