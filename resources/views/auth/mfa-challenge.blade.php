@extends('layouts.public', ['pageTitle' => 'Two-factor sign-in'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container">
        <div class="auth-card card">
            <h1>Enter your code</h1>
            <p>Open your authenticator app and enter the 6-digit code for {{ $brand['site_name'] }}.</p>
            <form method="post" action="{{ route('mfa.verify') }}" novalidate>
                @csrf
                @include('partials.field', ['name' => 'code', 'label' => 'Code', 'required' => true, 'autocomplete' => 'one-time-code',
                    'attrs' => 'inputmode="numeric" autofocus', 'maxlength' => 32,
                    'hint' => 'Lost your phone? Enter one of your 10-character recovery codes instead.'])
                <button type="submit" class="btn btn-primary">Verify</button>
                <a class="btn btn-secondary" href="{{ route('login') }}">Cancel</a>
            </form>
        </div>
    </div>
@endsection
