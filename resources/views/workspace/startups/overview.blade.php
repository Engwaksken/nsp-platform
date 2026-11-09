@extends('layouts.workspace', ['pageTitle' => 'Dashboard · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section">
        <div class="dash-greeting">
            <div>
                <p class="meta">Dashboard</p>
                <h1>{{ $startup->display_name }} at a glance</h1>
            </div>
            @if ($membership->canEdit())
                <a class="btn btn-secondary btn-sm" href="{{ route('workspace.startups.edit', $startup) }}">Edit profile</a>
            @endif
        </div>
        @include('partials.flash')

        @if ($next)
            <section aria-labelledby="next-heading" class="panel">
                <h2 id="next-heading" class="h3">Next steps</h2>
                <ul class="dash-next">
                    @foreach ($next as [$text, $action, $url])
                        <li><span>{{ $text }}</span> <a class="btn btn-primary btn-sm" href="{{ $url }}">{{ $action }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <h2 class="visually-hidden">Key figures</h2>
        <div class="dash-cards">
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
