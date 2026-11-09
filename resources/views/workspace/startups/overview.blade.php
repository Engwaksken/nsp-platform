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

        @php
            $look = [
                'Profile complete' => ['fa-id-card', 'blue'], 'Team members' => ['fa-users', 'purple'],
                'Growth stage' => ['fa-seedling', 'green'], 'Open growth actions' => ['fa-list-check', 'green'],
                'Funding request' => ['fa-sack-dollar', 'amber'], 'Investor introductions' => ['fa-handshake', 'amber'],
                'Active expert engagements' => ['fa-user-tie', 'blue'], 'Open vacancies' => ['fa-briefcase', 'orange'],
                'New job applications' => ['fa-file-lines', 'orange'], 'Unread marketplace messages' => ['fa-envelope', 'red'],
                'Hive programs' => ['fa-graduation-cap', 'purple'],
            ];
        @endphp
        @include('partials.stats', ['label' => 'Key figures', 'stats' => array_map(fn ($card) => [
            'icon' => $look[$card[0]][0] ?? 'fa-chart-simple', 'tone' => $look[$card[0]][1] ?? 'grey',
            'label' => $card[0], 'value' => $card[1], 'url' => $card[2],
        ], $cards)])

        @if ($membership->canEdit())
            <section class="panel" aria-labelledby="export-heading">
                <h2 id="export-heading" class="h3">Your data</h2>
                <p>Download everything your startup entered on NSP (profile, team, sharing choices, growth, funding requests, vacancies and listings) as a JSON file. It does not include other people’s private data such as candidates’ CVs or private notes. Downloads are recorded.</p>
                <p><a class="btn btn-secondary" href="{{ route('workspace.startups.export', $startup) }}">Download our data</a></p>
            </section>
        @endif
    </div>
@endsection
