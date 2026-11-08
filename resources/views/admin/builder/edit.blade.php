@extends('layouts.admin')
@section('title', 'Edit Venture Builder curriculum')

@section('content')
    <div class="page-head"><h1>Edit {{ $curriculum->name }} v{{ $curriculum->version }}</h1></div>
    @include('partials.flash')
    <p>This is an unpublished, provisional version. Editing does not publish it. Keep exactly six active modules and 24 active steps to enable publication.</p>
    <form method="post" action="{{ route('admin.builder.update', $curriculum) }}" class="panel">
        @csrf @method('PUT')
        <label for="definition">Curriculum definition (JSON)</label>
        <textarea id="definition" name="definition" rows="36" required spellcheck="false">{{ old('definition', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) }}</textarea>
        <button class="btn btn-primary" type="submit">Save draft</button>
        <a class="btn btn-secondary" href="{{ route('admin.builder.index') }}">Back to versions</a>
    </form>
@endsection
