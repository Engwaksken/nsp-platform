@extends('layouts.app', ['pageTitle' => $startup->display_name.' · '.$organization->name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>{{ $startup->display_name }}</h1>
        <p class="meta">Shared with {{ $organization->name }} for {{ strtolower($relationship->purposeLabel()) }}
            · {{ $relationship->expires_at ? 'until '.$relationship->expires_at->format('j M Y') : 'until the startup revokes it' }}</p>
        @include('organizations.partials.nav')
        <p class="hint">You see only what the startup chose to share. Do not copy or pass on this information beyond the purpose it was shared for.</p>

        {{-- One card per shared item. Open: reading them is the point of this page. --}}
        <x-card-grid label="Shared items" min="240px" remember="org-startup">
            @foreach ($sections as $scope => $rows)
                <x-collapsible-card id="scope-{{ $scope }}" :level="2" :title="config('organizations.scopes.'.$scope.'.label')"
                    :summary="empty($rows) ? 'Nothing to show yet' : trans_choice(':count item|:count items', count($rows))" :open="true">
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
                </x-collapsible-card>
            @endforeach
        </x-card-grid>
                    </dl>
                    @if ($scope === 'vgi_summary')
                        <p class="hint">Growth scores use Hive’s current rubric, which is provisional until the official methodology is approved.</p>
                    @endif
                @endif
            </section>
        @endforeach
    </div>
@endsection
