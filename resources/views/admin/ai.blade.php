@extends('layouts.admin')
@section('title', 'AI assistant')

@section('content')
    <div class="page-head">
        <div>
            <h1>AI assistant</h1>
            <p>Optional writing help for founders, powered by Claude (Anthropic). Founders opt in one by one; only the text they type and their startup's name, sector and stage are sent.</p>
        </div>
    </div>

    @if ($errors->has('ai'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('ai') }}</div>
    @endif

    {{-- 1. Status and switch --}}
    <section class="panel" aria-labelledby="ai-status-heading">
        <h2 id="ai-status-heading">Status</h2>
        <ul class="setup-checklist">
            <li class="{{ $maskedKey ? 'is-done' : '' }}">API key {{ $maskedKey ? 'added ('.$maskedKey.($keySource === 'env' ? ', from .env' : '').')' : 'missing' }}</li>
            <li class="{{ $lastTest && $lastTest['ok'] ? 'is-done' : '' }}">
                Connection
                @if ($lastTest) {{ $lastTest['ok'] ? 'tested and working' : 'test failed' }} <span class="meta">({{ \Illuminate\Support\Carbon::parse($lastTest['at'])->diffForHumans() }})</span>
                @else not tested yet @endif
            </li>
            <li class="{{ $legal ? 'is-done' : '' }}">Legal review {{ $legal ? 'recorded' : 'not recorded' }}</li>
            <li class="{{ $enabled ? 'is-done' : '' }}">Assistant {{ $enabled ? 'switched on' : 'switched off' }}</li>
        </ul>
        <div class="actions">
            <form method="post" action="{{ route('admin.ai.toggle') }}">
                @csrf
                <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
                @if ($enabled)
                    <button type="submit" class="btn btn-secondary">Switch off</button>
                @else
                    <button type="submit" class="btn btn-primary" @disabled($blockedReason)>Switch on</button>
                @endif
            </form>
            <form method="post" action="{{ route('admin.ai.test') }}">
                @csrf
                <button type="submit" class="btn btn-secondary" @disabled(! $maskedKey)>Test connection</button>
            </form>
        </div>
        @if (! $enabled && $blockedReason)<p class="hint">{{ $blockedReason }}</p>@endif
        <p class="hint">"Test connection" checks the key and the model without generating text, so it uses no tokens.</p>
    </section>

    {{-- 2. Connection, model and limits --}}
    <form method="post" action="{{ route('admin.ai.update') }}" class="panel" aria-labelledby="ai-connection-heading">
        @csrf @method('PUT')
        <h2 id="ai-connection-heading">API key and model</h2>
        @include('partials.field', ['name' => 'api_key', 'label' => $maskedKey ? 'Replace API key' : 'Anthropic API key', 'type' => 'password', 'value' => '',
            'placeholder' => $maskedKey ? 'Leave empty to keep the current key ('.$maskedKey.')' : 'sk-ant-…', 'autocomplete' => 'new-password',
            'attrs' => 'spellcheck="false" autocapitalize="off"',
            'hint' => 'Create a key at console.anthropic.com → API keys. It is stored encrypted and never shown again; only its last four characters are displayed.'])
        @if ($keySource === 'admin')
            <label class="checkbox"><input type="checkbox" name="remove_key" value="1"> Remove the saved key{{ filled(config('nsp.ai.api_key')) ? ' (the key in .env will be used instead)' : ' (the assistant is switched off)' }}</label>
        @endif
        <div class="form-row">
            @include('partials.field', ['name' => 'model', 'label' => 'Model', 'type' => 'select', 'required' => true, 'value' => $config['model'],
                'options' => collect(\App\Ai\AiSettings::MODELS)->map(fn ($m) => $m['label'])->all(),
                'hint' => 'Price per million tokens in / out: '.collect(\App\Ai\AiSettings::MODELS)->map(fn ($m, $id) => \Illuminate\Support\Str::of($m['label'])->before(' (').' $'.(float) $m['input'].' / $'.(float) $m['output'])->implode(' · ').'.'])
            @include('partials.field', ['name' => 'effort', 'label' => 'Effort', 'type' => 'select', 'required' => true, 'value' => $config['effort'], 'options' => \App\Ai\AiSettings::EFFORTS,
                'hint' => 'How hard the model works on each request. Higher effort uses more tokens.'])
        </div>

        <h2>Daily limits</h2>
        <p class="hint">Each request counts once, whether it succeeds or not. Limits reset at midnight (server time).</p>
        <div class="form-row">
            @include('partials.field', ['name' => 'limit_user', 'label' => 'Per person', 'type' => 'number', 'required' => true, 'value' => $config['daily_limits']['user'], 'attrs' => 'min="1" max="1000"'])
            @include('partials.field', ['name' => 'limit_startup', 'label' => 'Per startup', 'type' => 'number', 'required' => true, 'value' => $config['daily_limits']['startup'], 'attrs' => 'min="1" max="10000"'])
            @include('partials.field', ['name' => 'limit_platform', 'label' => 'Whole platform', 'type' => 'number', 'required' => true, 'value' => $config['daily_limits']['platform'], 'attrs' => 'min="1" max="100000"'])
        </div>
        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>

    {{-- 3. Legal approval --}}
    <section class="panel" aria-labelledby="ai-legal-heading">
        <h2 id="ai-legal-heading">Legal review</h2>
        @if ($legal)
            <p>Recorded{{ $legal['by'] ? ' by '.$legal['by'] : '' }}{{ $legal['at'] ? ' on '.\Illuminate\Support\Carbon::parse($legal['at'])->format('j M Y') : '' }}.
                @if ($legal['reference'])<br><span class="meta">Reference: {{ $legal['reference'] }}</span>@endif</p>
            @if (filled(\App\Ai\AiSettings::stored()['legal_approved_at'] ?? null))
                <form method="post" action="{{ route('admin.ai.legal.withdraw') }}" data-confirm="Withdraw the legal approval? The assistant will be switched off.">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-secondary btn-sm">Withdraw approval</button>
                </form>
            @endif
        @else
            <p class="hint">Hive's legal team must approve the assistant (data sent to Anthropic, founder consent wording) before it is switched on (backlog AI-605).</p>
            <form method="post" action="{{ route('admin.ai.legal.approve') }}">
                @csrf
                @include('partials.field', ['name' => 'legal_reference', 'label' => 'Where is the approval recorded?', 'value' => '', 'required' => true, 'maxlength' => 255,
                    'placeholder' => 'e.g. Legal memo LM-2026-14, 12 Nov 2026'])
                <label class="checkbox"><input type="checkbox" name="confirm" value="1" required> I confirm the legal review is complete and approved.</label>
                @error('confirm')<p class="error">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-secondary">Record approval</button>
            </form>
        @endif
    </section>

    {{-- 4. Token usage --}}
    <section class="panel" aria-labelledby="ai-usage-heading">
        <h2 id="ai-usage-heading">Usage and tokens</h2>
        @foreach (['today' => 'Today', 'month' => 'Last 30 days'] as $period => $label)
            <h3>{{ $label }}</h3>
            <dl class="status-row">
                <div><dt>Requests</dt><dd>{{ number_format($usage[$period]['requests']) }}@if ($usage[$period]['failed'])<span class="meta"> ({{ $usage[$period]['failed'] }} failed)</span>@endif</dd></div>
                <div><dt>Input tokens</dt><dd>{{ number_format($usage[$period]['input_tokens']) }}</dd></div>
                <div><dt>Output tokens</dt><dd>{{ number_format($usage[$period]['output_tokens']) }}</dd></div>
                <div><dt>Estimated cost</dt><dd>US$ {{ number_format($usage[$period]['cost'], 2) }}</dd></div>
            </dl>
        @endforeach
        <p class="hint">Estimates use Anthropic's list prices. Your Anthropic console shows the exact bill.</p>
    </section>
@endsection
