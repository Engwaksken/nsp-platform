@extends('layouts.admin')
@section('title', 'Challenge Exchange moderation')

@section('content')
    <div class="page-head">
        <h1>Challenge Exchange</h1>
        @can('manage-challenges')<a class="btn btn-primary" href="{{ route('admin.challenges.create') }}" data-modal="lg">New challenge</a>@endcan
    </div>
    @include('partials.flash')
    <nav class="filters" aria-label="Filter challenge records">
        @foreach (['submitted' => 'Submitted', 'under_review' => 'Under review', 'changes_requested' => 'Changes requested', 'approved' => 'Approved', 'live' => 'Published', 'draft' => 'Drafts', 'rejected' => 'Rejected', 'archived' => 'Archived'] as $key => $label)
            <a class="tag" href="{{ route('admin.challenges.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="true" @endif>{{ $label }} ({{ $counts[$key] ?? ($key === 'live' ? ($counts['published'] ?? 0) : 0) }})</a>
        @endforeach
    </nav>
    <form method="get" class="filters" role="search" aria-label="Search challenges">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label for="challenge-q">Title contains</label><input id="challenge-q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search challenges by title…">
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Challenge moderation queue">
        <table><thead><tr><th>Challenge</th><th>Status</th><th>Submitter</th><th>Published version</th><th>Actions</th></tr></thead><tbody>
            @forelse ($challenges as $challenge)
                <tr><td><a href="{{ route('admin.challenges.show', $challenge) }}">{{ $challenge->title }}</a></td>
                    <td>{{ \Illuminate\Support\Str::headline($challenge->status) }}</td><td>{{ $challenge->submitter?->name ?? 'Hive staff' }}</td>
                    <td>{{ $challenge->publishedVersion?->version ? 'v'.$challenge->publishedVersion->version : '—' }}</td>
                    <td class="actions"><a class="btn btn-secondary btn-sm" href="{{ route('admin.challenges.show', $challenge) }}">Review</a>
                        @can('manage-challenges')<a class="btn btn-secondary btn-sm" href="{{ route('admin.challenges.edit', $challenge) }}" data-modal="lg">Edit</a>@endcan
                    </td></tr>
            @empty<tr><td colspan="5" class="empty">No challenges in this queue.</td></tr>@endforelse
        </tbody></table>
    </div>
    {{ $challenges->links() }}
@endsection
