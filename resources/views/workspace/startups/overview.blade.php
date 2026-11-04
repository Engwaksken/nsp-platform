@extends('layouts.public', ['pageTitle' => 'Overview · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>{{ $startup->display_name }} at a glance</h1>
        @include('workspace.startups.nav')
        <div class="grid">
            @foreach ($cards as [$label, $value, $url])
                <a class="card card-link" href="{{ $url }}">
                    <span class="meta">{{ $label }}</span>
                    <span class="kpi-value">{{ is_numeric($value) ? number_format($value) : $value }}</span>
                </a>
            @endforeach
        </div>
        @if ($membership->canEdit())
            <section class="panel" aria-labelledby="export-heading">
                <h2 id="export-heading" class="h3">Your data</h2>
                <p>Download everything your startup entered on NSP (profile, team, sharing choices, growth, funding requests, vacancies and listings) as a JSON file. It does not include other people’s private data such as candidates’ CVs or private notes. Downloads are recorded.</p>
                <p><a class="btn btn-secondary" href="{{ route('workspace.startups.export', $startup) }}">Download our data</a></p>
            </section>
        @endif
    </div>
@endsection
