@extends('layouts.admin')
@section('title', 'Revisions')

@section('breadcrumbs')
    <li><a href="{{ route('admin.pages.index') }}">Pages</a></li>
    <li><a href="{{ route('admin.pages.edit', $page) }}">{{ $page->title }}</a></li>
    <li aria-current="page">Revisions</li>
@endsection

@section('content')
    <div class="page-head"><h1>Revisions: {{ $page->title }}</h1></div>
    <p class="hint">Restoring a revision copies its content back and sets the page to draft for review.</p>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
        <table>
            <thead><tr><th scope="col">#</th><th scope="col">Saved</th><th scope="col">By</th><th scope="col">Title</th><th scope="col">Status then</th><th scope="col">Note</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @foreach ($revisions as $revision)
                <tr>
                    <td>{{ $revision->id }}</td>
                    <td>{{ $revision->created_at->format('j M Y H:i') }}</td>
                    <td>{{ $revision->creator?->name ?? 'System' }}</td>
                    <td>{{ $revision->title }}</td>
                    <td>{{ $revision->meta['status'] ?? '' }}</td>
                    <td>{{ $revision->note }}</td>
                    <td>
                        <form method="post" action="{{ route('admin.pages.restore', [$page, $revision]) }}" data-confirm="Restore this revision as a draft?">
                            @csrf
                            <button type="submit" class="btn btn-icon" data-tip="Restore"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i><span class="visually-hidden">Restore this revision</span></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $revisions->links() }}
@endsection
