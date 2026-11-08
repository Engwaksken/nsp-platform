@extends('layouts.admin')
@section('title', 'Opportunities')

@section('content')
    <div class="page-head">
        <h1>Opportunities</h1>
        <a class="btn btn-primary" href="{{ route('admin.opportunities.create') }}" data-modal="lg">New opportunity</a>
    </div>
    <nav class="filters" aria-label="Filter opportunities">
        @foreach (['published' => 'Open', 'pending_review' => 'To review ('.$pendingCount.')', 'draft' => 'Drafts', 'closed' => 'Closed', 'rejected' => 'Rejected', 'archived' => 'Archived'] as $value => $label)
            <a class="tag" href="{{ route('admin.opportunities.index', ['tab' => $value]) }}" @if ($tab === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Opportunities">
        <table>
            <thead><tr><th scope="col">Title</th><th scope="col">Type</th><th scope="col">Deadline / date</th><th scope="col">Registrations</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse ($opportunities as $opportunity)
                <tr>
                    <td><a href="{{ route('admin.opportunities.edit', $opportunity) }}" data-modal="lg">{{ $opportunity->title }}</a>
                        @if ($opportunity->submitted_by)<br><span class="meta">Shared by a user</span>@endif</td>
                    <td>{{ \App\Models\Ecosystem\Opportunity::TYPES[$opportunity->type] }}</td>
                    <td>{{ ($opportunity->deadline_at ?? $opportunity->starts_at)?->format('j M Y') ?? '—' }}</td>
                    <td>@if ($opportunity->accepts_interest)<a href="{{ route('admin.opportunities.interests', $opportunity) }}">{{ $opportunity->interests_count }}</a>@else — @endif</td>
                    <td class="actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('opportunities.show', $opportunity) }}" target="_blank" rel="noopener">View</a>
                        @if ($opportunity->status === 'pending_review')<a class="btn btn-primary btn-sm" href="{{ route('admin.opportunities.edit', $opportunity) }}" data-modal="lg">Review</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Nothing here.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $opportunities->links() }}
@endsection
