@extends('layouts.public', ['pageTitle' => 'Create an account'])

@push('head')<meta name="robots" content="noindex">@endpush

@php
    // One tab per kind of member. Without JavaScript the tabs stack as separate sections.
    $icons = ['startup' => 'fa-rocket', 'investor' => 'fa-hand-holding-dollar', 'expert' => 'fa-chalkboard-user', 'talent' => 'fa-briefcase'];
    $tabNames = ['startup' => 'Startup', 'investor' => 'Investor', 'expert' => 'Expert', 'talent' => 'Job seeker'];
    $failed = old('purpose');
    $noErrors = new \Illuminate\Support\ViewErrorBag;
@endphp

@section('content')
    <div class="container">
        <div class="auth-card card">
            @include('partials.auth-brand')
            <h1>Create an account</h1>
            <p>One account for everything on NSP. Choose what brings you here; after you confirm your email and sign in, we take you to the right place to start.</p>

            @error('purpose')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

            @if (! $purposes)
                <form method="post" action="{{ route('register.store') }}" novalidate>
                    @csrf
                    @include('partials.field', ['name' => 'name', 'label' => 'Your full name', 'required' => true, 'autocomplete' => 'name', 'maxlength' => 255])
                    @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'maxlength' => 255])
                    @include('partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'value' => '', 'hint' => 'At least 8 characters, with letters and numbers.'])
                    @include('partials.field', ['name' => 'password_confirmation', 'label' => 'Repeat the password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'value' => ''])
                    <button type="submit" class="btn btn-primary">Create account</button>
                </form>
            @endif

            <div class="register-tabs" data-tabs="What brings you to NSP?" data-tabs-key="register">
                @foreach ($purposes as $key => [$label, $hint])
                    @php($fieldErrors = $failed === $key ? $errors : $noErrors)
                    <section id="{{ $key }}" data-tab="{{ $tabNames[$key] ?? $label }}" data-tab-icon="{{ $icons[$key] ?? 'fa-user' }}" aria-labelledby="{{ $key }}-heading">
                        <h2 id="{{ $key }}-heading">{{ $label }}</h2>
                        <p class="hint">{{ $hint }}</p>
                        <form method="post" action="{{ route('register.store') }}" novalidate>
                            @csrf
                            <input type="hidden" name="purpose" value="{{ $key }}">
                            @include('partials.field', ['errors' => $fieldErrors, 'id' => "$key-name", 'name' => 'name', 'label' => 'Your full name', 'required' => true, 'autocomplete' => 'name', 'maxlength' => 255])
                            @include('partials.field', ['errors' => $fieldErrors, 'id' => "$key-email", 'name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'maxlength' => 255,
                                'hint' => 'If someone invited you, use the address the invitation was sent to.'])
                            @include('partials.field', ['errors' => $fieldErrors, 'id' => "$key-password", 'name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'value' => '',
                                'hint' => 'At least 8 characters, with letters and numbers.'])
                            @include('partials.field', ['errors' => $fieldErrors, 'id' => "$key-password-confirmation", 'name' => 'password_confirmation', 'label' => 'Repeat the password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'value' => ''])
                            <button type="submit" class="btn btn-primary">Create {{ strtolower($tabNames[$key] ?? '') }} account</button>
                        </form>
                    </section>
                @endforeach
            </div>

            <p class="hint">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
        </div>
    </div>
@endsection
