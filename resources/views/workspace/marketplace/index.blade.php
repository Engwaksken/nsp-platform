@extends('layouts.workspace', ['pageTitle' => 'Marketplace · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section">
        <h1>Marketplace · {{ $startup->display_name }}</h1>
        @include('partials.flash')
        <p class="hint">Published listings appear in the public <a href="{{ route('marketplace.index') }}">marketplace</a> with your startup’s name and sector. To appear in the <a href="{{ route('directory.index') }}">startup directory</a> too, turn on “{{ config('nsp.registry.consent_purposes.nsp_directory.label') }}” under Data sharing.</p>

        @include('partials.stats', ['label' => 'Marketplace at a glance', 'stats' => [
            ['icon' => 'fa-store', 'tone' => 'green', 'label' => 'Published listings', 'value' => $listings->where('status', 'published')->count()],
            ['icon' => 'fa-pen-to-square', 'tone' => 'grey', 'label' => 'Drafts', 'value' => $listings->where('status', 'draft')->count()],
            ['icon' => 'fa-comments', 'tone' => 'blue', 'label' => 'Open conversations', 'value' => $inquiries->where('status', 'open')->count()],
            ['icon' => 'fa-envelope', 'tone' => 'red', 'label' => 'Unread', 'value' => $inquiries->filter(fn ($inquiry) => $inquiry->unreadFor('team'))->count()],
        ]])

        <div data-tabs="Marketplace sections" data-tabs-key="marketplace">
        <section class="panel" aria-labelledby="conv-heading" data-tab="Messages" data-tab-icon="fa-comments" data-tab-count="{{ $inquiries->count() }}">
            <h2 id="conv-heading">Messages</h2>
            <ul class="plain-list">
                @forelse ($inquiries as $inquiry)
                    <li>
                        <a href="{{ route('marketplace.inquiries.show', $inquiry) }}">{{ $inquiry->subject }}</a>
                        @if ($inquiry->unreadFor('team'))<span class="badge badge-on">New</span>@endif
                        <span class="meta">· {{ $inquiry->inquirer?->name ?? 'former user' }}@if ($inquiry->inquirerStartup) ({{ $inquiry->inquirerStartup->display_name }})@endif
                            · {{ $inquiry->listing?->title }} · {{ $inquiry->status === 'open' ? 'Open' : 'Closed' }} · {{ $inquiry->last_message_at?->format('j M Y') }}</span>
                    </li>
                @empty
                    <li class="empty">No messages yet.</li>
                @endforelse
            </ul>
        </section>

        <section data-tab="Listings" data-tab-icon="fa-store" data-tab-count="{{ $listings->count() }}" aria-labelledby="listings-heading">
        <h2 id="listings-heading">Listings</h2>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Listings">
            <table>
                <thead><tr><th scope="col">Listing</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Conversations</th></tr></thead>
                <tbody>
                @forelse ($listings as $item)
                    <tr>
                        <td>@if ($membership->canEdit())<a href="{{ route('workspace.marketplace.edit', [$startup, $item]) }}">{{ $item->title }}</a>@else{{ $item->title }}@endif
                            @if ($item->status === 'removed' && $item->moderation_note)<br><span class="meta">Hive: {{ $item->moderation_note }}</span>@endif</td>
                        <td>{{ \App\Models\Marketplace\Listing::KINDS[$item->kind] }}</td>
                        <td><span class="badge badge-{{ $item->status === 'published' ? 'on' : ($item->status === 'draft' ? 'draft' : 'off') }}">{{ \App\Models\Marketplace\Listing::STATUSES[$item->status] }}</span></td>
                        <td>{{ $item->inquiries_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No listings yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        </section>

        @if ($membership->canEdit())
            <section class="panel" aria-labelledby="new-heading" data-tab="New listing" data-tab-icon="fa-plus">
                <h2 id="new-heading">New listing</h2>
                <form method="post" action="{{ route('workspace.marketplace.store', $startup) }}">
                    @csrf
                    @include('workspace.marketplace._form')
                    <button type="submit" class="btn btn-primary">Save as draft</button>
                </form>
            </section>
        @endif
        </div>
    </div>
@endsection
