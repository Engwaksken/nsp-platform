@extends('layouts.public', ['pageTitle' => 'My startups'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <div class="page-head">
            <h1>My startups</h1>
            <a class="btn btn-primary" href="{{ route('workspace.startups.create') }}">Register a startup</a>
        </div>
        @include('partials.flash')

        @if ($invitations->isNotEmpty())
            <section class="panel" aria-labelledby="invites-heading">
                <h2 id="invites-heading">Invitations for you</h2>
                @foreach ($invitations as $invite)
                    <p><strong>{{ $invite->startup->display_name }}</strong>: {{ $invite->inviter?->name ?? 'A team member' }} invited you as {{ \App\Models\Registry\StartupMembership::ROLES[$invite->role] }}.</p>
                    <p class="hint">Open the link in your invitation email to accept it.</p>
                @endforeach
            </section>
        @endif

        @if ($memberships->isEmpty())
            <div class="card">
                <h2>No startup yet</h2>
                <p>Register your startup once. The same profile is used across Hive programs and NSP services, so you never have to type it in twice.</p>
                <p>If your co-founder already registered it, ask them to invite you from their Team page instead of registering again.</p>
                <a class="btn btn-primary" href="{{ route('workspace.startups.create') }}">Register a startup</a>
            </div>
        @else
            <div class="grid">
                @foreach ($memberships as $membership)
                    @php($startup = $membership->startup)
                    <section class="card" aria-labelledby="startup-{{ $startup->uuid }}">
                        <h2 id="startup-{{ $startup->uuid }}" class="h3">{{ $startup->display_name }}
                            @if ($active?->startup_uuid === $startup->uuid)<span class="badge badge-on">Current</span>@endif
                        </h2>
                        <p class="meta">{{ \App\Models\Registry\StartupMembership::ROLES[$membership->role] }} · Profile {{ $startup->profile?->profile_completion ?? 0 }}% complete</p>
                        <div class="actions">
                            <a class="btn btn-secondary btn-sm" href="{{ route('workspace.startups.overview', $startup) }}">Open<span class="visually-hidden"> {{ $startup->display_name }}</span></a>
                            @if ($active?->startup_uuid !== $startup->uuid)
                                <form method="post" action="{{ route('workspace.startups.switch', $startup) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Work on this startup<span class="visually-hidden">: {{ $startup->display_name }}</span></button>
                                </form>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        @if ($requests->isNotEmpty())
            <section class="panel" aria-labelledby="requests-heading" style="margin-top:1.5rem">
                <h2 id="requests-heading">Waiting for approval</h2>
                <ul>
                    @foreach ($requests as $request)
                        <li>You asked to join <strong>{{ $request->startup->display_name }}</strong> on {{ $request->created_at->format('j M Y') }}.</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
