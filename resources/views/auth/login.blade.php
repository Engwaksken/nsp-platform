@extends('layouts.public', ['pageTitle' => 'Sign in'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container">
        <div class="auth-card card">
            <h1>Sign in</h1>

            @error('oidc')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
            @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

            @if ($oidcLogin)
                <p><a class="btn btn-primary" href="{{ route('oidc.redirect') }}">Sign in with your Hive account</a></p>
                <p class="hint">One Hive login works for NSP and Hive programs.</p>
            @endif

            @if ($oidcLogin && $localLogin)
                <div class="divider">or</div>
            @endif

            @if ($localLogin)
                <form method="post" action="{{ route('login.store') }}" novalidate>
                    @csrf
                    @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'autocomplete' => 'username'])
                    @include('partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'value' => ''])
                    <div class="form-group">
                        <label class="checkbox"><input type="checkbox" name="remember" value="1"> <span>Keep me signed in on this device</span></label>
                    </div>
                    <button type="submit" class="btn btn-primary">Sign in</button>
                    <a class="btn-link" href="{{ route('password.request') }}">Forgot your password?</a>
                </form>
            @endif

            @if (!$oidcLogin && !$localLogin)
                <p class="alert alert-warning">Sign-in is not available right now. Please try again later.</p>
            @endif
        </div>
    </div>
@endsection
