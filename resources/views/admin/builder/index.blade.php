@extends('layouts.admin')
@section('title', 'Venture Builder curricula')

@section('content')
    <div class="page-head"><h1>Venture Builder curricula</h1></div>
    @include('partials.flash')
    <p>Publishing checks the six-module/24-step structure. Curriculum content remains marked provisional until owner methodology approval.</p>
    @include('partials.stats', ['label' => 'Curriculum statistics', 'stats' => [
        ['icon' => 'fa-layer-group', 'label' => 'Versions', 'value' => $curriculumStats['total'], 'tone' => 'blue'],
        ['icon' => 'fa-file-pen', 'label' => 'Drafts', 'value' => $curriculumStats['drafts'], 'tone' => 'amber'],
        ['icon' => 'fa-circle-check', 'label' => 'Published', 'value' => $curriculumStats['published'], 'tone' => 'green'],
    ]])
    @can('manage-builder-curricula')<form method="post" action="{{ route('admin.builder.create') }}">@csrf<button class="btn btn-primary" type="submit">Create new draft version</button></form>@endcan
    <form method="get" class="filters" role="search">
        <div class="form-group"><label for="q">Search curricula</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Curriculum name"></div>
        <div class="form-group"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach (\App\Models\Builder\BuilderCurriculum::STATUSES as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="actions"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-secondary" href="{{ route('admin.builder.index') }}">Clear</a></div>
    </form>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Curriculum versions">
        <table><thead><tr><th>Version</th><th>Status</th><th>Methodology</th><th>Modules / steps</th><th>Actions</th></tr></thead><tbody>
            @forelse ($curricula as $curriculum)
                <tr><td>{{ $curriculum->name }} v{{ $curriculum->version }}</td><td>{{ ucfirst($curriculum->status) }}</td><td>{{ \App\Models\Builder\BuilderCurriculum::METHODOLOGY[$curriculum->methodology_status] ?? $curriculum->methodology_status }}</td>
                    <td>{{ $curriculum->modules->count() }} / {{ $curriculum->steps->count() }}</td>
                    <td>@if ($curriculum->isDraft())<a href="{{ route('admin.builder.edit', $curriculum) }}">Edit draft</a>
                        @can('publish-builder-curricula')<form method="post" action="{{ route('admin.builder.publish', $curriculum) }}">@csrf<label for="publish-note-{{ $curriculum->id }}">Publication review note</label><input id="publish-note-{{ $curriculum->id }}" name="note" required minlength="10" maxlength="5000" placeholder="At least 10 characters on what you reviewed"><button class="btn btn-primary" type="submit">Publish provisional curriculum</button></form>@endcan
                    @else<span>Frozen</span>@endif</td></tr>
            @empty<tr><td colspan="5">No curriculum draft yet. Run the database seeders to add the provisional scaffold.</td></tr>@endforelse
        </tbody></table>
    </div>
    {{ $curricula->links() }}
@endsection
