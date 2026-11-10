@extends('layouts.admin')
@section('title', 'Marketplace and disputes')

@section('content')
    <div class="page-head"><h1>Marketplace and disputes</h1></div>
    @include('partials.flash')

    @php($look = ['Published listings' => ['fa-store', 'green'], 'Offers' => ['fa-box-open', 'blue'], 'Requests to buy' => ['fa-cart-shopping', 'purple'],
        'Partnerships' => ['fa-handshake', 'orange'], 'Conversations (30 days)' => ['fa-comments', 'blue'], 'Open reports' => ['fa-flag', 'red']])
    @include('partials.stats', ['label' => 'Summary', 'stats' => collect($figures)->map(fn ($value, $label) => [
        'icon' => $look[$label][0] ?? 'fa-chart-simple', 'tone' => $look[$label][1] ?? 'grey', 'label' => $label, 'value' => $value,
    ])->values()->all()])

    <h2>Reports</h2>
    <nav class="tags" aria-label="Filter reports">
        @foreach (\App\Models\Marketplace\Report::STATUSES as $value => $label)
            <a class="tag" href="{{ route('admin.marketplace.index', ['status' => $value]) }}" @if ($status === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Reports">
        <table>
            <thead><tr><th scope="col">Report</th><th scope="col">About</th><th scope="col">Reason</th><th scope="col">By</th><th scope="col">Date</th></tr></thead>
            <tbody>
            @forelse ($reports as $report)
                <tr>
                    <td><a href="{{ route('admin.marketplace.report', $report) }}">#{{ $report->id }}</a></td>
                    <td>{{ $report->inquiry_uuid ? 'Conversation about ' : 'Listing ' }}“{{ $report->listing?->title ?? 'deleted' }}” · {{ $report->listing?->startup?->display_name }}</td>
                    <td>{{ \App\Models\Marketplace\Report::REASONS[$report->reason] ?? $report->reason }}</td>
                    <td>{{ $report->reporter?->name ?? '—' }}</td>
                    <td>{{ $report->created_at->format('j M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No reports with this status.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $reports->links() }}
@endsection
