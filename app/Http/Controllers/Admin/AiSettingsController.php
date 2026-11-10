<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AiClient;
use App\Ai\AiSettings;
use App\Http\Controllers\Controller;
use App\Models\Ai\AiInteraction;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin → AI assistant: API key, model, limits, legal approval, connection test, switch and token usage. */
class AiSettingsController extends Controller
{
    public function edit(): View
    {
        $config = AiSettings::config();
        $stored = AiSettings::stored();
        $provider = $config['provider'];
        $models = [];
        $modelsByProvider = [];
        foreach (AiSettings::PROVIDERS as $providerKey => $providerDefinition) {
            foreach ($providerDefinition['models'] as $modelId => $modelDefinition) {
                $models[$modelId] = $providerDefinition['label'].' · '.$modelDefinition['label'];
                $modelsByProvider[$providerKey][$modelId] = $modelDefinition['label'];
            }
        }
        if ($provider === 'custom' && filled($config['model'])) {
            $models[$config['model']] = 'Custom · '.$config['model'];
        }

        return view('admin.ai', [
            'config' => $config,
            'providers' => collect(AiSettings::PROVIDERS)->map(fn ($p) => $p['label'])->all(),
            'models' => $models,
            'modelsByProvider' => $modelsByProvider,
            'keySource' => AiSettings::keySource(),
            'maskedKey' => AiSettings::maskedKey(),
            'legal' => AiSettings::legalApproval(),
            'enabled' => Features::enabled('ai_assistant'),
            'blockedReason' => FeatureFlagController::blockedReason('ai_assistant'),
            'lastTest' => filled($stored['last_test_at'] ?? null)
                ? ['at' => $stored['last_test_at'], 'ok' => ($stored['last_test_ok'] ?? '0') === '1', 'message' => $stored['last_test_message'] ?? null]
                : null,
            'usage' => [
                'today' => $this->usage(now()->startOfDay()),
                'month' => $this->usage(now()->subDays(30)),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $provider = $request->input('provider', AiSettings::config()['provider']);
        $request->merge(['provider' => $provider]);
        if (! isset(AiSettings::PROVIDERS[$provider])) {
            return back()->withErrors(['provider' => 'Choose a supported AI provider.'])->withInput();
        }
        $modelRule = ['required', Rule::in(array_keys(AiSettings::PROVIDERS[$provider]['models']))];
        if ($provider === 'custom') {
            $modelRule = ['nullable'];
        }
        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys(AiSettings::PROVIDERS))],
            'api_key' => ['nullable', 'string', 'min:20', 'max:300', 'regex:/^\S+$/'],
            'remove_key' => ['nullable', 'boolean'],
            'model' => $modelRule,
            'custom_model' => [Rule::requiredIf($provider === 'custom'), 'nullable', 'string', 'regex:/^[a-zA-Z0-9._:-]{1,100}$/'],
            'base_url' => [Rule::requiredIf($provider === 'custom'), 'nullable', 'url:https', 'max:255'],
            'effort' => ['required', Rule::in(array_keys(AiSettings::EFFORTS))],
            'limit_user' => ['required', 'integer', 'min:1', 'max:1000'],
            'limit_startup' => ['required', 'integer', 'min:1', 'max:10000'],
            'limit_platform' => ['required', 'integer', 'min:1', 'max:100000'],
        ], ['api_key.regex' => 'The API key cannot contain spaces.', 'api_key.min' => 'That does not look like a full API key.']);
        if ($provider === 'custom') {
            $data['model'] = $data['custom_model'];
            if (! $this->isSafeCustomEndpoint($data['base_url'])) {
                return back()->withErrors(['base_url' => 'Use a publicly reachable HTTPS API host. Private, local, and IP-literal endpoints are not allowed.'])->withInput($request->except('api_key'));
            }
        }

        $values = [
            'provider' => $provider, 'model' => $data['model'], 'effort' => $data['effort'],
            'limit_user' => (string) $data['limit_user'], 'limit_startup' => (string) $data['limit_startup'], 'limit_platform' => (string) $data['limit_platform'],
        ];
        if ($provider === 'custom') {
            $values['base_url'] = rtrim($data['base_url'] ?? '', '/');
        }
        $keyChange = null;
        if (filled($data['api_key'] ?? null)) {
            $values[$provider === 'anthropic' ? 'api_key' : 'api_key_'.$provider] = trim($data['api_key']);
            $keyChange = 'replaced';
        } elseif ($request->boolean('remove_key')) {
            $values[$provider === 'anthropic' ? 'api_key' : 'api_key_'.$provider] = null;
            $keyChange = 'removed';
        }
        if ($keyChange) {
            // A new or removed key makes the last connection test meaningless.
            $values += ['last_test_at' => null, 'last_test_ok' => null, 'last_test_message' => null];
        }
        AiSettings::put($values);

