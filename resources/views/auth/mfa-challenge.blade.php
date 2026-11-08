@extends('layouts.public', ['pageTitle' => 'Two-factor sign-in'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container">
        <div class="auth-card card is-centered">
            <span class="shield auth-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10.5 18.5h3"/><path d="M9.5 9.5l1.8 1.8 3.2-3.3"/></svg>
            </span>
            <h1>Enter your code</h1>
            <p class="auth-lead">Open your authenticator app and enter the 6-digit code for {{ $brand['site_name'] }}.</p>
            <form method="post" action="{{ route('mfa.verify') }}" novalidate>
                @csrf
                @include('partials.field', ['name' => 'code', 'label' => 'Code', 'required' => true, 'autocomplete' => 'one-time-code',
                    'inputClass' => 'otp-input', 'placeholder' => '000000',
                    'attrs' => 'inputmode="numeric" autofocus autocapitalize="characters" spellcheck="false"', 'maxlength' => 32,
                    'hint' => 'Lost your phone? Enter one of your 10-character recovery codes instead.'])
                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">Verify and sign in</button>
                    <a class="btn btn-secondary" href="{{ route('login') }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
