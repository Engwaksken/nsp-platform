@extends('layouts.app', ['pageTitle' => 'Investor dashboard'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>Investor dashboard</h1>
        @include('partials.flash')
        <p class="hint">You see startups that have an open funding request and chose to be shown to verified investors. Treat what you see as confidential. <a href="{{ route('investor.apply') }}">Edit my investor profile</a></p>

        @if ($incoming->isNotEmpty())
            <section class="panel" aria-labelledby="incoming-heading">
                <h2 id="incoming-heading">Introduction requests</h2>
                {{-- Each request waits for your answer, so the cards start open. --}}
                <x-card-grid label="Introduction requests" min="260px" remember="investor-incoming">
                    @foreach ($incoming as $introduction)
                        <x-collapsible-card id="incoming-{{ $introduction->id }}" icon="fa-handshake" class="tone-green"
                            :title="$introduction->startup?->display_name ?? 'Startup'" badge="Waiting for you" badge-tone="draft"
                            :summary="'Asked on '.$introduction->created_at->format('j M Y')" :open="true">
                            @if ($introduction->message)<p>{{ $introduction->message }}</p>@endif
                            <p><a href="{{ route('investor.startup', $introduction->startup_uuid) }}">View {{ $introduction->startup?->display_name }}</a></p>
                            <form method="post" action="{{ route('investor.decide', $introduction) }}" class="inline-form">
                                @csrf
                                <button type="submit" name="decision" value="accept" class="btn btn-primary btn-sm">Accept and share contacts<span class="visually-hidden"> with {{ $introduction->startup?->display_name }}</span></button>
                                <button type="submit" name="decision" value="decline" class="btn btn-secondary btn-sm">Decline<span class="visually-hidden"> {{ $introduction->startup?->display_name }}</span></button>
                            </form>
                        </x-collapsible-card>
                    @endforeach
                </x-card-grid>
            </section>
        @endif

        <section class="panel" aria-labelledby="pipeline-heading">
            <h2 id="pipeline-heading">My pipeline</h2>
            <p class="hint">Your stages and notes are private. Startups never see them.</p>
            <div class="table-wrap" tabindex="0" role="region" aria-label="My pipeline">
                <table>
                    <thead><tr><th scope="col">Startup</th><th scope="col">Introduction</th><th scope="col">My stage</th><th scope="col">Data room</th></tr></thead>
                    <tbody>
                    @forelse ($pipeline as $introduction)
                        <tr>
                            <td><a href="{{ route('investor.startup', $introduction->startup_uuid) }}">{{ $introduction->startup?->display_name }}</a></td>
                            <td>{{ \App\Models\Funding\Introduction::STATUSES[$introduction->status] }}</td>
                            <td>{{ \App\Models\Funding\Introduction::STAGES[$introduction->investor_stage] ?? 'Not set' }}</td>
                            <td>@php($grant = $grants->firstWhere('startup_uuid', $introduction->startup_uuid))@if ($grant)Until {{ $grant->expires_at->format('j M Y') }}@else<span class="meta">No access</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Nothing yet. Express interest in a startup below.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="startups-heading">
            <h2 id="startups-heading">Startups raising now</h2>
            <nav class="tags" aria-label="Filter startups">
                <a class="tag" href="{{ route('investor.dashboard') }}" @if ($filter !== 'all') aria-current="true" @endif>Fits my focus</a>
                <a class="tag" href="{{ route('investor.dashboard', ['show' => 'all']) }}" @if ($filter === 'all') aria-current="true" @endif>All</a>
            </nav>
            <div class="card-grid">
                @forelse ($startups as $row)
                    <article class="card">
                        <h3 class="h4"><a href="{{ route('investor.startup', $row['startup']) }}">{{ $row['startup']->display_name }}</a></h3>
                        <p class="meta">{{ $row['startup']->profile?->sector ?: 'Sector not given' }} · {{ \App\Models\Registry\Startup::COUNTRIES[$row['startup']->country] ?? $row['startup']->country }}</p>
                        <p><strong>{{ $row['request']->amountLabel() }}</strong></p>
                        <p>@foreach ($row['reasons'] as $reason)<span class="tag">{{ $reason }}</span>@endforeach</p>
                    </article>
                @empty
                    <p class="empty">{{ $filter === 'all' ? 'No startups are raising on NSP right now.' : 'No startups match your focus right now. Try “All”.' }}</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
