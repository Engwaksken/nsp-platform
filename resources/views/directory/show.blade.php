@extends('layouts.public', ['pageTitle' => $startup->display_name, 'metaDescription' => \Illuminate\Support\Str::limit((string) $startup->description, 150)])

@section('content')
    <div class="container section narrow">
        <p><a href="{{ route('directory.index') }}">← Startup directory</a></p>
        <h1>{{ $startup->display_name }}</h1>
        {{-- Only the fields named in the "nsp_directory" consent. --}}
        <p class="meta">{{ $startup->profile?->sector ?: 'Sector not given' }}@if ($startup->profile?->headquarters_location) · {{ $startup->profile->headquarters_location }}@endif</p>
        @if ($startup->description)<p>{!! nl2br(e($startup->description)) !!}</p>@endif
        @if ($startup->website)<p><a href="{{ $startup->website }}" rel="noopener nofollow" target="_blank">{{ $startup->website }}</a></p>@endif

        @if ($listings->isNotEmpty())
            <h2>In the marketplace</h2>
            <ul>
                @foreach ($listings as $listing)
                    <li><a href="{{ route('marketplace.show', $listing) }}">{{ $listing->title }}</a> <span class="meta">{{ \App\Models\Marketplace\Listing::KINDS[$listing->kind] }}</span></li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
