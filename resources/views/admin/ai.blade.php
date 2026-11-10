@extends('layouts.admin')
@section('title', 'AI assistant')

@section('content')
    <div class="page-head">
        <div>
            <h1>AI assistant</h1>
            <p>Optional writing help for founders, powered by an administrator-selected AI provider. Founders opt in one by one; only the text they type and their startup's name, sector and stage are sent.</p>
        </div>
    </div>

    @if ($errors->has('ai'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('ai') }}</div>
    @endif
    @php($selectedProvider = old('provider', $config['provider']))

    <div data-tabs="AI administration" data-tabs-key="ai-admin">
    <section class="panel" data-tab="Status" aria-labelledby="ai-status-heading">
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
    <form method="post" action="{{ route('admin.ai.update') }}" class="panel" data-tab="Provider and model" data-ai-provider-form data-model-catalog="{{ json_encode($modelsByProvider) }}" data-default-model="{{ $config['model'] }}" aria-labelledby="ai-connection-heading">
        @csrf @method('PUT')
        <h2 id="ai-connection-heading">API key and model</h2>
        @include('partials.field', ['name' => 'provider', 'label' => 'Provider', 'type' => 'select', 'required' => true, 'value' => old('provider', $config['provider']), 'options' => $providers])
        @include('partials.field', ['name' => 'api_key', 'label' => $maskedKey ? 'Replace API key' : $providers[$config['provider']].' API key', 'type' => 'password', 'value' => '',
            'placeholder' => $maskedKey ? 'Leave empty to keep the current key ('.$maskedKey.')' : 'sk-ant-…', 'autocomplete' => 'new-password',
            'attrs' => 'spellcheck="false" autocapitalize="off"',
            'hint' => 'The key is stored encrypted and never shown again; only its last four characters are displayed.'])
        @if ($keySource === 'admin')
            <label class="checkbox"><input type="checkbox" name="remove_key" value="1"> Remove the saved key{{ filled(config('nsp.ai.api_key')) ? ' (the key in .env will be used instead)' : ' (the assistant is switched off)' }}</label>
        @endif
        <div data-custom-provider-field @if ($selectedProvider !== 'custom') hidden @endif>
            @include('partials.field', ['name' => 'base_url', 'label' => 'OpenAI-compatible API base URL', 'type' => 'url', 'value' => $config['base_url'], 'placeholder' => 'https://api.example.com/v1', 'hint' => 'HTTPS endpoint implementing /chat/completions.'])
            @include('partials.field', ['name' => 'custom_model', 'label' => 'Custom model ID', 'value' => old('custom_model', $selectedProvider === 'custom' ? $config['model'] : ''), 'placeholder' => 'e.g. provider-model-v1'])
        </div>
        <div class="form-row">
            <div data-curated-model-field @if ($selectedProvider === 'custom') hidden @endif>
                @include('partials.field', ['name' => 'model', 'label' => 'Model', 'type' => 'select', 'required' => true, 'value' => old('model', $config['model']), 'options' => $models])
            </div>
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
    <section class="panel" data-tab="Legal approval" aria-labelledby="ai-legal-heading">
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
            <p class="hint">Hive's legal team must approve the assistant (including the selected provider and founder consent wording) before it is switched on.</p>
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
    <section class="panel" data-tab="Usage statistics" aria-labelledby="ai-usage-heading">
        <h2 id="ai-usage-heading">Usage and tokens</h2>
        @foreach (['today' => 'Today', 'month' => 'Last 30 days'] as $period => $label)
            <h3>{{ $label }}</h3>
            @include('partials.stats', ['label' => $label.' AI usage', 'stats' => [
                ['icon' => 'fa-wand-magic-sparkles', 'label' => 'Requests', 'value' => $usage[$period]['requests'], 'tone' => 'blue', 'note' => $usage[$period]['failed'].' failed'],
                ['icon' => 'fa-arrow-right-to-bracket', 'label' => 'Input tokens', 'value' => number_format($usage[$period]['input_tokens']), 'tone' => 'purple'],
                ['icon' => 'fa-arrow-right-from-bracket', 'label' => 'Output tokens', 'value' => number_format($usage[$period]['output_tokens']), 'tone' => 'green'],
                ['icon' => 'fa-coins', 'label' => 'Estimated cost', 'value' => 'US$ '.number_format($usage[$period]['cost'], 2), 'tone' => 'amber'],
            ]])
        @endforeach
        <p class="hint">@if ($config['provider'] === 'custom') Cost estimates are approximate for custom model IDs. @else Estimated costs use the configured model's published list rates. @endif The provider console shows the exact bill.</p>
    </section>
    </div>
@endsection
