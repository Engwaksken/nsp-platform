@extends('layouts.public', ['pageTitle' => 'Invitation'])

@push('head')<meta name="robots" content="noindex"><meta name="referrer" content="no-referrer">@endpush

@section('content')
    <div class="container">
        <div class="auth-card card">
            @if (!$valid)
                <h1>Invitation not valid</h1>
                <p>This invitation has expired, was cancelled or was already used. Ask the team to send a new one.</p>
                <a class="btn btn-secondary" href="{{ route('workspace.startups.index') }}">My startups</a>
            @else
                <h1>Join {{ $invite->startup->display_name }}</h1>
                <p>{{ $invite->inviter?->name ?? 'A team member' }} invited you as <strong>{{ \App\Models\Registry\StartupMembership::ROLES[$invite->role] }}</strong>.</p>
                @if (!$emailMatches)
                    <div class="alert alert-warning" role="alert">This invitation was sent to a different email address than the one you are signed in with. Sign out and sign in with the invited address to accept it.</div>
                @else
                    <div class="actions">
                        <form method="post" action="{{ route('invitations.accept', $token) }}">@csrf<button type="submit" class="btn btn-primary">Accept and join</button></form>
                        <form method="post" action="{{ route('invitations.decline', $token) }}">@csrf<button type="submit" class="btn btn-secondary">Decline</button></form>
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection
