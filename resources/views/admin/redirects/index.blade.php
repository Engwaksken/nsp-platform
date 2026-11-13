@extends('layouts.admin')
@section('title', 'Redirects')

@section('content')
    <div class="page-head"><h1>Redirects</h1></div>
    <p class="hint">Send visitors from an old address to a new one. Redirects only apply when no page exists at the old address.</p>
    <section class="panel" id="add-redirect" data-inline-modal data-modal-trigger="Add redirect" data-modal-trigger-into=".page-head">
        <h2>Add a redirect</h2>
        <form method="post" action="{{ route('admin.redirects.store') }}">
            @csrf
            @include('partials.field', ['name' => 'from_path', 'label' => 'Old address', 'required' => true, 'placeholder' => '/old-programs', 'hint' => 'Starts with /, e.g. /old-programs'])
            @include('partials.field', ['name' => 'to_url', 'label' => 'New address', 'required' => true, 'placeholder' => '/new-page or https://…', 'hint' => 'A page on this site or a full web address.'])
            @include('partials.field', ['name' => 'status_code', 'label' => 'Type', 'type' => 'select', 'options' => [301 => 'Permanent (301)', 302 => 'Temporary (302)'], 'value' => 301, 'required' => true])
            <div class="actions"><button type="submit" class="btn btn-primary">Add redirect</button></div>
        </form>
    </section>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
        <table>
            <thead><tr><th scope="col">From</th><th scope="col">To</th><th scope="col">Type</th><th scope="col">Uses</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse ($redirects as $redirect)
                <tr>
                    <td>{{ $redirect->from_path }}</td><td>{{ $redirect->to_url }}</td><td>{{ $redirect->status_code }}</td><td>{{ $redirect->hits }}</td>
                    <td>
                        <form method="post" action="{{ route('admin.redirects.destroy', $redirect) }}" data-confirm="Remove this redirect?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-icon btn-icon-danger" data-tip="Remove"><i class="fas fa-trash-can" aria-hidden="true"></i><span class="visually-hidden">Remove redirect from {{ $redirect->from_path }}</span></button>
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
