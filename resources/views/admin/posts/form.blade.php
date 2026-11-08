@extends('layouts.admin')
@section('title', $post->exists ? 'Edit post' : 'New post')

@section('breadcrumbs')
    <li><a href="{{ route('admin.posts.index') }}">News</a></li>
    <li aria-current="page">{{ $post->exists ? $post->title : 'New post' }}</li>
@endsection

@section('content')
    <div class="page-head"><h1>{{ $post->exists ? 'Edit: ' . $post->title : 'New post' }}</h1></div>

    <form method="post" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}">
        @csrf
        @if ($post->exists) @method('PUT') @endif
        <section class="panel">
            @include('partials.field', ['name' => 'title', 'label' => 'Title', 'value' => $post->title, 'required' => true, 'maxlength' => 200, 'attrs' => 'data-slug-source'])
            @include('partials.field', ['name' => 'slug', 'label' => 'Address (slug)', 'value' => $post->slug, 'required' => true, 'maxlength' => 120, 'attrs' => 'data-slug-target'])
            @include('partials.field', ['name' => 'excerpt', 'label' => 'Short summary', 'type' => 'textarea', 'rows' => 3, 'value' => $post->excerpt])
            @include('partials.field', ['name' => 'body', 'label' => 'Article (Markdown)', 'type' => 'textarea', 'rows' => 16, 'value' => $post->body, 'hint' => 'Markdown: **bold**, ## headings, [link](https://…). HTML is not allowed.'])
            @include('admin.partials.media-select', ['name' => 'cover_media_id', 'label' => 'Cover image', 'value' => $post->cover_media_id, 'media' => $media])
            <fieldset class="form-group">
                <legend class="label">Categories</legend>
                @forelse ($categories as $category)
                    <label class="checkbox"><input type="checkbox" name="categories[]" value="{{ $category->id }}"
                        @checked(in_array($category->id, old('categories', $post->exists ? $post->categories->pluck('id')->all() : [])))> {{ $category->name }}</label>
                @empty
                    <p class="hint">No categories yet. <a href="{{ route('admin.categories.index') }}">Add categories</a>.</p>
                @endforelse
            </fieldset>
        </section>
        @include('admin.partials.publishing', ['item' => $post])
        @include('admin.partials.seo', ['item' => $post])
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save post</button>
            <a class="btn btn-secondary" href="{{ route('admin.posts.index') }}">Cancel</a>
        </div>
    </form>

    @if ($post->exists)
        <form method="post" action="{{ route('admin.posts.destroy', $post) }}" data-confirm="Move this post to the trash?" class="panel">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete post</button>
        </form>
    @endif
@endsection
