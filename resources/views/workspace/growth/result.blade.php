@extends('layouts.public', ['pageTitle' => 'Growth results · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <div class="page-head">
            <h1>Growth results · {{ $startup->display_name }}</h1>
            <a class="btn btn-secondary" href="{{ route('workspace.growth.report', [$startup, $assessment]) }}">Printable report</a>
        </div>
        @include('workspace.startups.nav')
        @include('partials.flash')
        <p class="meta">Submitted by {{ $assessment->submitter?->name ?? 'a team member' }} on {{ $assessment->submitted_at->format('j M Y') }}.
            {{ $assessment->result['band']['description'] ?? '' }}</p>

        @include('workspace.growth.scores')

        <section class="panel" aria-labelledby="gaps-heading">
            <h2 id="gaps-heading">Where to focus next</h2>
            @if (empty($assessment->result['gaps']))
                <p>No weak areas: every answer scored 50 or more. Keep reassessing every few months.</p>
            @else
                <p class="hint">Suggested from your answers, weakest first. Each suggestion explains which answer it comes from.</p>
                @foreach ($assessment->result['gaps'] as $gap)
                    <div class="card" style="margin-bottom:.75rem">
                        <p class="meta">{{ $gap['dimension_title'] }} · score {{ $gap['score'] }}</p>
                        <p><strong>{{ $gap['tip'] ?: 'Improve this area' }}</strong></p>
                        <p class="meta">Because you answered “{{ $gap['answer'] }}” to “{{ $gap['prompt'] }}”</p>
                        @if (!empty($builderLinks[$gap['dimension_key'] ?? '']))
                            <p><a href="{{ $builderLinks[$gap['dimension_key']] }}">Open related Venture Builder step</a></p>
                        @endif
                        @php($recommendationFeedback = $guidanceFeedback[$gap['dimension_key']] ?? null)
                        @if ($recommendationFeedback)
                            <p class="meta">Your guidance feedback: {{ ucfirst($recommendationFeedback->feedback) }}@if ($recommendationFeedback->explanation) — {{ $recommendationFeedback->explanation }}@endif</p>
                        @endif
                        @if ($membership->canEdit())
                            <div class="actions" aria-label="Guidance feedback for {{ $gap['dimension_title'] }}">
                                <form method="post" action="{{ route('workspace.growth.guidance.feedback', [$startup, $assessment, $gap['dimension_key']]) }}">
                                    @csrf
                                    <input type="hidden" name="feedback" value="accepted">
                                    <button type="submit" class="btn btn-secondary btn-sm">Accept suggestion</button>
                                </form>
                                <form method="post" action="{{ route('workspace.growth.guidance.feedback', [$startup, $assessment, $gap['dimension_key']]) }}" class="actions">
                                    @csrf
                                    <input type="hidden" name="feedback" value="dismissed">
                                    <label for="guidance-reason-{{ $gap['dimension_key'] }}" class="visually-hidden">Why is this suggestion not relevant?</label>
                                    <input id="guidance-reason-{{ $gap['dimension_key'] }}" name="explanation" type="text" minlength="5" maxlength="1000" placeholder="Why not relevant?" required>
                                    <button type="submit" class="btn btn-secondary btn-sm">Dismiss with reason</button>
                                </form>
                            </div>
                        @endif
                        @if (in_array($gap['question_key'], $planned, true))
                            <span class="badge badge-on">In your growth plan</span>
                        @elseif ($membership->canEdit())
                            <form method="post" action="{{ route('workspace.growth.actions.store', $startup) }}">
                                @csrf
                                <input type="hidden" name="assessment_uuid" value="{{ $assessment->uuid }}">
                                <input type="hidden" name="question_key" value="{{ $gap['question_key'] }}">
                                <button type="submit" class="btn btn-secondary btn-sm">Add to growth plan</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            @endif
        </section>

        <details class="panel">
            <summary>Your answers</summary>
            @foreach ($definition['dimensions'] as $dimension)
                <h3>{{ $dimension['title'] }}</h3>
                <ul>
                    @foreach ($dimension['questions'] as $question)
                        @php($response = $responses->get($question['key']))
                        @php($option = collect($question['options'])->firstWhere('key', $response?->option_key))
                        <li>{{ $question['prompt'] }} <strong>{{ $option['label'] ?? '—' }}</strong>
                            @if ($response?->evidence_note)<br><span class="meta">Evidence: {{ $response->evidence_note }}</span>@endif</li>
                    @endforeach
                </ul>
            @endforeach
        </details>
    </div>
@endsection
