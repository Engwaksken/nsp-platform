@extends('layouts.admin')
@section('title', 'Knowledge version history')

@section('content')
    <div class="page-head"><h1>Version history · {{ $resource->title }}</h1><a class="btn btn-secondary" href="{{ route('admin.knowledge.edit', $resource->uuid) }}">Back to resource</a></div>
    @foreach ($versions as $version)
        <article class="panel"><h2>Version {{ $version->version }} @if ($version->version === $resource->current_version)<span class="badge badge-on">Current</span>@endif</h2>
            <p>{{ $version->created_at?->format('j M Y H:i') ?? 'Date not recorded' }} · published by {{ $version->publisher?->name ?? 'Unknown' }}</p>
            @if ($version->change_note)<p>{{ $version->change_note }}</p>@endif
            <dl><dt>Title</dt><dd>@if (filled($version->snapshot['title'] ?? null)){{ $version->snapshot['title'] }}@else<span class="meta">Not given</span>@endif</dd><dt>Source</dt><dd>@if (filled($version->snapshot['source'] ?? null)){{ $version->snapshot['source'] }}@else<span class="meta">Not given</span>@endif</dd><dt>Licence</dt><dd>{{ config('knowledge.licenses.'.($version->snapshot['license'] ?? '').'.label', $version->snapshot['license'] ?? 'Not given') }}</dd><dt>Visibility</dt><dd>{{ config('knowledge.visibilities.'.($version->snapshot['visibility'] ?? '').'.label', $version->snapshot['visibility'] ?? 'Not given') }}</dd></dl>
            @if ($version->file_name)<p>File snapshot: {{ $version->file_name }} ({{ number_format(($version->file_size ?? 0) / 1024, 1) }} KB)</p>@endif
        </article>
    @endforeach
    @if ($versions->isEmpty())<p class="empty">No published versions have been recorded.</p>@endif
@endsection
