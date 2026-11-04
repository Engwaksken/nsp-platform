@extends('layouts.admin')
@section('title', 'Categories')

@section('content')
    <div class="page-head"><h1>News categories</h1></div>
    <form method="post" action="{{ route('admin.categories.store') }}" class="panel filters">
        @csrf
        @include('partials.field', ['name' => 'name', 'label' => 'New category', 'required' => true, 'maxlength' => 80])
        <button type="submit" class="btn btn-primary">Add</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">Name</th><th scope="col">Posts</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->posts_count }}</td>
                    <td>
                        <form method="post" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Delete this category? Posts keep their content.">
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
@endsection
