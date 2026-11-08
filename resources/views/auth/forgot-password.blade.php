@extends('layouts.public', ['pageTitle' => 'Reset your password'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container">
        <div class="auth-card card">
            <h1>Reset your password</h1>
            @include('partials.flash')
            <p>Enter your email and we'll send a reset link. If you sign in with your Hive account, reset your password there instead.</p>
            <form method="post" action="{{ route('password.email') }}" novalidate>
                @csrf
                @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
                <button type="submit" class="btn btn-primary">Send reset link</button>
                <a class="btn btn-secondary" href="{{ route('login') }}">Back to sign in</a>
            </form>
        </div>
    </div>
@endsection
