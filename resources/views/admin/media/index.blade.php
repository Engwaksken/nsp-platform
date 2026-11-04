@extends('layouts.admin')
@section('title', 'Media library')

@section('content')
    <div class="page-head"><h1>Media library</h1></div>

    <form method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="panel">
        @csrf
        <h2>Upload</h2>
        <div class="form-row">
            @include('partials.field', ['name' => 'file', 'label' => 'File', 'type' => 'file', 'required' => true, 'attrs' => 'accept=".jpg,.jpeg,.png,.webp,.gif,.ico,.pdf"',
                'hint' => 'JPG, PNG, WebP, GIF, ICO or PDF, up to 5 MB. Compress large photos first.'])
            @include('partials.field', ['name' => 'alt_text', 'label' => 'Description for screen readers', 'maxlength' => 255, 'hint' => 'Describe what the image shows. Leave empty for decorative images.'])
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>

    <nav class="filters" aria-label="Filter media">
        <a class="tag" href="{{ route('admin.media.index') }}" @if (!request('type')) aria-current="true" @endif>All</a>
        <a class="tag" href="{{ route('admin.media.index', ['type' => 'images']) }}" @if (request('type') === 'images') aria-current="true" @endif>Images</a>
        <a class="tag" href="{{ route('admin.media.index', ['type' => 'documents']) }}" @if (request('type') === 'documents') aria-current="true" @endif>Documents</a>
    </nav>

    @if ($media->isEmpty())
        <p class="empty">No files uploaded yet.</p>
    @else
        <ul class="media-grid" role="list">
            @foreach ($media as $file)
                <li class="card">
                    <div class="media-thumb">
                        @if ($file->isImage())
                            <img src="{{ $file->url() }}" alt="" loading="lazy">
                        @else
                            <span>PDF</span>
                        @endif
                    </div>
                    <p><a href="{{ $file->url() }}" target="_blank" rel="noopener">{{ $file->original_name }}</a><br>
                        <span class="meta">{{ number_format($file->size / 1024) }} KB</span></p>
                    <form method="post" action="{{ route('admin.media.update', $file) }}">
                        @csrf @method('PATCH')
                        <label for="alt-{{ $file->id }}">Description</label>
                        <input id="alt-{{ $file->id }}" type="text" name="alt_text" value="{{ $file->alt_text }}" maxlength="255">
                        <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                    </form>
                    <form method="post" action="{{ route('admin.media.destroy', $file) }}" data-confirm="Delete this file? Pages that use it will lose the image.">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-link">Delete<span class="visually-hidden"> {{ $file->original_name }}</span></button>
                    </form>
                </li>
            @endforeach
        </ul>
        {{ $media->links() }}
    @endif
@endsection
