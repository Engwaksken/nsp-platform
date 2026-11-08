@extends('layouts.public', ['pageTitle' => 'Unsubscribe'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>Email choices</h1>
        @if ($done)
            <div class="alert alert-success" role="status">Done. You will no longer get “{{ $label }}” emails. You can still see these updates on NSP.</div>
        @else
            <p>Stop emails about “{{ $label }}”? Security emails are not affected.</p>
            <form method="post" action="{{ $action }}">
                @csrf
                <button type="submit" class="btn btn-primary">Stop these emails</button>
            </form>
        @endif
        <p>@auth<a href="{{ route('notifications.preferences') }}">Change all your choices</a>@else<a href="{{ route('login') }}">Sign in</a> to change all your choices.@endauth</p>
    </div>
@endsection
