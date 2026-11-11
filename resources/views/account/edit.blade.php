@extends('layouts.app', ['pageTitle' => 'My profile'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="security-wrap">
        <header class="security-head">
            <span class="avatar avatar-lg" aria-hidden="true">{{ $user->initials() }}</span>
            <div>
                <h1>My profile</h1>
                <p>Your name and email address on {{ $brand['site_name'] }}, and your password.</p>
            </div>
        </header>

        @include('partials.flash')

        @if ($user->pending_email)
            <section class="security-card notice-card" aria-labelledby="pending-heading">
                <h2 id="pending-heading">Confirm your new email address</h2>
                <p class="lead-muted">We sent a link to <strong>{{ $user->pending_email }}</strong>. Your email address stays <strong>{{ $user->email }}</strong> until you open it while signed in. The link works for 60 minutes.</p>
                <form method="post" action="{{ route('account.email.cancel') }}">
                    @csrf
                    @method('delete')
                    <button type="submit" class="btn btn-secondary btn-sm">Cancel the change</button>
                </form>
            </section>
        @endif

        @unless ($user->hasVerifiedEmail())
            <section class="security-card notice-card" aria-labelledby="verify-heading">
                <h2 id="verify-heading">Confirm your email address</h2>
                <p class="lead-muted">We sent a confirmation link to <strong>{{ $user->email }}</strong>. Open it to confirm this address.</p>
                <form method="post" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Resend confirmation</button>
                </form>
            </section>
        @endunless

        <div class="security-grid">
            <section class="security-card" aria-labelledby="profile-heading">
                <h2 id="profile-heading">Personal details</h2>
                <form method="post" action="{{ route('account.profile.update') }}" novalidate>
                    @csrf
                    @method('put')
                    @include('partials.field', ['name' => 'name', 'label' => 'Full name', 'value' => $user->name, 'required' => true, 'autocomplete' => 'name'])
                    @if ($canChangeCredentials)
                        @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'value' => $user->email, 'required' => true, 'autocomplete' => 'email',
                            'hint' => 'It changes only after you open the link we send to the new address. We also tell the old address.'])
                        <div>
                            @include('partials.field', ['name' => 'email_current_password', 'label' => 'Current password (only needed to change your email)', 'type' => 'password', 'value' => '', 'autocomplete' => 'current-password', 'placeholder' => false])
                        </div>
                    @else
                        <div class="form-group">
                            <span class="label-like">Email address</span>
                            <p class="static-value">{{ $user->email }}</p>
                            <p class="hint">Your email address comes from your organisation's sign-in. Change it there.</p>
                        </div>
                    @endif
                    <button type="submit" class="btn btn-primary">Save details</button>
                </form>
            </section>

            <section class="security-card" aria-labelledby="password-heading">
                <h2 id="password-heading">Password</h2>
                @if ($canChangeCredentials)
                    <p class="lead-muted">At least 12 characters, with letters and numbers. Changing it signs you out on your other devices.</p>
                    <form method="post" action="{{ route('account.password.update') }}" novalidate>
                        @csrf
                        @method('put')
                        @include('partials.field', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'value' => '', 'required' => true, 'autocomplete' => 'current-password', 'placeholder' => false])
                        @include('partials.field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'value' => '', 'required' => true, 'autocomplete' => 'new-password', 'placeholder' => false])
                        @include('partials.field', ['name' => 'password_confirmation', 'label' => 'Repeat new password', 'type' => 'password', 'value' => '', 'required' => true, 'autocomplete' => 'new-password', 'placeholder' => false])
                        <button type="submit" class="btn btn-primary">Change password</button>
                    </form>
                @else
                    <p class="lead-muted">You sign in through your organisation's single sign-on, so there is no {{ $brand['site_name'] }} password to change.</p>
                @endif
            </section>
        </div>

        <section class="security-card account-links" aria-labelledby="more-heading">
            <h2 id="more-heading">More settings</h2>
            <ul>
                <li><a href="{{ route('mfa.setup') }}"><i class="fas fa-lock" aria-hidden="true"></i> Sign-in security</a> — two-factor sign-in is <strong>{{ $user->hasMfa() ? 'on' : 'off' }}</strong>.</li>
                <li><a href="{{ route('notifications.preferences') }}"><i class="fas fa-sliders" aria-hidden="true"></i> Notification settings</a> — choose which emails you get.</li>
            </ul>
            <p class="lead-muted">Member since {{ $user->created_at?->format('j F Y') }}@if ($user->last_login_at) · last sign-in {{ $user->last_login_at->format('j F Y, H:i') }}@endif</p>
        </section>
    </div>
@endsection
