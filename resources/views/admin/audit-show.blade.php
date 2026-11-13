@extends('layouts.admin')
@section('title', $kind === 'login' ? 'Sign-in attempt' : 'Audit entry')

{{-- The full record of one audit entry. Opened in a popup from the audit log (only #main is shown there). --}}
@section('breadcrumbs')
    <li><a href="{{ route('admin.audit.index', $kind === 'login' ? ['tab' => 'logins'] : []) }}">Audit log</a></li>
    <li aria-current="page">{{ $kind === 'login' ? 'Sign-in' : 'Entry' }} #{{ $log->id }}</li>
@endsection

@section('content')

    @if ($kind === 'login')
        <div class="page-head"><h1>Sign-in attempt #{{ $log->id }}</h1></div>
        <div class="audit-hero {{ $log->success ? 'is-ok' : 'is-bad' }}">
            <span class="audit-hero-icon" aria-hidden="true"><i class="fas {{ $log->success ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i></span>
            <div>
                <strong>{{ $log->success ? 'Signed in successfully' : 'Sign-in failed' }}</strong>
                <span class="meta">{{ strtoupper($log->method) }} · {{ $log->created_at->format('l j F Y, H:i:s') }} ({{ $log->created_at->diffForHumans() }})</span>
            </div>
        </div>
        <dl class="audit-details">
            <dt>Account</dt>
            <dd>@if ($user){{ $user->name }} <span class="meta">{{ $user->email }} · user #{{ $user->id }}</span>@elseif ($log->user_id)User #{{ $log->user_id }} <span class="meta">(account no longer exists)</span>@else<span class="meta">No matching account</span>@endif</dd>
            <dt>Method</dt><dd>{{ strtoupper($log->method) }}</dd>
            <dt>Result</dt><dd><span class="badge badge-{{ $log->success ? 'on' : 'off' }}">{{ $log->success ? 'Success' : 'Failed' }}</span></dd>
            <dt>Reason</dt><dd>@if ($log->reason){{ \Illuminate\Support\Str::of($log->reason)->replace('_', ' ')->ucfirst() }} <code>{{ $log->reason }}</code>@else<span class="meta">None recorded</span>@endif</dd>
            <dt>Email (hashed)</dt><dd>@if ($log->identifier_hash)<code class="audit-hash" title="{{ $log->identifier_hash }}">{{ \Illuminate\Support\Str::limit($log->identifier_hash, 16, '…') }}</code>@else<span class="meta">None</span>@endif</dd>
            <dt>IP address (hashed)</dt><dd>@if ($log->ip_hash)<code class="audit-hash" title="{{ $log->ip_hash }}">{{ \Illuminate\Support\Str::limit($log->ip_hash, 16, '…') }}</code>@else<span class="meta">None</span>@endif</dd>
        </dl>
    @else
        @php($parts = explode('.', $log->action, 2))
        <div class="page-head"><h1>{{ \Illuminate\Support\Str::headline($parts[0]) }}: {{ \Illuminate\Support\Str::of($parts[1] ?? $parts[0])->replace(['_', '.'], ' ') }}</h1></div>
        <div class="audit-hero">
            <span class="audit-hero-icon" aria-hidden="true"><i class="fas fa-clock-rotate-left"></i></span>
            <div>
                <strong>{{ $log->actor?->name ?? 'System' }}</strong>
                <span class="meta">{{ $log->created_at->format('l j F Y, H:i:s') }} ({{ $log->created_at->diffForHumans() }})</span>
            </div>
        </div>
        <dl class="audit-details">
            <dt>Action</dt><dd><code>{{ $log->action }}</code></dd>
            <dt>Who</dt><dd>@if ($log->actor){{ $log->actor->name }} <span class="meta">{{ $log->actor->email }} · user #{{ $log->actor->id }}</span>@else System or signed-out visitor @endif</dd>
            <dt>Subject</dt><dd>@if ($log->subject_type){{ \Illuminate\Support\Str::headline(class_basename((string) $log->subject_type)) }}@if ($log->subject_id) <code>{{ $log->subject_id }}</code>@endif
                @if (class_basename((string) $log->subject_type) !== $log->subject_type)<br><span class="meta">{{ $log->subject_type }}</span>@endif
                @else<span class="meta">None</span>@endif</dd>
            <dt>IP address (hashed)</dt><dd>@if ($log->ip_hash)<code class="audit-hash" title="{{ $log->ip_hash }}">{{ \Illuminate\Support\Str::limit($log->ip_hash, 16, '…') }}</code>@else<span class="meta">None</span>@endif</dd>
            <dt>Browser</dt><dd>@if ($log->user_agent)<span class="audit-agent">{{ $log->user_agent }}</span>@else<span class="meta">None</span>@endif</dd>
        </dl>

        <h2 class="h3">Details</h2>
        @if ($log->changes)
            <div class="table-wrap" data-no-stack>
                <table class="audit-changes">
                    <caption class="visually-hidden">Recorded details</caption>
                    <thead><tr><th scope="col">Field</th><th scope="col">Value</th></tr></thead>
                    <tbody>
                    @foreach ($log->changes as $field => $value)
                        <tr>
                            <th scope="row">{{ \Illuminate\Support\Str::headline((string) $field) }}<span class="meta">{{ $field }}</span></th>
                            <td>@if (is_array($value) || is_object($value))<pre class="json">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                @elseif (is_bool($value)){{ $value ? 'Yes' : 'No' }}
                                @elseif ($value === null)<span class="meta">Empty</span>
                                @else{{ $value }}@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <details class="audit-raw"><summary>Raw JSON</summary><pre class="json">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></details>
        @else
            <p class="meta">No extra details were recorded for this action.</p>
        @endif
    @endif
@endsection
