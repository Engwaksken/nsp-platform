@extends('layouts.public', ['pageTitle' => 'Check your email'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container">
        <div class="auth-card card">
            <h1>Check your email</h1>
            <p>We sent a message to <strong>{{ $email }}</strong>. Open the link in it to confirm your address, then sign in to get started.</p>
            <p class="hint">Nothing arrived after a few minutes? Check your spam folder. If you already had an account, the email tells you how to sign in.</p>
            <p><a class="btn btn-primary" href="{{ route('login') }}">Go to sign in</a></p>
        </div>
    </div>
@endsection
