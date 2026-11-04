@extends('layouts.public', ['pageTitle' => $listing->title.' · '.$listing->startup->display_name, 'metaDescription' => $listing->summary])

@section('content')
    <div class="container section narrow">
        <p><a href="{{ route('marketplace.index') }}">← Marketplace</a></p>
        <p><span class="badge">{{ \App\Models\Marketplace\Listing::KINDS[$listing->kind] }}</span></p>
        <h1>{{ $listing->title }}</h1>
        @include('partials.flash')
        <p class="meta">{{ $listing->startup->display_name }}@if ($listing->startup->profile?->sector) · {{ $listing->startup->profile->sector }}@endif
            · {{ \App\Models\Marketplace\Listing::categories()[$listing->category] ?? $listing->category }}@if ($listing->location) · {{ $listing->location }}@endif
            @if ($listing->expires_on) · until {{ $listing->expires_on->format('j M Y') }}@endif</p>
        <p><strong>{{ $listing->summary }}</strong></p>
        <p>{!! nl2br(e($listing->description)) !!}</p>
        @if ($listing->price_note)<p>Price: {{ $listing->price_note }}</p>@endif

        <section class="panel" aria-labelledby="contact-heading">
            <h2 id="contact-heading">Contact {{ $listing->startup->display_name }}</h2>
            @guest
                <p><a class="btn btn-primary" href="{{ route('login') }}">Sign in to send a message</a></p>
            @else
                @if ($isOwnTeam)
                    <p>This is your startup’s listing. Messages about it are in your <a href="{{ route('workspace.marketplace', $listing->startup) }}">workspace</a>.</p>
                @elseif ($openInquiry)
                    <p>You already have a conversation about this listing. <a href="{{ route('marketplace.inquiries.show', $openInquiry) }}">Open it</a></p>
                @else
                    <form method="post" action="{{ route('marketplace.inquire', $listing) }}">
                        @csrf
                        @include('partials.field', ['name' => 'subject', 'label' => 'Subject', 'required' => true, 'maxlength' => 150, 'value' => 'About: '.$listing->title])
                        @include('partials.field', ['name' => 'body', 'label' => 'Message', 'type' => 'textarea', 'rows' => 5, 'required' => true, 'hint' => 'Messages stay on NSP. Your email address is not shared unless you write it here.'])
                        @if ($myStartups)
                            @include('partials.field', ['name' => 'on_behalf_of', 'label' => 'Writing on behalf of', 'type' => 'select', 'placeholder' => 'Myself', 'options' => $myStartups])
                        @endif
                        <button type="submit" class="btn btn-primary">Send message</button>
                    </form>
                @endif
            @endguest
        </section>

        @auth
            @unless ($isOwnTeam)
                <details>
                    <summary>Report this listing</summary>
                    <form method="post" action="{{ route('marketplace.report', $listing) }}">
                        @csrf
                        @include('partials.field', ['name' => 'reason', 'id' => 'report-reason', 'label' => 'Reason', 'type' => 'select', 'required' => true, 'placeholder' => 'Choose…', 'options' => \App\Models\Marketplace\Report::REASONS])
                        @include('partials.field', ['name' => 'details', 'id' => 'report-details', 'label' => 'Details (optional)', 'type' => 'textarea', 'rows' => 3])
                        <button type="submit" class="btn btn-secondary btn-sm">Send report</button>
                    </form>
                </details>
            @endunless
        @endauth
    </div>
@endsection
