@extends('layouts.admin')
@section('title', 'Categories')

@section('content')
    <div class="page-head"><h1>News categories</h1></div>
    @include('partials.stats', ['label' => 'Category statistics', 'stats' => [
        ['icon' => 'fa-folder-open', 'label' => 'Categories', 'value' => $categoryStats['total'], 'tone' => 'blue'],
        ['icon' => 'fa-newspaper', 'label' => 'Categorized posts', 'value' => $categoryStats['posts'], 'tone' => 'purple'],
    ]])
    <section class="panel" id="add-category" data-inline-modal data-modal-size="sm" data-modal-trigger="Add category" data-modal-trigger-into=".page-head">
        <h2>Add a category</h2>
        <form method="post" action="{{ route('admin.categories.store') }}">
            @csrf
            @include('partials.field', ['name' => 'name', 'label' => 'Category name', 'required' => true, 'maxlength' => 80, 'placeholder' => 'e.g. Funding news'])
            <div class="actions">
                <button type="submit" class="btn btn-primary">Add category</button>
            </div>
        </form>
    </section>
    <form method="get" class="filters" role="search">
        <div class="form-group"><label for="q">Search categories</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Category name"></div>
        <div class="actions"><button type="submit" class="btn btn-primary">Search</button><a class="btn btn-secondary" href="{{ route('admin.categories.index') }}">Clear</a></div>
    </form>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
        <table>
            <thead><tr><th scope="col">Name</th><th scope="col">Posts</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->posts_count }}</td>
                    <td>
                        <form method="post" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Delete the category “{{ $category->name }}”? Posts keep their content.">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete<span class="visually-hidden"> {{ $category->name }}</span></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">No categories yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $categories->links() }}
@endsection
