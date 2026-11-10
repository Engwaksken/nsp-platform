@extends('layouts.public', ['pageTitle' => 'Confirm your email'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container">
        <div class="auth-card card">
            <h1>Confirm your email</h1>
            @include('partials.flash')
            <p>We sent a confirmation link to <strong>{{ auth()->user()->email }}</strong>. Open that link to confirm this email address.</p>
            <form method="post" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Send a new confirmation link</button>
            </form>
        </div>
    </div>
@endsection
