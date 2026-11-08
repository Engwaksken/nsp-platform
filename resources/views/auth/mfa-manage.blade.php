@extends('layouts.public', ['pageTitle' => 'Account security'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>Account security</h1>
        @include('partials.flash')

        @if ($recoveryCodes)
            <section class="alert alert-warning" aria-labelledby="codes-heading">
                <h2 id="codes-heading">Save your recovery codes now</h2>
                <p>Each code works once if you lose your phone. They will not be shown again. Store them somewhere safe, such as a password manager.</p>
                <ul class="tags">
                    @foreach ($recoveryCodes as $code)<li><code>{{ $code }}</code></li>@endforeach
                </ul>
            </section>
        @endif

        <section class="panel">
            <h2>Two-factor sign-in is on</h2>
            <p>Since {{ $user->mfa_enabled_at->format('j M Y') }}. Recovery codes left: {{ $user->recoveryCodesLeft() }}.</p>
            @if (auth()->user()->canUseAdmin())<p><a class="btn btn-primary" href="{{ route('admin.dashboard') }}">Go to the admin area</a></p>@endif
        </section>

        @if ($user->password)
            <section class="panel">
                <h2>New recovery codes</h2>
                <form method="post" action="{{ route('mfa.recovery') }}">
                    @csrf
                    @include('partials.field', ['name' => 'password', 'id' => 'recovery-password', 'label' => 'Your password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'value' => ''])
                    <button type="submit" class="btn btn-secondary">Create new codes</button>
                </form>
            </section>
            @unless ($user->requiresMfa())
                <section class="panel">
                    <h2>Turn off</h2>
                    <form method="post" action="{{ route('mfa.disable') }}" data-confirm="Turn off two-factor sign-in?">
                        @csrf
                        @include('partials.field', ['name' => 'password', 'id' => 'disable-password', 'label' => 'Your password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'value' => ''])
                        <button type="submit" class="btn btn-danger">Turn off two-factor sign-in</button>
                    </form>
                </section>
            @endunless
        @endif
    </div>
@endsection