        // Without a key the assistant cannot run, so it is switched off with it.
        if ($keyChange === 'removed' && ! AiSettings::config()['api_key'] && Features::enabled('ai_assistant')) {
            Features::set('ai_assistant', false, $request->user()->id);
        }
        // The key itself is never written to the audit log.
        Audit::log('ai.settings_changed', 'ai_settings', array_filter([
            'provider' => $provider, 'model' => $data['model'], 'effort' => $data['effort'], 'key' => $keyChange,
            'limits' => [$data['limit_user'], $data['limit_startup'], $data['limit_platform']],
        ]));

        return redirect()->route('admin.ai.edit')->with('status', $keyChange === 'replaced'
            ? 'Settings saved. Use "Test connection" to check the new key.'
            : 'Settings saved.');
    }

    public function test(): RedirectResponse
    {
        // Resolved here so it uses the settings just saved.
        $error = app(AiClient::class)->check();
        AiSettings::put(['last_test_at' => now()->toDateTimeString(), 'last_test_ok' => $error ? '0' : '1', 'last_test_message' => $error]);
        Audit::log('ai.connection_tested', 'ai_settings', ['ok' => $error === null]);

        return $error
            ? redirect()->route('admin.ai.edit')->withErrors(['ai' => $error])
            : redirect()->route('admin.ai.edit')->with('status', 'Connection works: the key is accepted and the model "'.AiSettings::config()['model'].'" is available.');
    }

    public function approveLegal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'legal_reference' => ['required', 'string', 'max:255'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Confirm that the legal review is complete.', 'legal_reference.required' => 'Say where the approval is recorded, for example the memo or ticket.']);
        AiSettings::put([
            'legal_approved_at' => now()->toDateTimeString(),
            'legal_approved_by' => $request->user()->name,
            'legal_reference' => $data['legal_reference'],
        ]);
        Audit::log('ai.legal_approval_recorded', 'ai_settings', ['reference' => $data['legal_reference']]);

        return redirect()->route('admin.ai.edit')->with('status', 'Legal approval recorded.');
    }

    public function withdrawLegal(Request $request): RedirectResponse
    {
        AiSettings::put(['legal_approved_at' => null, 'legal_approved_by' => null, 'legal_reference' => null]);
        if (! AiSettings::config()['legal_approved'] && Features::enabled('ai_assistant')) {
            Features::set('ai_assistant', false, $request->user()->id);
        }
        Audit::log('ai.legal_approval_withdrawn', 'ai_settings');

        return redirect()->route('admin.ai.edit')->with('status', 'Legal approval withdrawn. The assistant is switched off until it is recorded again.');
    }

    public function toggle(Request $request): RedirectResponse
    {
        $enable = $request->boolean('enabled');
        if ($enable && ($reason = FeatureFlagController::blockedReason('ai_assistant'))) {
            return redirect()->route('admin.ai.edit')->withErrors(['ai' => $reason]);
        }
        Features::set('ai_assistant', $enable, $request->user()->id);
        Audit::log('feature_flag.changed', 'feature_flags', ['key' => 'ai_assistant', 'enabled' => $enable]);

        return redirect()->route('admin.ai.edit')->with('status', $enable ? 'The AI assistant is on for founders who opt in.' : 'The AI assistant is off.');
    }

    /** @return array{requests: int, failed: int, input_tokens: int, output_tokens: int, cost: float} */
    private function usage($since): array
    {
        $rows = AiInteraction::query()->where('created_at', '>=', $since)
            ->selectRaw('model, status, count(*) as requests, coalesce(sum(input_tokens), 0) as input_tokens, coalesce(sum(output_tokens), 0) as output_tokens')
            ->groupBy('model', 'status')->get();

        return [
            'requests' => (int) $rows->sum('requests'),
            'failed' => (int) $rows->where('status', 'error')->sum('requests'),
            'input_tokens' => (int) $rows->sum('input_tokens'),
            'output_tokens' => (int) $rows->sum('output_tokens'),
            'cost' => round($rows->sum(fn ($row) => AiSettings::cost($row->model, (int) $row->input_tokens, (int) $row->output_tokens)), 2),
        ];
    }

    private function isSafeCustomEndpoint(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            return false;
        }
        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        $addresses = gethostbynamel($host) ?: [];
        foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return $addresses !== [] && collect($addresses)->every(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false);
    }
}
