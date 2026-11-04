@extends('layouts.public', ['pageTitle' => 'Edit listing · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <p><a href="{{ route('workspace.marketplace', $startup) }}">← {{ $startup->display_name }} marketplace</a></p>
        <h1>{{ $listing->title }}</h1>
        @include('partials.flash')
        <p>Status: <span class="badge badge-{{ $listing->status === 'published' ? 'on' : ($listing->status === 'draft' ? 'draft' : 'off') }}">{{ \App\Models\Marketplace\Listing::STATUSES[$listing->status] }}</span>
            @if ($listing->isLive()) · <a href="{{ route('marketplace.show', $listing) }}">View public page</a>@endif</p>
        @if ($listing->status === 'removed')
            <div class="alert alert-warning" role="status">Hive took this listing down: {{ $listing->moderation_note }}. Contact Hive if you think this is a mistake.</div>
        @else
            <form method="post" action="{{ route('workspace.marketplace.status', [$startup, $listing]) }}" class="inline-form">
                @csrf
                @if ($listing->status !== 'published')<button type="submit" name="status" value="published" class="btn btn-primary">Publish</button>@endif
                @if ($listing->status === 'published')<button type="submit" name="status" value="closed" class="btn btn-secondary">Close</button>@endif
                @if ($listing->status !== 'draft')<button type="submit" name="status" value="draft" class="btn btn-secondary">Back to draft</button>@endif
            </form>
            <form method="post" action="{{ route('workspace.marketplace.update', [$startup, $listing]) }}">
                @csrf @method('PUT')
                @include('workspace.marketplace._form')
                <button type="submit" class="btn btn-primary">Save changes</button>
            </form>
        @endif
    </div>
@endsection
