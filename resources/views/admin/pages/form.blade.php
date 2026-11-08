@extends('layouts.admin')
@section('title', $page->exists ? 'Edit page' : 'New page')

@section('breadcrumbs')
    <li><a href="{{ route('admin.pages.index') }}">Pages</a></li>
    <li aria-current="page">{{ $page->exists ? $page->title : 'New page' }}</li>
@endsection

@section('content')
    <div class="page-head">
        <h1>{{ $page->exists ? 'Edit: ' . $page->title : 'New page' }}</h1>
        @if ($page->exists)
            <div class="actions">
                <a class="btn btn-secondary" href="{{ route('admin.pages.preview', $page) }}" target="_blank" rel="noopener">Preview</a>
                <a class="btn btn-secondary" href="{{ route('admin.pages.revisions', $page) }}">Revisions</a>
                @if ($page->isLive())<a class="btn btn-secondary" href="{{ $page->publicUrl() }}" target="_blank" rel="noopener">View live</a>@endif
            </div>
        @endif
    </div>

    <form method="post" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
        @csrf
        @if ($page->exists) @method('PUT') @endif

        <section class="panel" aria-labelledby="details-heading">
            <h2 id="details-heading">Details</h2>
            @include('partials.field', ['name' => 'title', 'label' => 'Title', 'value' => $page->title, 'required' => true, 'maxlength' => 200, 'attrs' => 'data-slug-source'])
            @include('partials.field', ['name' => 'slug', 'label' => 'Address (slug)', 'value' => $page->slug, 'required' => true, 'maxlength' => 120, 'attrs' => 'data-slug-target',
                'hint' => 'Lower-case words with hyphens, e.g. about-us. Use "home" for the home page.'])
            @include('partials.field', ['name' => 'template', 'label' => 'Template', 'type' => 'select', 'options' => \App\Models\Page::TEMPLATES, 'value' => $page->template, 'required' => true])
            @include('partials.field', ['name' => 'summary', 'label' => 'Summary', 'type' => 'textarea', 'rows' => 3, 'value' => $page->summary, 'hint' => 'Shown under the title when the page has no hero section.'])
        </section>

        <section class="panel" aria-labelledby="sections-heading">
            <h2 id="sections-heading">Sections</h2>
            <p class="hint">Build the page from sections. Text uses Markdown; HTML is not allowed.</p>
            <div id="blocks">
                @foreach (old('blocks', $page->blocks ?? []) as $index => $block)
                    @include('admin.pages.block', ['index' => $loop->index, 'block' => $block])
                @endforeach
            </div>
            <p id="blocks-status" class="visually-hidden" aria-live="polite"></p>
            <div class="filters">
                <div class="form-group">
                    <label for="new-block-type">Section type</label>
                    <select id="new-block-type">
                        @foreach (\App\Models\Page::BLOCK_TYPES as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="btn btn-secondary" id="add-block">Add section</button>
            </div>
            <noscript><p class="hint">Adding and reordering sections needs JavaScript. You can still edit existing sections.</p></noscript>
        </section>

        @include('admin.partials.publishing', ['item' => $page])
        @include('admin.partials.seo', ['item' => $page])
        <section class="panel">
            @include('admin.partials.media-select', ['name' => 'og_image_id', 'label' => 'Sharing image', 'value' => $page->og_image_id, 'media' => $media])
            @if ($page->exists)
                @include('partials.field', ['name' => 'revision_note', 'label' => 'Note for this revision (optional)', 'maxlength' => 200])
            @endif
        </section>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save page</button>
            <a class="btn btn-secondary" href="{{ route('admin.pages.index') }}">Cancel</a>
        </div>
    </form>

    @if ($page->exists && $page->slug !== 'home')
        <form method="post" action="{{ route('admin.pages.destroy', $page) }}" data-confirm="Move this page to the trash?" class="panel">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete page</button>
        </form>
    @endif

    <template id="block-template">
        @include('admin.pages.block', ['index' => '__INDEX__', 'block' => ['type' => 'text']])
    </template>
@endsection
