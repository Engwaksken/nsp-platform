@extends('layouts.workspace', ['pageTitle' => 'Investor activity · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section">
        <p><a href="{{ route('workspace.capital.index', $startup) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Capital Exchange</a></p>
        <div class="page-head">
            <div>
                <h1>Investor activity · {{ $startup->display_name }}</h1>
                <p>What connected investors did with your information, and what your team shared. An investor's private pipeline and watchlist are not shown.</p>
            </div>
        </div>

        <div class="table-wrap" tabindex="0" role="region" aria-label="Investor activity">
            <table>
                <thead><tr><th scope="col">When</th><th scope="col">Investor</th><th scope="col">Activity</th><th scope="col">By</th><th scope="col">Details</th></tr></thead>
                <tbody>
                @forelse ($interactions as $interaction)
                    @php($meta = $interaction->meta ?? [])
                    <tr>
                        <td>{{ $interaction->created_at->format('j M Y H:i') }}</td>
                        <td>{{ $interaction->investor?->name ?? 'Former user' }}</td>
                        <td>{{ $interaction->typeLabel() }}</td>
                        <td>{{ $interaction->actor?->name ?? 'System' }}</td>
                        <td>
                            @if (! empty($meta['title'])){{ $meta['title'] }}
                            @elseif (! empty($meta['scopes'])){{ collect($meta['scopes'])->map(fn ($s) => config('capital.share_scopes.'.$s.'.label', $s))->join(', ') }}
                            @elseif (isset($meta['count'])){{ $meta['count'] }} item(s)
                            @elseif (isset($meta['to'])){{ config('capital.diligence_statuses.'.$meta['to'], $meta['to']) }}
                            @elseif (isset($meta['rating'])){{ \App\Models\Capital\MatchFeedback::RATINGS[$meta['rating']] ?? $meta['rating'] }}
                            @else<span class="meta">None</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No investor activity yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $interactions->links() }}
    </div>
@endsection
