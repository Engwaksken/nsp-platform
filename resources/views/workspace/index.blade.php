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
                    <p>{{ $startup['display_name'] }} <span class="badge">{{ $startup['role'] }}</span></p>
                @empty
                    <p>You are not a member of any startup yet.</p>
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
                    @endif
                </section>
            @endforeach
        </div>
    </div>
@endsection
