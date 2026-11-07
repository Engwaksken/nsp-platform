@extends('layouts.admin')
@section('title', 'Venture Builder curricula')

@section('content')
    <div class="page-head"><h1>Venture Builder curricula</h1></div>
    @include('partials.flash')
    <p>Publishing checks the six-module/24-step structure. Curriculum content remains marked provisional until owner methodology approval.</p>
    @can('manage-builder-curricula')<form method="post" action="{{ route('admin.builder.create') }}">@csrf<button class="btn btn-primary" type="submit">Create new draft version</button></form>@endcan
    <div class="table-wrap" tabindex="0" role="region" aria-label="Curriculum versions">
        <table><thead><tr><th>Version</th><th>Status</th><th>Methodology</th><th>Modules / steps</th><th>Actions</th></tr></thead><tbody>
            @forelse ($curricula as $curriculum)
                <tr><td>{{ $curriculum->name }} v{{ $curriculum->version }}</td><td>{{ ucfirst($curriculum->status) }}</td><td>{{ \App\Models\Builder\BuilderCurriculum::METHODOLOGY[$curriculum->methodology_status] ?? $curriculum->methodology_status }}</td>
                    <td>{{ $curriculum->modules->count() }} / {{ $curriculum->steps->count() }}</td>
                    <td>@if ($curriculum->isDraft())<a href="{{ route('admin.builder.edit', $curriculum) }}">Edit draft</a>
                        @can('publish-builder-curricula')<form method="post" action="{{ route('admin.builder.publish', $curriculum) }}">@csrf<label for="publish-note-{{ $curriculum->id }}">Publication review note</label><input id="publish-note-{{ $curriculum->id }}" name="note" required minlength="10" maxlength="5000"><button class="btn btn-primary" type="submit">Publish provisional curriculum</button></form>@endcan
                    @else<span>Frozen</span>@endif</td></tr>
            @empty<tr><td colspan="5">No curriculum draft yet. Run the database seeders to add the provisional scaffold.</td></tr>@endforelse
        </tbody></table>
    </div>
@endsection
