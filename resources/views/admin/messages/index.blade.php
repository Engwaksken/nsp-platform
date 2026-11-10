@extends('layouts.admin')
@section('title', 'Contact messages')

@section('content')
    <div class="page-head"><h1>Contact messages</h1></div>
    @include('partials.stats', ['label' => 'Summary', 'stats' => \App\Support\AdminStats::messages()])
    <nav class="filters" aria-label="Filter by status">
        <a class="tag" href="{{ route('admin.messages.index') }}" @if (!$status) aria-current="true" @endif>All</a>
        @foreach (\App\Models\ContactMessage::STATUSES as $option)
            <a class="tag" href="{{ route('admin.messages.index', ['status' => $option]) }}" @if ($status === $option) aria-current="true" @endif>{{ ucfirst(str_replace('_', ' ', $option)) }}</a>
        @endforeach
    </nav>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
        <table>
            <thead><tr><th scope="col">Received</th><th scope="col">Topic</th><th scope="col">From</th><th scope="col">Subject</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse ($messages as $message)
                <tr>
                    <td>{{ $message->created_at->format('j M Y H:i') }}</td>
                    <td>{{ \App\Models\ContactMessage::TOPICS[$message->topic] ?? $message->topic }}</td>
                    <td>{{ $message->name }}</td>
                    <td><a href="{{ route('admin.messages.show', $message) }}">{{ $message->subject }}</a></td>
                    <td><span class="badge badge-{{ $message->status }}">{{ ucfirst(str_replace('_', ' ', $message->status)) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No messages.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $messages->links() }}
@endsection
