@extends('layouts.admin')
@section('title', $resource->exists ? 'Edit Knowledge resource' : 'New Knowledge resource')

@section('content')
    <div class="page-head"><h1>{{ $resource->exists ? $resource->title : 'New Knowledge resource' }}
        @if ($resource->exists)<span class="badge badge-{{ $resource->status === 'published' ? 'on' : 'draft' }}">{{ \Illuminate\Support\Str::headline($resource->status) }}</span>@endif
    </h1>
    @if ($resource->exists)<a class="btn btn-secondary" href="{{ route('admin.knowledge.versions', $resource->uuid) }}">Version history</a>@endif
    </div>
    @include('partials.flash')
    @if ($resource->status === 'published')<p class="alert alert-info">Published content is live. Saving changes records a new immutable version.</p>@endif
    <form method="post" enctype="multipart/form-data" action="{{ $resource->exists ? route('admin.knowledge.update', $resource->uuid) : route('admin.knowledge.store') }}" class="panel">
        @csrf @if ($resource->exists) @method('PUT') @endif
        @include('partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'maxlength' => 200, 'value' => $resource->title])
        @include('partials.field', ['name' => 'slug', 'label' => 'URL slug', 'required' => true, 'maxlength' => 140, 'value' => $resource->slug])
        @include('partials.field', ['name' => 'summary', 'label' => 'Summary', 'type' => 'textarea', 'rows' => 3, 'value' => $resource->summary])
        @include('partials.field', ['name' => 'body', 'label' => 'Body (Markdown)', 'type' => 'textarea', 'rows' => 12, 'value' => $resource->body])
        <div class="form-row">
            @include('partials.field', ['name' => 'resource_type', 'label' => 'Resource type', 'type' => 'select', 'required' => true, 'options' => config('knowledge.resource_types'), 'value' => $resource->resource_type])
            @include('partials.field', ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'placeholder' => 'No category', 'options' => collect($categories)->mapWithKeys(fn ($category) => [$category->id => str_repeat('— ', $category->depth).$category->name])->all(), 'value' => $resource->category_id])
            @include('partials.field', ['name' => 'license', 'label' => 'Licence', 'type' => 'select', 'required' => true, 'options' => collect(config('knowledge.licenses'))->mapWithKeys(fn ($license, $key) => [$key => $license['label']])->all(), 'value' => $resource->license])
            @include('partials.field', ['name' => 'visibility', 'label' => 'Who can view', 'type' => 'select', 'required' => true, 'options' => config('knowledge.visibilities'), 'value' => $resource->visibility])
        </div>
        <div class="form-row">
            @include('partials.field', ['name' => 'author', 'label' => 'Author', 'maxlength' => 200, 'value' => $resource->author])
            @include('partials.field', ['name' => 'publisher', 'label' => 'Publisher', 'maxlength' => 200, 'value' => $resource->publisher])
            @include('partials.field', ['name' => 'source', 'label' => 'Source / provenance', 'maxlength' => 200, 'value' => $resource->source])
            @include('partials.field', ['name' => 'publication_date', 'label' => 'Publication date', 'type' => 'date', 'value' => $resource->publication_date?->format('Y-m-d')])
            @include('partials.field', ['name' => 'external_url', 'label' => 'External source URL', 'type' => 'url', 'value' => $resource->external_url])
            @include('partials.field', ['name' => 'tags', 'label' => 'Tags (comma separated)', 'value' => $resource->tags?->pluck('name')->implode(', ')])
        </div>
        <h2>Who this helps</h2>
        <div class="form-row">
            @foreach (config('knowledge.audiences') as $key => $label)<label><input type="checkbox" name="audience[]" value="{{ $key }}" @checked(in_array($key, old('audience', $resource->audience ?? []), true))> {{ $label }}</label>@endforeach
        </div>
        <h2>Venture stages</h2><div class="form-row">
            @foreach (config('knowledge.stages') as $key => $label)<label><input type="checkbox" name="stages[]" value="{{ $key }}" @checked(in_array($key, old('stages', $resource->stages ?? []), true))> {{ $label }}</label>@endforeach
        </div>
        <h2>VGI dimensions</h2><div class="form-row">
            @foreach (config('knowledge.vgi_dimensions') as $key => $label)<label><input type="checkbox" name="vgi_dimension_keys[]" value="{{ $key }}" @checked(in_array($key, old('vgi_dimension_keys', $resource->vgi_dimension_keys ?? []), true))> {{ $label }}</label>@endforeach
        </div>
        @include('partials.field', ['name' => 'builder_step_keys', 'label' => 'Related Builder step keys (comma separated)', 'value' => implode(', ', $resource->builder_step_keys ?? [])])
        <div class="form-row">
            @include('partials.field', ['name' => 'review_due_at', 'label' => 'Review due', 'type' => 'date', 'value' => $resource->review_due_at?->format('Y-m-d')])
            @include('partials.field', ['name' => 'expires_at', 'label' => 'Expiry date', 'type' => 'date', 'value' => $resource->expires_at?->format('Y-m-d')])
            @include('partials.field', ['name' => 'seo_title', 'label' => 'SEO title', 'maxlength' => 200, 'value' => $resource->seo_title])
            @include('partials.field', ['name' => 'seo_description', 'label' => 'SEO description', 'maxlength' => 320, 'value' => $resource->seo_description])
        </div>
        <div class="form-group"><label for="knowledge-file">Upload file (private)</label><input id="knowledge-file" name="file" type="file" accept=".pdf,.docx,.xlsx,.pptx,.csv,.txt,.png,.jpg,.jpeg">@if ($resource->hasFile())<p>Current file: {{ $resource->file_name }} · <a href="{{ route('admin.knowledge.file', $resource->uuid) }}">Open privately</a></p><label><input type="checkbox" name="remove_file" value="1"> Remove current file</label>@endif</div>
        @if ($resource->exists && $resource->status === 'published')@include('partials.field', ['name' => 'change_note', 'label' => 'Version change note', 'maxlength' => 500, 'value' => ''])@endif
        <button class="btn btn-primary" type="submit">Save {{ $resource->status === 'published' ? 'new version' : 'draft' }}</button>
    </form>
    @if ($resource->exists && $resource->status === 'draft')
        <form method="post" action="{{ route('admin.knowledge.submit', $resource->uuid) }}" class="panel">@csrf<h2>Editorial workflow</h2><p>Submit the saved draft for an independent review before publication.</p><button class="btn btn-secondary">Submit for review</button></form>
    @endif
    @if ($resource->exists && $resource->status === 'in_review')
        @can('publish-knowledge')
            <form method="post" action="{{ route('admin.knowledge.publish', $resource->uuid) }}" class="panel">@csrf<h2>Review and publish</h2>@if (! $mayPublish)<p class="alert alert-info">Four-eyes review: another authorized publisher must publish this resource.</p>@endif
                @include('partials.field', ['name' => 'review_note', 'label' => 'Review note', 'type' => 'textarea', 'rows' => 3])
                <button class="btn btn-primary" @disabled(! $mayPublish)>Publish version</button>
            </form>
            <form method="post" action="{{ route('admin.knowledge.return', $resource->uuid) }}" class="panel">@csrf<h2>Return to editor</h2>@include('partials.field', ['name' => 'review_note', 'label' => 'Required explanation', 'type' => 'textarea', 'rows' => 2, 'required' => true])<button class="btn btn-secondary">Return as draft</button></form>
        @endcan
    @endif
    @if ($resource->exists && in_array($resource->status, ['published', 'archived'], true))
        @can('publish-knowledge')<form method="post" action="{{ route('admin.knowledge.archive', $resource->uuid) }}" class="panel">@csrf<h2>Archive</h2><p>Archived resources are removed from public and recommendation results.</p><button class="btn btn-secondary">Archive resource</button></form>@endcan
    @endif
@endsection
