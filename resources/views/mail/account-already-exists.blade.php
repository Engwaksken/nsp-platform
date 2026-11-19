Hello,

Someone just tried to create a new account with this email address. You already have one, so nothing was changed.

@if ($singleSignOnOnly)
Your account signs in with your Hive login: {{ route('login') }}
@else
Sign in here: {{ route('login') }}

Forgot your password? Reset it here: {{ route('password.request') }}
@endif

If this wasn't you, you can ignore this email. Your account is safe.
