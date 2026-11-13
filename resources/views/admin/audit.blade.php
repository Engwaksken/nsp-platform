@extends('layouts.admin')
@section('title', 'Audit log')

{{-- Each row is a short summary; the View button opens the full entry (a popup with JavaScript, its own page without). --}}
@section('content')
    <div class="page-head">
        <div>
            <h1>Audit log</h1>
            <p>{{ $tab === 'changes' ? 'Who changed what, and when.' : 'Every sign-in attempt, successful or not.' }}</p>
        </div>
    </div>

    @include('partials.stats', ['label' => 'Summary', 'stats' => $stats])

    <nav class="audit-tabs" aria-label="Log type">
        <a href="{{ route('admin.audit.index') }}" @if ($tab === 'changes') aria-current="page" @endif><i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Changes</a>
        <a href="{{ route('admin.audit.index', ['tab' => 'logins']) }}" @if ($tab === 'logins') aria-current="page" @endif><i class="fas fa-right-to-bracket" aria-hidden="true"></i> Sign-ins</a>
    </nav>

    <form method="get" class="filters" role="search" aria-label="Filter the audit log">
        @if ($tab === 'logins')
            <input type="hidden" name="tab" value="logins">
            <div class="form-group">
                <label for="result">Result</label>
                <select id="result" name="result">
                    <option value="">All</option>
                    <option value="success" @selected(($filters['result'] ?? '') === 'success')>Success</option>
                    <option value="failed" @selected(($filters['result'] ?? '') === 'failed')>Failed</option>
                </select>
            </div>
            <div class="form-group">
                <label for="method">Method</label>
                <select id="method" name="method">
                    <option value="">All</option>
                    @foreach ($methods as $method)<option value="{{ $method }}" @selected(($filters['method'] ?? '') === $method)>{{ strtoupper($method) }}</option>@endforeach
                </select>
            </div>
        @else
            <div class="form-group">
                <label for="action">Area</label>
                <select id="action" name="action">
                    <option value="">All areas</option>
                    @foreach ($actionGroups as $group)<option value="{{ $group }}." @selected(($filters['action'] ?? '') === $group.'.')>{{ \Illuminate\Support\Str::headline($group) }}</option>@endforeach
                    @if (filled($filters['action'] ?? null) && ! in_array(rtrim($filters['action'], '.'), $actionGroups, true))
                        <option value="{{ $filters['action'] }}" selected>Starts with “{{ $filters['action'] }}”</option>
                    @endif
                </select>
            </div>
            <div class="form-group"><label for="who">Who</label><input id="who" type="search" name="who" value="{{ $filters['who'] ?? '' }}" placeholder="Name or email"></div>
        @endif
        <div class="form-group"><label for="from">From</label><input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="form-group"><label for="to">To</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
        <div class="actions">
            <button class="btn btn-primary" type="submit"><i class="fas fa-filter" aria-hidden="true"></i> Filter</button>
            @if ($filtered)<a class="btn btn-secondary" href="{{ route('admin.audit.index', $tab === 'logins' ? ['tab' => 'logins'] : []) }}">Clear</a>@endif
        </div>
    </form>

    <p class="meta audit-count">
        {{ number_format($logs->total()) }} {{ \Illuminate\Support\Str::plural('entry', $logs->total()) }}{{ $filtered ? ($logs->total() === 1 ? ' matches' : ' match').' your filters' : '' }}.
        <span><i class="fas fa-shield-halved" aria-hidden="true"></i> IP addresses and sign-in emails are stored as keyed hashes, never in clear text.</span>
    </p>

    <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
        <table class="audit-table">
            <caption class="visually-hidden">{{ $tab === 'changes' ? 'Changes' : 'Sign-ins' }}</caption>
            @if ($tab === 'changes')
                <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Subject</th><th scope="col">Details</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    @php($parts = explode('.', $log->action, 2))
                    @php($fields = array_keys((array) $log->changes))
                    <tr>
                        <td class="audit-when"><time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('j M Y H:i:s') }}">{{ $log->created_at->format('j M Y') }}</time>
                            <span class="meta">{{ $log->created_at->format('H:i') }} · {{ $log->created_at->diffForHumans(short: true) }}</span></td>
                        <td>
                            <span class="audit-who">
                                <span class="avatar avatar-sm {{ $log->actor ? '' : 'avatar-system' }}" aria-hidden="true">@if ($log->actor){{ \Illuminate\Support\Str::of($log->actor->name)->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}@else<i class="fas fa-gear"></i>@endif</span>
                                <span>{{ $log->actor?->name ?? 'System' }}</span>
                            </span>
                        </td>
                        <td>
                            <span class="audit-area">{{ \Illuminate\Support\Str::headline($parts[0]) }}</span>
                            <strong class="audit-verb">{{ \Illuminate\Support\Str::of($parts[1] ?? $parts[0])->replace(['_', '.'], ' ')->ucfirst() }}</strong>
                        </td>
                        <td>@if ($log->subject_type)<span class="audit-subject">{{ \Illuminate\Support\Str::headline(class_basename((string) $log->subject_type)) }}</span>@if ($log->subject_id) <span class="meta">#{{ \Illuminate\Support\Str::limit($log->subject_id, 13) }}</span>@endif
                            @else<span class="meta">None</span>@endif</td>
                        <td>@if ($fields)
                                <span class="audit-fields">@foreach (array_slice($fields, 0, 3) as $field)<span class="tag">{{ \Illuminate\Support\Str::headline((string) $field) }}</span>@endforeach
                                    @if (count($fields) > 3)<span class="meta">+{{ count($fields) - 3 }} more</span>@endif</span>
                            @else<span class="meta">No details</span>@endif</td>
                        <td><a class="btn btn-icon" href="{{ route('admin.audit.show', $log) }}" data-modal="lg" data-tip="View"><i class="fas fa-eye" aria-hidden="true"></i><span class="visually-hidden">View entry {{ $log->id }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ $filtered ? 'No entries match these filters.' : 'No entries yet.' }}</td></tr>
                @endforelse
                </tbody>
            @else
                <thead><tr><th scope="col">When</th><th scope="col">User</th><th scope="col">Method</th><th scope="col">Result</th><th scope="col">Reason</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="audit-when"><time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('j M Y H:i:s') }}">{{ $log->created_at->format('j M Y') }}</time>
                            <span class="meta">{{ $log->created_at->format('H:i') }} · {{ $log->created_at->diffForHumans(short: true) }}</span></td>
                        <td>@if ($log->user_id !== null)User #{{ $log->user_id }}@else<span class="meta">Unknown account</span>@endif</td>
                        <td><span class="tag">{{ strtoupper($log->method) }}</span></td>
                        <td><span class="badge badge-{{ $log->success ? 'on' : 'off' }}">{{ $log->success ? 'Success' : 'Failed' }}</span></td>
                        <td>@if ($log->reason){{ \Illuminate\Support\Str::of($log->reason)->replace('_', ' ')->ucfirst() }}@else<span class="meta">None</span>@endif</td>
                        <td><a class="btn btn-icon" href="{{ route('admin.audit.login', $log) }}" data-modal data-tip="View"><i class="fas fa-eye" aria-hidden="true"></i><span class="visually-hidden">View sign-in {{ $log->id }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ $filtered ? 'No sign-ins match these filters.' : 'No sign-ins yet.' }}</td></tr>
                @endforelse
                </tbody>
            @endif
        </table>
    </div>
    {{ $logs->links() }}
@endsection
