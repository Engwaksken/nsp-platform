@extends('layouts.admin')
@section('title', 'Edit Venture Builder curriculum')

@section('content')
    <div class="page-head"><h1>Edit {{ $curriculum->name }} v{{ $curriculum->version }}</h1></div>
    @include('partials.flash')
    <p class="hint">Edit modules, steps, founder response fields, guidance and checklists. Saving keeps this version unpublished; publishing checks required structure and content.</p>
    @include('partials.admin-ai-panel', ['context' => 'builder', 'subjectId' => $curriculum->id])
    @if ($errors->has('definition'))
        <div class="alert alert-danger" role="alert"><strong>Review these items:</strong><ul>@foreach ((array) $errors->get('definition') as $problem)<li>{{ $problem }}</li>@endforeach</ul></div>
    @endif
    <form method="post" action="{{ route('admin.builder.update', $curriculum) }}" class="panel" id="curriculum-editor">
        @csrf @method('PUT')
        <div class="curriculum-module-list" data-curriculum-module-list>
            @foreach ($definition['modules'] ?? [] as $moduleIndex => $module)
                @include('admin.builder.partials.module-editor', ['moduleIndex' => $moduleIndex, 'module' => $module])
            @endforeach
        </div>
        <template data-curriculum-module-template>
            @include('admin.builder.partials.module-editor', ['moduleIndex' => '__MODULE__', 'module' => ['key' => '', 'title' => '', 'steps' => [], 'active' => true]])
        </template>
        <div class="actions">
            <button type="button" class="btn btn-secondary" data-curriculum-add-module>Add module</button>
            <button class="btn btn-primary" type="submit">Save draft</button>
            <a class="btn btn-secondary" href="{{ route('admin.builder.index') }}">Back to versions</a>
        </div>
    </form>
    <p class="hint">A publishable version must have six active modules and 24 active steps. Each step needs at least one founder response field; keys must be unique.</p>
@endsection
