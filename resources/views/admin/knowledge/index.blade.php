@extends('layouts.admin')
@section('title', 'Knowledge Hub resources')

@section('content')
    <div class="page-head"><h1>Knowledge Hub</h1>@can('manage-knowledge')<a class="btn btn-primary" href="{{ route('admin.knowledge.create') }}" data-modal="lg">New resource</a>@endcan</div>
    @include('partials.stats', ['label' => 'Summary', 'stats' => \App\Support\AdminStats::knowledge()])
    @include('partials.flash')
    <nav class="filters" aria-label="Filter resources by status">
        <a class="tag" href="{{ route('admin.knowledge.index') }}">All</a>
        @foreach (\App\Models\Knowledge\KnowledgeResource::STATUSES as $key => $label)
            <a class="tag" href="{{ route('admin.knowledge.index', ['status' => $key]) }}" @if ($status === $key) aria-current="true" @endif>{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
        @endforeach
        <a class="tag" href="{{ route('admin.knowledge.index', ['due' => 1]) }}">Review due</a>
        @can('manage-knowledge')<a class="tag" href="{{ route('admin.knowledge.categories.index') }}">Categories</a>@endcan
    </nav>
    <form method="get" class="filters" role="search" aria-label="Search editorial resources">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <label for="knowledge-admin-q">Title</label><input id="knowledge-admin-q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search resources by title…">
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Knowledge resource records">
        <table><thead><tr><th>Resource</th><th>Status</th><th>Visibility</th><th>Version</th><th>Review due</th><th>Editor</th></tr></thead><tbody>
            @forelse ($resources as $resource)
                <tr><td><a href="{{ route('admin.knowledge.edit', $resource->uuid) }}" data-modal="lg">{{ $resource->title }}</a><span class="meta">{{ $resource->source ?: 'Source not recorded' }}</span></td>
                    <td>{{ \Illuminate\Support\Str::headline($resource->status) }}</td><td>{{ $resource->visibilityLabel() }}</td>
                    <td>@if ($resource->current_version){{ $resource->current_version }}@else<span class="meta">None</span>@endif</td><td>@if ($resource->review_due_at){{ $resource->review_due_at->format('j M Y') }}@else<span class="meta">Not set</span>@endif</td>
                    <td>@if ($resource->uploader){{ $resource->uploader->name }}@else<span class="meta">Unknown</span>@endif</td></tr>
            @empty<tr><td colspan="6" class="empty">No resources match this queue.</td></tr>@endforelse
        </tbody></table>
    </div>
    {{ $resources->links() }}
@endsection
