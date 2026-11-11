@extends('layouts.admin')
@section('title', 'Edit announcement')

@section('content')
    <div class="page-head"><h1>Edit draft announcement</h1></div>
    @include('partials.flash')
    <form method="post" action="{{ route('admin.communications.update', $announcement) }}" class="panel">
        @csrf @method('PUT')
        @include('admin.communications._form', ['announcement' => $announcement])
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save draft</button>
            <a class="btn btn-secondary" href="{{ route('admin.communications.index') }}">Cancel</a>
        </div>
    </form>
@endsection
