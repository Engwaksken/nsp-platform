@extends('layouts.admin')
@section('title', 'Report #'.$report->id)

@section('content')
    <div class="page-head"><h1>Report #{{ $report->id }}</h1><a class="btn btn-secondary" href="{{ route('admin.marketplace.index') }}">All reports</a></div>
    @include('partials.flash')
    <p class="meta">{{ \App\Models\Marketplace\Report::REASONS[$report->reason] ?? $report->reason }} · reported by {{ $report->reporter?->name ?? 'an unknown user' }} ({{ $report->reporter?->email }}) on {{ $report->created_at->format('j M Y H:i') }}
        · <span class="badge">{{ \App\Models\Marketplace\Report::STATUSES[$report->status] }}</span></p>
    @if ($report->details)<p>{!! nl2br(e($report->details)) !!}</p>@endif

    @if ($report->listing)
        <section class="panel" aria-labelledby="listing-heading">
            <h2 id="listing-heading" class="h3">Listing: {{ $report->listing->title }} <span class="badge">{{ \App\Models\Marketplace\Listing::STATUSES[$report->listing->status] }}</span></h2>
            <p class="meta">{{ $report->listing->startup?->display_name }} · {{ \App\Models\Marketplace\Listing::KINDS[$report->listing->kind] }}</p>
            <p>{{ $report->listing->summary }}</p>
            <p>{!! nl2br(e($report->listing->description)) !!}</p>
            @if ($report->listing->status === 'removed')
                <form method="post" action="{{ route('admin.marketplace.restore', $report->listing) }}">@csrf <button type="submit" class="btn btn-secondary btn-sm">Restore listing as draft</button></form>
            @endif
        </section>
    @endif

    @if ($report->inquiry)
        <section class="panel" aria-labelledby="conv-heading">
            <h2 id="conv-heading" class="h3">Conversation (shared with Hive by the report)</h2>
            <p class="meta">Between {{ $report->inquiry->inquirer?->name }} and {{ $report->listing?->startup?->display_name }}. Opening this page is recorded in the audit log.</p>
            <ol class="plain-list">
                @foreach ($report->inquiry->messages as $message)
                    <li class="card"><p class="meta">{{ $message->sender?->name ?? 'former user' }} ({{ $message->side === 'team' ? 'startup' : 'inquirer' }}) · {{ $message->created_at->format('j M Y H:i') }}</p>
                        <p>{!! nl2br(e($message->body)) !!}</p></li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($report->status === 'open')
        <section class="panel" aria-labelledby="resolve-heading">
            <h2 id="resolve-heading" class="h3">Decision</h2>
            <form method="post" action="{{ route('admin.marketplace.resolve', $report) }}">
                @csrf
                @include('partials.field', ['name' => 'resolution_note', 'label' => 'Note (sent to the reporter; also shown to the startup if you take the listing down)', 'type' => 'textarea', 'rows' => 3, 'required' => true])
                @if ($report->listing && $report->listing->status !== 'removed')
                    <div class="form-group"><label class="checkbox"><input type="checkbox" name="remove_listing" value="1"> <span>Take the listing down</span></label></div>
                @endif
                <div class="inline-form">
                    <button type="submit" name="status" value="resolved" class="btn btn-primary">Resolve</button>
                    <button type="submit" name="status" value="dismissed" class="btn btn-secondary">Dismiss</button>
                </div>
            </form>
        </section>
    @else
        <p>{{ \App\Models\Marketplace\Report::STATUSES[$report->status] }} by {{ $report->resolver?->name ?? 'an unknown user' }} on {{ $report->resolved_at?->format('j M Y') }}: {{ $report->resolution_note }}</p>
    @endif
@endsection
