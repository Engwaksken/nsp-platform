@extends('layouts.admin')
@section('title', 'Announcements and email log')

@section('content')
    @php($audiences = \App\Models\Communication\Announcement::AUDIENCES)
    <div class="page-head"><h1>Announcements and email log</h1></div>
    @include('partials.flash')

    {{-- New announcement: a popup behind the button in the page head (a normal panel without JavaScript). --}}
    <section class="panel" id="new-announcement" data-inline-modal data-modal-trigger="New announcement" data-modal-trigger-into=".page-head" aria-labelledby="new-heading">
        <h2 id="new-heading">New announcement</h2>
        <p class="hint">Everyone in the audience sees it on NSP unless they turned that off. Only people who opted in to "News from Hive" get it by email.</p>
        <form method="post" action="{{ route('admin.communications.store') }}">
            @csrf
            @include('admin.communications._form', ['announcement' => null])
            <button type="submit" class="btn btn-primary">Save draft</button>
        </form>
    </section>

    <div data-tabs="Communications" data-tabs-key="admin-communications">
        <section id="announcements" aria-labelledby="announcements-heading" data-tab="Announcements" data-tab-icon="fa-bullhorn" data-tab-count="{{ $announcements->count() }}">
            <h2 id="announcements-heading" class="visually-hidden">Announcements</h2>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Announcements">
                <table>
                    <thead><tr><th scope="col">Title</th><th scope="col">Audience</th><th scope="col">Status</th><th scope="col">Recipients</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    @forelse ($announcements as $announcement)
                        <tr>
                            <td>{{ $announcement->title }}<br><span class="meta">{{ \Illuminate\Support\Str::limit($announcement->body, 120) }}</span></td>
                            <td>{{ $audiences[$announcement->audience] }}</td>
                            <td><span class="badge {{ $announcement->status === 'sent' ? 'badge-on' : 'badge-draft' }}">{{ ucfirst($announcement->status) }}</span>
                                @if ($announcement->sent_at)<br><span class="meta">{{ $announcement->sent_at->format('j M Y') }} by {{ $announcement->sender?->name }}</span>@endif</td>
                            <td>@if ($announcement->status === 'sent'){{ $announcement->recipients }}@else<span class="meta">Not sent</span>@endif</td>
                            <td>@if ($announcement->status === 'draft')
                                    <div class="icon-actions">
                                        <a class="btn btn-icon" href="{{ route('admin.communications.edit', $announcement) }}" data-modal data-tip="Edit"><i class="fas fa-pen-to-square" aria-hidden="true"></i><span class="visually-hidden">Edit {{ $announcement->title }}</span></a>
                                        <form method="post" action="{{ route('admin.communications.send', $announcement) }}" data-confirm="Send &quot;{{ $announcement->title }}&quot; to {{ $audiences[$announcement->audience] }}?">@csrf <button type="submit" class="btn btn-icon btn-icon-primary" data-tip="Send"><i class="fas fa-paper-plane" aria-hidden="true"></i><span class="visually-hidden">Send {{ $announcement->title }}</span></button></form>
                                        <form method="post" action="{{ route('admin.communications.destroy', $announcement) }}" data-confirm="Delete the draft &quot;{{ $announcement->title }}&quot;?">@csrf @method('DELETE') <button type="submit" class="btn btn-icon btn-icon-danger" data-tip="Delete"><i class="fas fa-trash-can" aria-hidden="true"></i><span class="visually-hidden">Delete {{ $announcement->title }}</span></button></form>
                                    </div>
                                @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No announcements yet. Use "New announcement" to write one.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section id="deliveries" aria-labelledby="deliveries-heading" data-tab="Email delivery log" data-tab-icon="fa-envelope-circle-check" data-tab-count="{{ $deliveries->total() }}">
            <h2 id="deliveries-heading" class="visually-hidden">Email delivery log</h2>
            <p class="hint">Last 7 days: {{ $counts['sent'] ?? 0 }} sent, {{ $counts['queued'] ?? 0 }} waiting, {{ $counts['failed'] ?? 0 }} failed. Failed emails were retried 3 times. The log keeps no message text or addresses.</p>
            <nav class="tags" aria-label="Filter deliveries">
                <a class="tag" href="{{ route('admin.communications.index') }}#deliveries" @if (! $status) aria-current="true" @endif>All</a>
                @foreach (['queued' => 'Waiting', 'sent' => 'Sent', 'failed' => 'Failed'] as $value => $label)
                    <a class="tag" href="{{ route('admin.communications.index', ['status' => $value]) }}#deliveries" @if ($status === $value) aria-current="true" @endif>{{ $label }}</a>
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
            {{ $deliveries->fragment('deliveries')->links() }}
        </section>
    </div>
@endsection
