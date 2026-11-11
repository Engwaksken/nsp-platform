@extends('layouts.app', ['pageTitle' => $inquiry->subject])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    @php($canWrite = in_array($side, ['inquirer', 'team'], true))
    <div class="container section narrow">
        <p>@if ($side === 'inquirer')<a href="{{ route('marketplace.inbox') }}">← My messages</a>@else<a href="{{ route('workspace.marketplace', $inquiry->startup_uuid) }}">← {{ $inquiry->startup?->display_name }} marketplace</a>@endif</p>
        <h1>{{ $inquiry->subject }}</h1>
        @include('partials.flash')
        <p class="meta">About @if ($inquiry->listing?->isLive())<a href="{{ route('marketplace.show', $inquiry->listing) }}">{{ $inquiry->listing->title }}</a>@else{{ $inquiry->listing?->title ?? 'a removed listing' }}@endif
            · between {{ $inquiry->inquirer?->name ?? 'a former user' }}@if ($inquiry->inquirerStartup) ({{ $inquiry->inquirerStartup->display_name }})@endif and {{ $inquiry->startup?->display_name }}
            · {{ $inquiry->status === 'open' ? 'Open' : 'Closed' }}</p>

        <ol class="plain-list" aria-label="Messages">
            @foreach ($inquiry->messages as $message)
                <li class="card">
                    <p class="meta">{{ $message->side === 'team' ? ($inquiry->startup?->display_name.' · '.($message->sender?->name ?? 'team member')) : ($message->sender?->name ?? 'former user') }}
                        · {{ $message->created_at->format('j M Y H:i') }}</p>
                    <p>{!! nl2br(e($message->body)) !!}</p>
                </li>
            @endforeach
        </ol>

        @if ($inquiry->status === 'open' && $canWrite)
            <form method="post" action="{{ route('marketplace.inquiries.reply', $inquiry) }}">
                @csrf
                @include('partials.field', ['name' => 'body', 'label' => 'Reply', 'type' => 'textarea', 'rows' => 4, 'required' => true])
                <button type="submit" class="btn btn-primary">Send</button>
            </form>
            <form method="post" action="{{ route('marketplace.inquiries.close', $inquiry) }}" class="inline-form" data-confirm="Close this conversation?">
                @csrf <button type="submit" class="btn btn-secondary btn-sm">Close conversation</button>
            </form>
        @elseif (! $canWrite)
            <p class="hint">You can read this conversation. Owners, founders and admins reply.</p>
        @endif

        @if ($canWrite)
            <details>
                <summary>Report a problem</summary>
                <p class="hint">Reporting lets Hive staff read this conversation so they can help.</p>
                <form method="post" action="{{ route('marketplace.inquiries.report', $inquiry) }}">
                    @csrf
                    @include('partials.field', ['name' => 'reason', 'id' => 'report-reason', 'label' => 'Reason', 'type' => 'select', 'required' => true, 'placeholder' => 'Choose…', 'options' => \App\Models\Marketplace\Report::REASONS])
                    @include('partials.field', ['name' => 'details', 'id' => 'report-details', 'label' => 'What happened?', 'type' => 'textarea', 'rows' => 3])
                    <button type="submit" class="btn btn-secondary btn-sm">Send report</button>
                </form>
            </details>
        @endif
    </div>
@endsection
