@extends('layouts.app', ['pageTitle' => 'Readiness · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    @php($scopeLabels = collect(\App\Capital\CapitalService::scopes())->map(fn ($s) => $s['label']))
    <div class="container section narrow">
        <p><a href="{{ route('investor.startup', $startup) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ $startup->display_name }}</a></p>
        <div class="page-head">
            <div>
                <h1>Investment readiness · {{ $startup->display_name }}</h1>
                <p>Shared with you by the founders until {{ $share->expires_at?->format('j F Y') ?? 'they stop sharing' }}. Treat it as confidential.</p>
            </div>
            <div class="capital-actions">
                <a class="btn btn-secondary" href="{{ route('investor.capital.diligence', $startup) }}"><i class="fas fa-list-check" aria-hidden="true"></i> Due diligence</a>
                <a class="btn btn-secondary" href="{{ route('investor.capital.readiness', [$startup, 'print' => 1]) }}"><i class="fas fa-print" aria-hidden="true"></i> Printable report</a>
            </div>
        </div>
        @include('partials.flash')
        <p class="meta">Shared with you: {{ collect($scopes)->map(fn ($s) => $scopeLabels[$s] ?? $s)->join(', ') }}.</p>

        @include('capital.report')
    </div>
@endsection
