@extends('layouts.public', ['pageTitle' => 'My workspace'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>Welcome, {{ $user->name }}</h1>
        <p>This is your NSP workspace. Services appear here as they become available.</p>

        @if ($registryMessage)
            <div class="alert alert-warning" role="status">{{ $registryMessage }}</div>
        @endif

        @if (is_array($startups))
            <section class="panel" aria-labelledby="startups-heading">
                <h2 id="startups-heading">Your startups</h2>
                @forelse ($startups as $startup)
                    <p><a href="{{ route('workspace.startups.show', $startup['uuid']) }}">{{ $startup['display_name'] }}</a> <span class="badge">{{ \App\Models\Registry\StartupMembership::ROLES[$startup['role']] ?? $startup['role'] }}</span></p>
                @empty
                    <p>You are not on a startup team yet. <a href="{{ route('workspace.startups.create') }}">Register your startup</a>, or ask your co-founder to invite you.</p>
                @endforelse
            </section>
        @endif

        <div class="grid">
            @foreach ($sections as $section)
                <section class="card" aria-labelledby="ws-{{ $loop->index }}">
                    <h2 id="ws-{{ $loop->index }}" class="h3">{{ $section['title'] }}</h2>
                    <p>{{ $section['text'] }}</p>
                    @if (!$section['available'])
                        <p><span class="badge">Not available yet</span></p>
                    @elseif ($section['key'] === 'registry')
                        <p><a class="btn btn-primary btn-sm" href="{{ route('workspace.startups.index') }}">Open my startups</a></p>
                    @elseif ($section['key'] === 'opportunities')
                        <p><a class="btn btn-primary btn-sm" href="{{ $active ? route('workspace.opportunities', $active->startup) : route('opportunities.index') }}">See opportunities</a></p>
                    @elseif ($section['key'] === 'vgi' && $active)
                        <p><a class="btn btn-primary btn-sm" href="{{ route('workspace.growth', $active->startup) }}">Open growth</a></p>
                    @elseif ($section['key'] === 'experts')
                        <p>@if ($active)<a class="btn btn-primary btn-sm" href="{{ route('workspace.experts', $active->startup) }}">Find an expert</a> @endif
                            <a class="btn btn-secondary btn-sm" href="{{ route('experts.dashboard') }}">I am an expert or mentor</a></p>
                    @elseif ($section['key'] === 'talent')
                        <p>@if ($active)<a class="btn btn-primary btn-sm" href="{{ route('workspace.talent', $active->startup) }}">Hire</a> @endif
                            <a class="btn btn-secondary btn-sm" href="{{ route('talent.dashboard') }}">Find a job</a></p>
                    @elseif ($section['key'] === 'funding')
                        <p>@if ($active)<a class="btn btn-primary btn-sm" href="{{ route('workspace.funding', $active->startup) }}">Open funding</a> @endif
                            <a class="btn btn-secondary btn-sm" href="{{ route('investor.dashboard') }}">I am an investor</a></p>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
@endsection
