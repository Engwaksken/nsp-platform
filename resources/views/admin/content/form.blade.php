@extends('layouts.admin')
@section('title', ($item->exists ? 'Edit ' : 'Add ') . $definition['singular'])

@section('breadcrumbs')
    <li><a href="{{ route('admin.content.index', $definition['key']) }}">{{ $definition['plural'] }}</a></li>
    <li aria-current="page">{{ $item->exists ? 'Edit' : 'Add' }}</li>
@endsection

@section('content')
    <div class="page-head"><h1>{{ $item->exists ? 'Edit' : 'Add' }} {{ $definition['singular'] }}</h1></div>
    <form method="post" class="panel"
          action="{{ $item->exists ? route('admin.content.update', [$definition['key'], $item->id]) : route('admin.content.store', $definition['key']) }}">
        @csrf
        @if ($item->exists) @method('PUT') @endif
        @foreach ($definition['fields'] as $name => $field)
            @if ($field['type'] === 'media')
                @include('admin.partials.media-select', ['name' => $name, 'label' => $field['label'], 'value' => $item->{$name}, 'media' => $media])
            @elseif ($field['type'] === 'checkbox')
                <div class="form-group"><label class="checkbox"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $item->{$name}))> <span>{{ $field['label'] }}</span></label></div>
            @else
                @include('partials.field', ['name' => $name, 'label' => $field['label'], 'type' => $field['type'], 'value' => $item->{$name}, 'required' => in_array('required', $field['rules'], true)])
            @endif
        @endforeach
        @include('partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'value' => $item->sort_order ?? 0, 'hint' => 'Lower numbers show first.'])
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $item->is_published))> <span>Show on the website</span></label></div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <a class="btn btn-secondary" href="{{ route('admin.content.index', $definition['key']) }}">Cancel</a>
        </div>
    </form>
@endsection
