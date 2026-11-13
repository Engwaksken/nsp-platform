{{--
    AI help in context, using the provider and model an admin made active in Admin → AI assistant.
    @include('partials.ai-panel', [
        'startup' => $startup,
        'id' => 'ai-growth',                       // unique on the page; also the #anchor after a no-JS answer
        'subject' => $assessment->uuid,            // the page's record; the server rebuilds the context from it
        'tasks' => ['growth_explain'],             // one-click tasks from Assistant::CONTEXT_TASKS
        'question' => 'growth_question',           // optional: a task that takes the founder's typed question
        'gap' => $gap['question_key'],             // optional: for growth_gap
        'title' => 'AI insights', 'intro' => '…', // optional
        'compact' => true,                         // optional: a single small button, for cards
    ])
    Shows nothing unless the AI assistant is switched on and set up, and the viewer can edit the startup.
    Answers are AI-generated plain text (escaped), shown once and never stored.
--}}
@php
    $user = auth()->user();
    $available = \Illuminate\Support\Facades\Route::has('workspace.assistant.ask') && \App\Ai\Assistant::availableFor($startup, $user);
    $tasks = $tasks ?? [];
    $question = $question ?? null;
    $compact = $compact ?? false;
@endphp
@if ($available)
    @php
        $consented = app(\App\Ai\Assistant::class)->hasConsent($user);
        $flash = session('ai_result');
        $flash = is_array($flash) && ($flash['panel'] ?? null) === $id ? $flash : null;
        $error = old('panel') === $id ? $errors->first('assistant') : null;
        $provider = \App\Ai\Assistant::providerLabel();
    @endphp
    @if (! $compact || $consented)
    <section class="ai-panel {{ $compact ? 'is-compact' : '' }}" id="{{ $id }}" aria-label="{{ $title ?? 'AI help' }}">
        @unless ($compact)
            <div class="ai-panel-head">
                <span class="ai-panel-icon" aria-hidden="true"><i class="fas fa-wand-magic-sparkles"></i></span>
                <div>
                    <h2 class="h3">{{ $title ?? 'AI help' }} <span class="badge badge-scheduled">AI</span></h2>
                    @if (! empty($intro))<p class="meta">{{ $intro }}</p>@endif
                </div>
            </div>
        @endunless

        @if (! $consented)
            @unless ($compact)
                <div class="ai-consent">
                    <p>AI help is off for your account. When you ask, we send this page’s facts (for example scores and the answers you chose, or your saved draft) and anything you type, plus your startup’s name, sector and stage, to <strong>{{ $provider }}</strong> to write the answer. We never send documents, evidence, messages, contact details or other people’s data, and we don’t keep what is generated. AI can be wrong: check before you use it.</p>
                    <form method="post" action="{{ route('workspace.assistant.consent', $startup) }}">
                        @csrf <input type="hidden" name="grant" value="1">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-toggle-on" aria-hidden="true"></i> Turn on AI help for my account</button>
                    </form>
                </div>
            @endunless
        @else
            <form method="post" action="{{ route('workspace.assistant.ask', $startup) }}" class="ai-form" data-ai-form>
                @csrf
                <input type="hidden" name="subject" value="{{ $subject }}">
                <input type="hidden" name="panel" value="{{ $id }}">
                @if (! empty($gap))<input type="hidden" name="gap" value="{{ $gap }}">@endif
                @if ($tasks)
                    <div class="ai-actions">
                        @foreach ($tasks as $task)
                            <button type="submit" name="task" value="{{ $task }}" class="btn {{ $compact ? 'btn-secondary btn-sm' : 'btn-primary' }}">
                                <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> {{ $labels[$task] ?? \App\Ai\Assistant::CONTEXT_TASKS[$task]['label'] }}</button>
                        @endforeach
                    </div>
                @endif
                @if ($question)
                    <div class="ai-question">
                        <label for="{{ $id }}-text" class="visually-hidden">{{ \App\Ai\Assistant::CONTEXT_TASKS[$question]['label'] }}</label>
                        <textarea id="{{ $id }}-text" name="text" rows="2" maxlength="{{ config('nsp.ai.max_input_chars') }}" placeholder="{{ $placeholder ?? 'Ask a question…' }}">{{ old('panel') === $id ? old('text') : '' }}</textarea>
                        <button type="submit" name="task" value="{{ $question }}" class="btn btn-secondary"><i class="fas fa-paper-plane" aria-hidden="true"></i> Ask</button>
                    </div>
                @endif
            </form>
            <div class="ai-output" data-ai-output aria-live="polite" @if (! $flash && ! $error) hidden @endif>
                @if ($error)
                    <p class="ai-error"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $error }}</p>
                @elseif ($flash)
                    @if ($flash['text'])
                        <div class="ai-text">{{ $flash['text'] }}</div>
                        <p class="ai-meta"><i class="fas fa-robot" aria-hidden="true"></i> AI-generated by {{ $flash['model'] }} on {{ $flash['generated_at'] }}. Check the facts before you use it.</p>
                    @else
                        <p class="ai-error"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $flash['message'] }}</p>
                    @endif
                @endif
            </div>
            @unless ($compact)
                <p class="ai-footnote meta">Sent to {{ $provider }} · not stored · <a href="{{ route('workspace.assistant', $startup) }}">AI assistant settings</a></p>
            @endunless
        @endif
    </section>
    @endif
@endif
