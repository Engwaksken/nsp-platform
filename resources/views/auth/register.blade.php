@extends('layouts.public', ['pageTitle' => 'Create an account'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container">
        <div class="auth-card card">
            <h1>Create an account</h1>
            <p>One account for everything on NSP. After you confirm your email and sign in, we take you to the right place to start.</p>

            <form method="post" action="{{ route('register.store') }}" novalidate>
                @csrf
                @if ($purposes)
                    @php($current = old('purpose', $selected))
                    <fieldset class="form-group" @error('purpose') aria-describedby="purpose-error" aria-invalid="true" @enderror>
                        <legend class="required">What brings you to NSP?</legend>
                        @foreach ($purposes as $key => [$label, $hint])
                            <label class="checkbox">
                                <input type="radio" name="purpose" value="{{ $key }}" @checked($current === $key) required>
                                <span><strong>{{ $label }}</strong><br><span class="hint">{{ $hint }}</span></span>
                            </label>
                        @endforeach
                        @error('purpose')<p class="error" id="purpose-error">{{ $message }}</p>@enderror
                    </fieldset>
                @endif
                @include('partials.field', ['name' => 'name', 'label' => 'Your full name', 'required' => true, 'autocomplete' => 'name', 'maxlength' => 255])
                @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'maxlength' => 255,
                    'hint' => 'If someone invited you, use the address the invitation was sent to.'])
                @include('partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'value' => '',
                    'hint' => 'At least 12 characters, with letters and numbers.'])
                @include('partials.field', ['name' => 'password_confirmation', 'label' => 'Repeat the password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'value' => ''])
                <button type="submit" class="btn btn-primary">Create account</button>
            </form>

            <p class="hint">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
        </div>
    </div>
@endsection
