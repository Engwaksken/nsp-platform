@extends('layouts.app', ['pageTitle' => 'Set up two-factor sign-in'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="security-wrap">
        <header class="security-head">
            <span class="shield" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z"/><rect x="9" y="10.5" width="6" height="5" rx="1"/><path d="M10.5 10.5V9a1.5 1.5 0 013 0v1.5"/></svg>
            </span>
            <div>
                <h1>Set up two-factor sign-in</h1>
                <p>
                    @if ($required)
                        Your role can change the website and see personal data, so signing in needs a second step: a code from your phone.
                    @else
                        Add a second step to signing in: a 6-digit code from an app on your phone. Even if someone learns your password, they can't get in.
                    @endif
                </p>
            </div>
        </header>

        @include('partials.flash')

        <div class="setup-grid">
            <section class="security-card" aria-labelledby="steps-heading">
                <h2 id="steps-heading">Three quick steps</h2>
                <p class="lead-muted">This takes about a minute. Keep this page open while you use your phone.</p>
                <ol class="steps">
                    <li>
                        <strong>Install an authenticator app</strong>
                        <p>Google Authenticator, Microsoft Authenticator, Authy or 1Password all work. They are free.</p>
                    </li>
                    <li>
                        <strong>Scan the QR code</strong>
                        <p>In the app, choose <em>Add account</em> or <em>+</em>, then point the camera at the code on this page.</p>
                    </li>
                    <li>
                        <strong>Enter the 6-digit code</strong>
                        <p>Type the code the app shows for {{ $brand['site_name'] }}. It changes every 30 seconds.</p>
                        <form method="post" action="{{ route('mfa.enable') }}" novalidate class="setup-form">
                            @csrf
                            @include('partials.field', ['name' => 'code', 'label' => 'Code from the app', 'required' => true, 'autocomplete' => 'one-time-code',
                                'inputClass' => 'otp-input', 'placeholder' => '000000',
                                'attrs' => 'inputmode="numeric" pattern="[0-9 ]*" data-otp="digits" autofocus', 'maxlength' => 10])
                            <button type="submit" class="btn btn-primary">Turn on two-factor sign-in</button>
                        </form>
                    </li>
                </ol>
            </section>

            <section class="security-card qr-panel" aria-labelledby="qr-heading">
                <h2 id="qr-heading">Scan with your app</h2>
                <figure class="qr-frame" role="img" aria-label="QR code for setting up the authenticator app">
                    {!! $qrSvg !!}
                </figure>
                <details>
                    <summary>Can't scan the code?</summary>
                    <p class="hint">Choose <em>Enter a setup key</em> in the app, use the account name {{ auth()->user()->email }}, and type this key (time-based):</p>
                    <div class="secret-key">
                        <code id="mfa-secret">{{ $secret }}</code>
                        <button type="button" class="btn btn-secondary btn-sm" data-copy="#mfa-secret">Copy</button>
                    </div>
                </details>
            </section>
        </div>
    </div>
@endsection
