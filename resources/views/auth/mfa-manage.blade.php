@extends('layouts.app', ['pageTitle' => 'Account security'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="security-wrap">
        <header class="security-head">
            <span class="shield is-on" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></svg>
            </span>
            <div>
                <h1>Account security</h1>
                <p>Two-factor sign-in is on for {{ $user->email }}. Each sign-in needs your password and a code from your authenticator app.</p>
            </div>
        </header>

        @include('partials.flash')

        @if ($recoveryCodes)
            <section class="security-card codes-card" aria-labelledby="codes-heading">
                <h2 id="codes-heading">Save your recovery codes now</h2>
                <p>If you lose your phone, each code lets you sign in once. <strong>They will not be shown again.</strong> Store them somewhere safe, such as a password manager.</p>
                <ul class="recovery-codes" id="recovery-codes">
                    @foreach ($recoveryCodes as $code)<li><code>{{ $code }}</code></li>@endforeach
                </ul>
                <div class="actions">
                    <button type="button" class="btn btn-primary btn-sm" data-download="#recovery-codes" data-filename="{{ \Illuminate\Support\Str::slug($brand['site_name']) }}-recovery-codes.txt">Download</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-copy="#recovery-codes">Copy</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-print>Print</button>
                </div>
            </section>
        @endif

        <section class="security-card" aria-labelledby="status-heading">
            <h2 id="status-heading">Two-factor sign-in is on</h2>
            <dl class="status-row">
                <div><dt>Turned on</dt><dd>{{ $user->mfa_enabled_at->format('j M Y') }}</dd></div>
                <div><dt>Recovery codes left</dt><dd>{{ $user->recoveryCodesLeft() }} of 8</dd></div>
            </dl>
            @if (auth()->user()->canUseAdmin())
                <p class="status-cta"><a class="btn btn-primary" href="{{ route('admin.dashboard') }}">Go to the admin area</a></p>
            @endif
        </section>

        @if ($user->password)
            <div class="security-grid">
                <section class="security-card" aria-labelledby="new-codes-heading">
                    <h2 id="new-codes-heading">New recovery codes</h2>
                    <p class="lead-muted">Creates 8 new codes. Your old codes stop working.</p>
                    <form method="post" action="{{ route('mfa.recovery') }}">
                        @csrf
                        @include('partials.field', ['name' => 'password', 'id' => 'recovery-password', 'label' => 'Your password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'value' => ''])
                        <button type="submit" class="btn btn-secondary">Create new codes</button>
                    </form>
                </section>
                @unless ($user->requiresMfa())
                    <section class="security-card is-danger-zone" aria-labelledby="disable-heading">
                        <h2 id="disable-heading">Turn off</h2>
                        <p class="lead-muted">Signing in will only need your password again. Not recommended.</p>
                        <form method="post" action="{{ route('mfa.disable') }}" data-confirm="Turn off two-factor sign-in? Your account will be protected by your password only.">
                            @csrf
                            @include('partials.field', ['name' => 'password', 'id' => 'disable-password', 'label' => 'Your password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'value' => ''])
                            <button type="submit" class="btn btn-danger">Turn off two-factor sign-in</button>
                        </form>
                    </section>
                @endunless
            </div>
        @endif
    </div>
@endsection
