@extends('layouts.admin')
@section('title', 'Knowledge categories')

@section('content')
    <div class="page-head"><h1>Knowledge categories</h1><a class="btn btn-secondary" href="{{ route('admin.knowledge.index') }}">Back to resources</a></div>
    @include('partials.flash')
    <form method="post" action="{{ route('admin.knowledge.categories.store') }}" class="panel">@csrf<h2>Add category</h2>
        @include('partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'maxlength' => 120])
        @include('partials.field', ['name' => 'slug', 'label' => 'Slug', 'required' => true, 'maxlength' => 120])
        @include('partials.field', ['name' => 'parent_id', 'label' => 'Parent category', 'type' => 'select', 'placeholder' => 'Top-level category', 'options' => collect($categories)->mapWithKeys(fn ($category) => [$category->id => str_repeat('— ', $category->depth).$category->name])->all()])
        @include('partials.field', ['name' => 'description', 'label' => 'Description', 'maxlength' => 500])
        <button class="btn btn-primary">Add category</button>
    </form>
    <h2>Existing categories</h2>
    @foreach ($categories as $category)
        <form method="post" action="{{ route('admin.knowledge.categories.update', $category) }}" class="panel">@csrf @method('PUT')
            <div class="form-row">
                @include('partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'maxlength' => 120, 'value' => $category->name])
                @include('partials.field', ['name' => 'slug', 'label' => 'Slug', 'required' => true, 'maxlength' => 120, 'value' => $category->slug])
                @include('partials.field', ['name' => 'parent_id', 'label' => 'Parent', 'type' => 'select', 'placeholder' => 'Top-level', 'options' => collect($categories)->reject(fn ($item) => in_array($category->id, $item->selfAndDescendantIds(), true))->mapWithKeys(fn ($item) => [$item->id => str_repeat('— ', $item->depth).$item->name])->all(), 'value' => $category->parent_id])
                @include('partials.field', ['name' => 'description', 'label' => 'Description', 'maxlength' => 500, 'value' => $category->description])
                @include('partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'value' => $category->sort_order])
            </div><button class="btn btn-secondary">Save category</button>
        </form>
    @endforeach
@endsection
