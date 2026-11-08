@extends('layouts.public', ['pageTitle' => 'Change history · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>Change history · {{ $startup->display_name }}</h1>
        @include('workspace.startups.nav')
        <p class="hint">Private details only show that they changed, never their values.</p>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Change history">
            <table>
                <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Field</th><th scope="col">Before</th><th scope="col">After</th><th scope="col">Version</th></tr></thead>
                <tbody>
                @forelse ($changes as $change)
                    <tr>
                        <td>{{ $change->created_at->format('j M Y H:i') }}</td>
                        <td>{{ $change->changer?->name ?? 'System' }}</td>
                        <td>{{ $change->field_name === '*' ? 'Profile' : str_replace('_', ' ', $change->field_name) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) $change->old_value, 80) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) $change->new_value, 80) }}</td>
                        <td>{{ $change->version }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No changes yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $changes->links() }}
    </div>
@endsection
