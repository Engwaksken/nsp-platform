@extends('layouts.admin')
@section('title', 'Redirects')

@section('content')
    <div class="page-head"><h1>Redirects</h1></div>
    <p class="hint">Send visitors from an old address to a new one. Redirects only apply when no page exists at the old address.</p>
    <form method="post" action="{{ route('admin.redirects.store') }}" class="panel form-row">
        @csrf
        @include('partials.field', ['name' => 'from_path', 'label' => 'Old address', 'required' => true, 'hint' => 'Starts with /, e.g. /old-programs'])
        @include('partials.field', ['name' => 'to_url', 'label' => 'New address', 'required' => true, 'hint' => '/new-page or https://…'])
        @include('partials.field', ['name' => 'status_code', 'label' => 'Type', 'type' => 'select', 'options' => [301 => 'Permanent (301)', 302 => 'Temporary (302)'], 'value' => 301, 'required' => true])
        <div><button type="submit" class="btn btn-primary">Add redirect</button></div>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">From</th><th scope="col">To</th><th scope="col">Type</th><th scope="col">Uses</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse ($redirects as $redirect)
                <tr>
                    <td>{{ $redirect->from_path }}</td><td>{{ $redirect->to_url }}</td><td>{{ $redirect->status_code }}</td><td>{{ $redirect->hits }}</td>
                    <td>
                        <form method="post" action="{{ route('admin.redirects.destroy', $redirect) }}" data-confirm="Remove this redirect?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No redirects.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $redirects->links() }}
@endsection
