@extends('layouts.public', ['pageTitle' => 'Venture Builder · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>Venture Builder · {{ $startup->display_name }}</h1>
        @include('workspace.startups.nav')
        @include('partials.flash')

        @if (!$curriculum)
            <section class="panel"><h2>Curriculum not published</h2><p>The Venture Builder will be available when a curriculum is published.</p></section>
        @elseif (!$enrollment)
            <section class="panel">
                @if ($curriculum->isProvisional())<p class="notice">Provisional curriculum — awaiting owner approval. Labels and content are a configurable proposal.</p>@endif
                <h2>{{ $curriculum->name }} · version {{ $curriculum->version }}</h2>
                <p>{{ $curriculum->description }}</p>
                @if ($membership->canEdit())
                    <form method="post" action="{{ route('workspace.builder.start', $startup) }}">@csrf<button class="btn btn-primary" type="submit">Start Venture Builder</button></form>
                @else
                    <p>Only startup owners, founders and admins can start Builder work.</p>
                @endif
                @foreach ($curriculum->modules->where('is_active', true)->sortBy('position') as $module)
                    <section><h3>{{ $module->title }}</h3><ol>@foreach ($module->steps->where('is_active', true)->sortBy('position') as $step)<li id="step-{{ $step->key }}">{{ $step->title }}</li>@endforeach</ol></section>
                @endforeach
            </section>
        @else
            @if ($curriculum->isProvisional())<p class="notice">Provisional curriculum — awaiting owner approval.</p>@endif
            <section class="panel" aria-label="Builder progress">
                <h2>Progress</h2><p>{{ $progress['done'] }} of {{ $progress['total'] }} steps validated or completed ({{ $progress['percent'] }}%).</p>
                <progress max="100" value="{{ $progress['percent'] }}" aria-label="Builder completion">{{ $progress['percent'] }}%</progress>
            </section>
            @foreach ($curriculum->modules->where('is_active', true)->sortBy('position') as $module)
                <section class="panel"><h2>{{ $module->position }}. {{ $module->title }}</h2>
                    @if ($module->description)<p>{{ $module->description }}</p>@endif
                    <ol>
                        @foreach ($module->steps->where('is_active', true)->sortBy('position') as $step)
                            @php($submission = $submissions->get($step->key))
                            @php($unlocked = $submission && app(\App\Builder\VentureBuilderService::class)->isUnlocked($submission))
                            <li id="step-{{ $step->key }}">
                                @if ($unlocked)
                                    <a href="{{ route('workspace.builder.step', [$startup, $submission]) }}">{{ $step->title }}</a>
                                @else
                                    <span>{{ $step->title }} <span aria-label="Locked">(locked — complete the previous step first)</span></span>
                                @endif
                                @if ($submission)<small> — {{ \App\Models\Builder\VentureStepSubmission::STATUSES[$submission->status] ?? $submission->status }}</small>@endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        @endif
    </div>
@endsection
