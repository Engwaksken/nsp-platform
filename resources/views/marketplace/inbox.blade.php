@extends('layouts.app', ['pageTitle' => 'My marketplace messages'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>My marketplace messages</h1>
        @include('partials.flash')
        <p class="hint">Conversations you started. Messages to your own startup’s listings are in your startup’s Marketplace tab.</p>
        <ul class="plain-list">
            @forelse ($inquiries as $inquiry)
                <li class="card">
                    <a href="{{ route('marketplace.inquiries.show', $inquiry) }}">{{ $inquiry->subject }}</a>
                    @if ($inquiry->unreadFor('inquirer'))<span class="badge badge-on">New reply</span>@endif
                    <span class="meta">· {{ $inquiry->startup?->display_name }} · {{ $inquiry->status === 'open' ? 'Open' : 'Closed' }} · {{ $inquiry->last_message_at?->format('j M Y') }}</span>
                </li>
            @empty
                <li class="empty">No conversations yet. <a href="{{ route('marketplace.index') }}">Browse the marketplace</a></li>
            @endforelse
        </ul>
    </div>
@endsection
