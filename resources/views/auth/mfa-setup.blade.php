@extends('layouts.public', ['pageTitle' => 'Set up two-factor sign-in'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>Set up two-factor sign-in</h1>
        @include('partials.flash')
        @if ($required)
            <p>Your role can change the website and see personal data, so you need a second step when you sign in.</p>
        @endif
        <ol>
            <li>Install an authenticator app on your phone, such as Google Authenticator, Microsoft Authenticator or Authy.</li>
            <li>In the app, add an account and scan this QR code.</li>
            <li>Enter the 6-digit code the app shows.</li>
        </ol>
        <figure class="card qr-card">
            {!! $qrSvg !!}
            <figcaption class="hint">Can't scan? Enter this key in the app instead:<br><code>{{ $secret }}</code></figcaption>
        </figure>
        <form method="post" action="{{ route('mfa.enable') }}" class="panel" novalidate>
            @csrf
            @include('partials.field', ['name' => 'code', 'label' => 'Code from the app', 'required' => true, 'autocomplete' => 'one-time-code',
                'attrs' => 'inputmode="numeric" pattern="[0-9 ]*"', 'maxlength' => 10])
            <button type="submit" class="btn btn-primary">Turn on two-factor sign-in</button>
        </form>
    </div>
@endsection
