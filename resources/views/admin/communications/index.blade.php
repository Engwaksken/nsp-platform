@extends('layouts.admin')
@section('title', 'Announcements and email log')

@section('content')
    <div class="page-head"><h1>Announcements and email log</h1></div>
    @include('partials.flash')

    <section class="panel" aria-labelledby="new-heading">
        <h2 id="new-heading" class="h3">New announcement</h2>
        <p class="hint">Everyone in the audience sees it on NSP unless they turned that off. Only people who opted in to “News from Hive” get it by email.</p>
        <form method="post" action="{{ route('admin.communications.store') }}">
            @csrf
            @include('partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'maxlength' => 200])
            @include('partials.field', ['name' => 'body', 'label' => 'Message', 'type' => 'textarea', 'rows' => 4, 'required' => true])
            @include('partials.field', ['name' => 'url', 'label' => 'Link (optional)', 'type' => 'url'])
            @include('partials.field', ['name' => 'audience', 'label' => 'Audience', 'type' => 'select', 'required' => true,
                'options' => collect(\App\Models\Communication\Announcement::AUDIENCES)->map(fn ($label, $key) => $label.' ('.$audienceSizes[$key].')')->all()])
            <button type="submit" class="btn btn-primary">Save draft</button>
        </form>
    </section>

    <h2>Announcements</h2>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Announcements">
        <table>
            <thead><tr><th scope="col">Title</th><th scope="col">Audience</th><th scope="col">Status</th><th scope="col">Recipients</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse ($announcements as $announcement)
                <tr>
                    <td>{{ $announcement->title }}<br><span class="meta">{{ \Illuminate\Support\Str::limit($announcement->body, 120) }}</span></td>
                    <td>{{ \App\Models\Communication\Announcement::AUDIENCES[$announcement->audience] }}</td>
                    <td>{{ ucfirst($announcement->status) }}@if ($announcement->sent_at) {{ $announcement->sent_at->format('j M Y') }} by {{ $announcement->sender?->name }}@endif</td>
                    <td>@if ($announcement->status === 'sent'){{ $announcement->recipients }}@else<span class="meta">Not sent</span>@endif</td>
                    <td>@if ($announcement->status === 'draft')
                            <div class="inline-form">
                                <form method="post" action="{{ route('admin.communications.send', $announcement) }}" data-confirm="Send “{{ $announcement->title }}” to {{ \App\Models\Communication\Announcement::AUDIENCES[$announcement->audience] }}?">@csrf <button type="submit" class="btn btn-primary btn-sm">Send<span class="visually-hidden"> {{ $announcement->title }}</span></button></form>
                                <form method="post" action="{{ route('admin.communications.destroy', $announcement) }}">@csrf @method('DELETE') <button type="submit" class="btn btn-secondary btn-sm">Delete<span class="visually-hidden"> {{ $announcement->title }}</span></button></form>
                            </div>
                        @endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No announcements yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <h2>Email delivery log</h2>
    <p class="hint">Last 7 days: {{ $counts['sent'] ?? 0 }} sent, {{ $counts['queued'] ?? 0 }} waiting, {{ $counts['failed'] ?? 0 }} failed. Failed emails were retried 3 times. The log keeps no message text or addresses.</p>
    <nav class="tags" aria-label="Filter deliveries">
        <a class="tag" href="{{ route('admin.communications.index') }}" @if (! $status) aria-current="true" @endif>All</a>
        @foreach (['queued' => 'Waiting', 'sent' => 'Sent', 'failed' => 'Failed'] as $value => $label)
            <a class="tag" href="{{ route('admin.communications.index', ['status' => $value]) }}" @if ($status === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Email delivery log">
        <table>
            <thead><tr><th scope="col">When</th><th scope="col">To</th><th scope="col">Type</th><th scope="col">Subject</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse ($deliveries as $delivery)
                <tr>
                    <td>{{ $delivery->created_at->format('j M Y H:i') }}</td>
                    <td>@if ($delivery->user){{ $delivery->user->name }}@else<span class="meta">Unknown</span>@endif</td>
                    <td>{{ \App\Communication\Notifier::types()[$delivery->type]['label'] ?? $delivery->type }}</td>
                    <td>{{ $delivery->subject }}</td>
                    <td><span class="badge badge-{{ $delivery->status === 'sent' ? 'on' : ($delivery->status === 'failed' ? 'off' : 'draft') }}">{{ ucfirst($delivery->status) }}</span>
                        @if ($delivery->error)<br><span class="meta">{{ \Illuminate\Support\Str::limit($delivery->error, 140) }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No emails logged.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $deliveries->links() }}
@endsection
