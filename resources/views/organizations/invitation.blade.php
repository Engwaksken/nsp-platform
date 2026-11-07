@extends('layouts.public', ['pageTitle' => 'Organization invitation'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>Organization invitation</h1>
        @if (!$valid)
            <p>This invitation is no longer valid. Ask the organization to send a new one.</p>
        @elseif (!$emailMatches)
            <p>This invitation was sent to a different email address. Sign in with that address to accept it.</p>
        @else
            <p>{{ $invite->inviter?->name ?? 'Someone' }} invited you to join <strong>{{ $invite->organization->name }}</strong> ({{ $invite->organization->typeLabel() }}) as <strong>{{ $invite->roleLabel() }}</strong>.</p>
            <div class="actions">
                <form method="post" action="{{ route('organizations.invitations.accept', $token) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Accept and join</button>
                </form>
                <form method="post" action="{{ route('organizations.invitations.decline', $token) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Decline</button>
                </form>
            </div>
        @endif
    </div>
@endsection
