@extends('layouts.admin')
@section('title', 'Audit log')

@section('content')
    <div class="page-head"><h1>Audit log</h1></div>
    <nav class="filters" aria-label="Log type">
        <a class="tag" href="{{ route('admin.audit.index') }}" @if ($tab === 'changes') aria-current="true" @endif>Changes</a>
        <a class="tag" href="{{ route('admin.audit.index', ['tab' => 'logins']) }}" @if ($tab === 'logins') aria-current="true" @endif>Sign-ins</a>
    </nav>
    @if ($tab === 'changes')
        <form method="get" class="filters" role="search">
            <div class="form-group"><label for="action">Action starts with</label><input id="action" type="search" name="action" value="{{ request('action') }}" placeholder="page."></div>
            <button class="btn btn-secondary" type="submit">Filter</button>
        </form>
    @endif
    <p class="hint">IP addresses and sign-in emails are stored as keyed hashes, not in clear text.</p>
    <div class="table-wrap">
        <table>
            @if ($tab === 'changes')
                <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Subject</th><th scope="col">Details</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('j M Y H:i:s') }}</td>
                        <td>{{ $log->actor?->name ?? 'System' }}</td>
                        <td><code>{{ $log->action }}</code></td>
                        <td>{{ class_basename((string) $log->subject_type) }} {{ $log->subject_id }}</td>
                        <td>@if ($log->changes)<pre class="json">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No entries.</td></tr>
                @endforelse
                </tbody>
            @else
                <thead><tr><th scope="col">When</th><th scope="col">User ID</th><th scope="col">Method</th><th scope="col">Result</th><th scope="col">Reason</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('j M Y H:i:s') }}</td>
                        <td>{{ $log->user_id ?? '—' }}</td>
                        <td>{{ $log->method }}</td>
                        <td><span class="badge badge-{{ $log->success ? 'on' : 'off' }}">{{ $log->success ? 'Success' : 'Failed' }}</span></td>
                        <td>{{ $log->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No entries.</td></tr>
                @endforelse
                </tbody>
            @endif
        </table>
    </div>
    {{ $logs->links() }}
@endsection
