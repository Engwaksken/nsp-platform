@extends('layouts.app', ['pageTitle' => 'My capital pipeline'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    @php
        $total = array_sum($funnel);
        $peak = max(1, max($funnel ?: [0]));
        $stageIcon = ['sourced' => 'fa-magnifying-glass', 'screened' => 'fa-filter', 'contacted' => 'fa-envelope', 'diligence' => 'fa-list-check',
            'decision' => 'fa-scale-balanced', 'committed' => 'fa-handshake', 'closed' => 'fa-flag-checkered', 'declined' => 'fa-circle-xmark'];
        $link = fn (\App\Models\Registry\Startup $startup) => ($visible[$startup->uuid] ?? false)
            ? '<a href="'.e(route('investor.startup', $startup)).'">'.e($startup->display_name).'</a>'
            : e($startup->display_name).' <span class="meta">(no longer open to investors)</span>';
    @endphp
    <div class="container section">
        <p><a href="{{ route('investor.dashboard') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Investor dashboard</a></p>
        <div class="page-head">
            <div>
                <h1>My capital pipeline</h1>
                <p>Your stages, watchlist and history are private. Startups never see them.</p>
            </div>
        </div>
        @include('partials.flash')

        <section class="panel" aria-labelledby="funnel-heading">
            <h2 id="funnel-heading">Funnel <span class="badge">{{ $total }} startup(s)</span></h2>
            <ol class="capital-funnel">
                @foreach ($stages as $key => $label)
                    <li>
                        <span class="meter-label"><span><i class="fas {{ $stageIcon[$key] ?? 'fa-circle' }}" aria-hidden="true"></i> {{ $label }}</span><strong>{{ $funnel[$key] ?? 0 }}</strong></span>
                        <span class="meter" aria-hidden="true"><span style="width: {{ (int) round(($funnel[$key] ?? 0) / $peak * 100) }}%"></span></span>
                    </li>
                @endforeach
            </ol>
        </section>

        <div data-tabs="Pipeline sections" data-tabs-key="investor-capital">
        <section class="panel" aria-labelledby="stages-heading" data-tab="Stages" data-tab-icon="fa-table-columns" data-tab-count="{{ $total }}">
            <h2 id="stages-heading">Startups by stage</h2>
            @if ($total === 0)
                <p class="empty">Your pipeline is empty. Open a startup from your <a href="{{ route('investor.dashboard') }}">dashboard</a> and choose a stage.</p>
            @else
                <x-card-grid label="Pipeline stages" min="240px" remember="investor-pipeline">
                    @foreach ($stages as $key => $label)
                        @php($atStage = $items[$key] ?? collect())
                        <x-collapsible-card id="stage-{{ $key }}" icon="{{ $stageIcon[$key] ?? 'fa-circle' }}" class="tone-blue" :title="$label"
                            :badge="(string) $atStage->count()" :badge-tone="$atStage->isNotEmpty() ? 'scheduled' : 'off'" :open="$atStage->isNotEmpty()">
                            @forelse ($atStage as $item)
                                <article class="card capital-item">
                                    <h4 class="capital-item-title">{!! $link($item->startup) !!}</h4>
                                    <p class="meta">Since {{ $item->stage_changed_at?->format('j M Y') ?? 'Not set' }}
                                        @if (in_array($item->startup_uuid, $connected, true)) · <a href="{{ route('investor.capital.diligence', $item->startup) }}">Due diligence</a>@endif</p>
                                    <form method="post" action="{{ route('investor.capital.transition', $item->startup) }}" class="capital-move">
                                        @csrf
                                        <label class="visually-hidden" for="move-{{ $item->id }}">Move {{ $item->startup->display_name }} to</label>
                                        <select id="move-{{ $item->id }}" name="stage">
                                            @foreach ($stages as $value => $stageLabel)<option value="{{ $value }}" @selected($value === $item->stage)>{{ $stageLabel }}</option>@endforeach
                                        </select>
                                        <button type="submit" class="btn btn-secondary btn-sm">Move<span class="visually-hidden"> {{ $item->startup->display_name }}</span></button>
                                    </form>
                                </article>
                            @empty
                                <p class="empty">None.</p>
                            @endforelse
                        </x-collapsible-card>
                    @endforeach
                </x-card-grid>
            @endif
        </section>

        <section class="panel" aria-labelledby="watch-heading" data-tab="Watchlist" data-tab-icon="fa-eye" data-tab-count="{{ $watchlist->count() }}">
            <h2 id="watch-heading">Watchlist</h2>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Watchlist">
                <table>
                    <thead><tr><th scope="col">Startup</th><th scope="col">Pipeline stage</th><th scope="col">Added</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    @forelse ($watchlist as $entry)
                        @php($trackedItem = $tracked[$entry->startup_uuid] ?? null)
                        <tr>
                            <td>{!! $link($entry->startup) !!}</td>
                            <td>{{ $trackedItem?->stageLabel() ?? 'Not in pipeline' }}</td>
                            <td>{{ $entry->created_at->format('j M Y') }}</td>
                            <td class="capital-row-actions">
                                @if (! $trackedItem && ($visible[$entry->startup_uuid] ?? false))
                                    <form method="post" action="{{ route('investor.capital.transition', $entry->startup) }}" class="inline-form">
                                        @csrf <input type="hidden" name="stage" value="{{ array_key_first($stages) }}">
                                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-plus" aria-hidden="true"></i> Add to pipeline<span class="visually-hidden"> {{ $entry->startup->display_name }}</span></button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('investor.capital.unwatch', $entry->startup) }}" class="inline-form">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-eye-slash" aria-hidden="true"></i> Remove<span class="visually-hidden"> {{ $entry->startup->display_name }}</span></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Nothing on your watchlist. Use "Watch" on a startup page.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" aria-labelledby="history-heading" data-tab="History" data-tab-icon="fa-clock-rotate-left">
            <h2 id="history-heading">Stage history</h2>
            <p class="hint">Every move is kept and cannot be changed. The latest 25 are shown.</p>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Stage history">
                <table>
                    <thead><tr><th scope="col">When</th><th scope="col">Startup</th><th scope="col">From</th><th scope="col">To</th></tr></thead>
                    <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>{{ $event->created_at->format('j M Y H:i') }}</td>
                            <td>{{ $names[$event->startup_uuid] ?? 'Startup no longer available' }}</td>
                            <td>{{ $event->from_stage ? ($stages[$event->from_stage] ?? $event->from_stage) : 'New' }}</td>
                            <td>{{ $stages[$event->to_stage] ?? $event->to_stage }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No moves yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        </div>
    </div>
@endsection
