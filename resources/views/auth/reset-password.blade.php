@extends('layouts.public', ['pageTitle' => 'Choose a new password'])

@push('head')<meta name="robots" content="noindex"><meta name="referrer" content="no-referrer">@endpush

@section('content')
    <div class="container">
        <div class="auth-card card">
            <h1>Choose a new password</h1>
            @include('partials.flash')
            <form method="post" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'value' => $email, 'required' => true, 'autocomplete' => 'username'])
                @include('partials.field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'value' => '', 'required' => true, 'autocomplete' => 'new-password',
                    'hint' => 'At least 12 characters, with letters and numbers.'])
                @include('partials.field', ['name' => 'password_confirmation', 'label' => 'Repeat new password', 'type' => 'password', 'value' => '', 'required' => true, 'autocomplete' => 'new-password'])
                <button type="submit" class="btn btn-primary">Save new password</button>
            </form>
        </div>
    </div>
@endsection
