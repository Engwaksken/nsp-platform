@extends('layouts.workspace', ['pageTitle' => 'AI assistant · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section narrow">
        <h1>AI writing assistant · {{ $startup->display_name }}</h1>
        @include('partials.flash')

        @if ($unavailable)
            <div class="alert alert-warning" role="status">{{ $unavailable }}</div>
        @elseif (! $membership->canEdit())
            <p>Owners, founders and admins of {{ $startup->display_name }} can use the assistant.</p>
        @elseif (! $consented)
            <section class="panel" aria-labelledby="consent-heading">
                <h2 id="consent-heading" class="h3">Before you start</h2>
                <ul>
                    <li>The assistant suggests wording and next steps. <strong>You decide what to use</strong>; nothing is saved to your profile automatically.</li>
                    <li>When you ask for help we send <strong>only</strong> the text you type in the box, plus your startup’s name, sector and growth stage, to {{ \App\Ai\Assistant::providerLabel() }}, the AI provider, to produce the suggestion.</li>
                    <li>The same switch turns on AI help on other pages, such as your growth results and Venture Builder steps. There we also send that page’s facts: your scores and the answers you chose, or your saved draft for the step.</li>
                    <li>We never send your documents, data room, notes, messages, contact details, financial figures or other people’s data.</li>
                    <li>NSP does not keep the text you type or the suggestion. We keep a count of requests to apply daily limits.</li>
                    <li>AI can be wrong. Check every suggestion before you use it. Do not type passwords, bank details or other people’s personal information.</li>
                </ul>
                <form method="post" action="{{ route('workspace.assistant.consent', $startup) }}">
                    @csrf <input type="hidden" name="grant" value="1">
                    <button type="submit" class="btn btn-primary">Turn on the assistant for my account</button>
                </form>
            </section>
        @else
            <form method="post" action="{{ route('workspace.assistant.run', $startup) }}">
                @csrf
                <fieldset class="form-group">
                    <legend class="label">What would you like help with?</legend>
                    @foreach (\App\Ai\Assistant::TASKS as $value => $definition)
                        <label class="checkbox"><input type="radio" name="task" value="{{ $value }}" @checked(old('task', $task) === $value) required>
                            <span><strong>{{ $definition['label'] }}</strong>: {{ $definition['hint'] }}</span></label>
                    @endforeach
                </fieldset>
                @include('partials.field', ['name' => 'text', 'label' => 'Your text', 'type' => 'textarea', 'rows' => 8, 'required' => true,
                    'value' => $text ?? ($defaults[old('task', $task)] ?? ''), 'hint' => 'Up to '.number_format(config('nsp.ai.max_input_chars')).' characters. Only this text and your startup’s name, sector and stage are sent.'])
                <button type="submit" class="btn btn-primary">Get a suggestion</button>
            </form>

            @if ($result)
                <section class="panel" aria-labelledby="result-heading" aria-live="polite">
                    <h2 id="result-heading" class="h3">Suggestion <span class="badge">AI-generated</span></h2>
                    @if ($result->ok())
                        <div class="ai-output">{!! nl2br(e($result->text)) !!}</div>
                        <p class="meta">Written by an AI model ({{ $result->model }}) on {{ now()->format('j M Y H:i') }}. Check the facts and edit it before you use it. Copy what you want into your profile or pitch yourself.</p>
                    @elseif ($result->status === 'refused')
                        <p>The assistant could not help with this request. Try rewording it.</p>
                    @else
                        <p>{{ $result->error ?? 'The assistant could not answer. Try again later.' }}</p>
                    @endif
                </section>
            @endif

            <form method="post" action="{{ route('workspace.assistant.consent', $startup) }}" class="inline-form">
                @csrf <input type="hidden" name="grant" value="0">
                <button type="submit" class="btn btn-secondary btn-sm">Turn off the assistant for my account</button>
            </form>
        @endif
    </div>
@endsection
