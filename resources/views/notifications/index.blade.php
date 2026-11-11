@extends('layouts.app', ['pageTitle' => 'Notifications'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>Notifications</h1>
        @include('partials.flash')
        <div class="inline-form">
            <nav class="tags" aria-label="Filter notifications">
                <a class="tag" href="{{ route('notifications.index') }}" @if ($show !== 'unread') aria-current="true" @endif>All</a>
                <a class="tag" href="{{ route('notifications.index', ['show' => 'unread']) }}" @if ($show === 'unread') aria-current="true" @endif>Unread</a>
            </nav>
            <form method="post" action="{{ route('notifications.read-all') }}">@csrf <button type="submit" class="btn btn-secondary btn-sm">Mark all as read</button></form>
            <a href="{{ route('notifications.preferences') }}">Choose what you receive</a>
        </div>

        <ul class="plain-list">
            @forelse ($notifications as $notification)
                <li class="card">
                    <p>@unless ($notification->read_at)<span class="badge badge-on">New</span> @endunless
                        <a href="{{ route('notifications.open', $notification) }}"><strong>{{ $notification->title }}</strong></a></p>
                    @if ($notification->body)<p>{{ $notification->body }}</p>@endif
                    <p class="meta">{{ \App\Communication\Notifier::types()[$notification->type]['label'] ?? $notification->type }} · {{ $notification->created_at->format('j M Y H:i') }}</p>
                </li>
            @empty
                <li class="empty">No notifications.</li>
            @endforelse
        </ul>
        {{ $notifications->links() }}
    </div>
@endsection
