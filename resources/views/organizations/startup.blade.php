@extends('layouts.app', ['pageTitle' => $startup->display_name.' · '.$organization->name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>{{ $startup->display_name }}</h1>
        <p class="meta">Shared with {{ $organization->name }} for {{ strtolower($relationship->purposeLabel()) }}
            · {{ $relationship->expires_at ? 'until '.$relationship->expires_at->format('j M Y') : 'until the startup revokes it' }}</p>
        @include('organizations.partials.nav')
        <p class="hint">You see only what the startup chose to share. Do not copy or pass on this information beyond the purpose it was shared for.</p>

        @foreach ($sections as $scope => $rows)
            <section class="panel" aria-labelledby="scope-{{ $scope }}">
                <h2 id="scope-{{ $scope }}">{{ config('organizations.scopes.'.$scope.'.label') }}</h2>
                @if (empty($rows))
                    <p class="empty">Nothing to show yet.</p>
                @else
                    <dl>
                        @foreach ($rows as [$label, $value])
                            <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
                        @endforeach
                    </dl>
                    @if ($scope === 'vgi_summary')
                        <p class="hint">Growth scores use Hive’s current rubric, which is provisional until the official methodology is approved.</p>
                    @endif
                @endif
            </section>
        @endforeach
    </div>
@endsection
