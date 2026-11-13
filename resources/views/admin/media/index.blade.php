@extends('layouts.admin')
@section('title', 'Media library')

@section('content')
    <div class="page-head"><h1>Media library</h1></div>

    <section class="panel" id="upload-media" data-inline-modal data-modal-trigger="Upload file" data-modal-trigger-into=".page-head">
        <h2>Upload a file</h2>
        <form method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
            @csrf
            @include('partials.field', ['name' => 'file', 'label' => 'File', 'type' => 'file', 'required' => true, 'attrs' => 'accept=".jpg,.jpeg,.png,.webp,.gif,.ico,.pdf,.mp4,.webm"',
                'hint' => 'JPG, PNG, WebP, GIF, ICO or PDF up to 5 MB; MP4 or WebM video up to 20 MB (for section backgrounds). Compress large files first.'])
            @include('partials.field', ['name' => 'alt_text', 'label' => 'Description for screen readers', 'maxlength' => 255, 'hint' => 'Describe what the image shows. Leave empty for decorative images.'])
            <div class="actions">
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </section>

    <nav class="filters" aria-label="Filter media">
        <a class="tag" href="{{ route('admin.media.index') }}" @if (!request('type')) aria-current="true" @endif>All</a>
        <a class="tag" href="{{ route('admin.media.index', ['type' => 'images']) }}" @if (request('type') === 'images') aria-current="true" @endif>Images</a>
        <a class="tag" href="{{ route('admin.media.index', ['type' => 'videos']) }}" @if (request('type') === 'videos') aria-current="true" @endif>Videos</a>
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
                        @elseif ($file->isVideo())
                            <video src="{{ $file->url() }}" muted playsinline preload="metadata" aria-hidden="true"></video>
                        @else
                            <span>PDF</span>
                        @endif
                    </div>
                    <p class="media-name"><a href="{{ $file->url() }}" target="_blank" rel="noopener">{{ $file->original_name }}</a></p>
                    <p class="item-meta"><span>{{ number_format($file->size / 1024) }} KB</span>@if ($file->alt_text)<span>Has description</span>@else<span>No description</span>@endif</p>
                    <div class="actions media-actions">
                        <a class="btn btn-secondary btn-sm" href="#edit-media-{{ $file->id }}" data-modal-open="edit-media-{{ $file->id }}">Edit<span class="visually-hidden"> {{ $file->original_name }}</span></a>
                        <form method="post" action="{{ route('admin.media.destroy', $file) }}" data-confirm="Delete “{{ $file->original_name }}”? Pages that use it will lose the image.">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete<span class="visually-hidden"> {{ $file->original_name }}</span></button>
                        </form>
                    </div>
                    <div class="media-edit" id="edit-media-{{ $file->id }}" data-inline-modal data-modal-size="sm" data-modal-title="Edit {{ $file->original_name }}">
                        <form method="post" action="{{ route('admin.media.update', $file) }}">
                            @csrf @method('PATCH')
                            <div class="form-group">
                                <label for="alt-{{ $file->id }}">Description for screen readers</label>
                                <input id="alt-{{ $file->id }}" type="text" name="alt_text" value="{{ $file->alt_text }}" maxlength="255" placeholder="Describe what the image shows">
                                <p class="hint">Leave empty for decorative images.</p>
                            </div>
                            <div class="actions">
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        {{ $media->links() }}
    @endif
@endsection
