<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AiSettings;
use App\Http\Controllers\Controller;
use App\Models\Integration\ApiClient;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeatureFlagController extends Controller
{
    /** Modules that are not built yet cannot be switched on from the admin screen. */
    public const NOT_BUILT = ['capital_exchange'];

    /** Why a module cannot be switched on yet, or null. */
    public static function blockedReason(string $key): ?string
    {
        if (in_array($key, self::NOT_BUILT, true)) {
            return 'This module is not built yet, so it cannot be switched on.';
        }
        if ($key === 'ai_assistant' && (! AiSettings::config()['api_key'] || ! AiSettings::config()['legal_approved'])) {
            return 'The AI assistant can be switched on only after an API key is added and the legal review is recorded in Admin → AI assistant.';
        }

        return null;
    }

    /**
     * Set-up steps still missing for a feature that depends on outside systems.
     * These do not block the switch (except where blockedReason says so); they explain
     * why a switched-on feature would not work yet.
     *
     * @return list<string>
     */
    public static function missingSetup(string $key): array
    {
        $missing = [];
        if ($key === 'ai_assistant') {
            $ai = AiSettings::config();
            if (! $ai['api_key']) {
                $missing[] = 'Add the Anthropic API key in Admin → AI assistant.';
            }
            if (! $ai['legal_approved']) {
                $missing[] = 'Record the legal review in Admin → AI assistant once it is done (backlog AI-605).';
            }
        }
        if ($key === 'oidc') {
            foreach (['issuer' => 'OIDC_ISSUER', 'client_id' => 'OIDC_CLIENT_ID', 'client_secret' => 'OIDC_CLIENT_SECRET', 'redirect_uri' => 'OIDC_REDIRECT_URI'] as $setting => $env) {
                if (! filled(config("nsp.oidc.$setting"))) {
                    $missing[] = "Set $env in .env.";
                }
            }
            if ($missing) {
                $missing[] = 'Register '.(config('nsp.oidc.redirect_uri') ?: url('/auth/oidc/callback')).' as the redirect URI with the Hive identity provider. The SSO button stays hidden until this is configured.';
            }
        }
        if ($key === 'ims_integration') {
            $clients = ApiClient::query()->where('is_active', true)->where('recipient_system', 'ims');
            if (! (clone $clients)->exists()) {
                $missing[] = 'Register Hive IMS as a connected system: php artisan nsp:api-client ims-live --name="Hive IMS" --redirect-uri=… --webhook-url=…';
            } elseif (! (clone $clients)->whereNotNull('webhook_url')->exists()) {
                $missing[] = 'Give the IMS client a webhook URL (php artisan nsp:api-client {client_id} --webhook-url=…) so events can be delivered.';
            }
            if (! config('nsp.ims_url')) {
                $missing[] = 'Set IMS_URL in .env so founders get a link to Hive IMS.';
            }
        }

        return $missing;
    }

    public function index(): View
    {
        $flags = collect(Features::definitions())->map(fn ($definition, $key) => $definition + [
            'key' => $key,
            'enabled' => Features::enabled($key),
            'locked' => self::blockedReason($key) !== null,
            'blocked_reason' => self::blockedReason($key),
            'missing' => self::missingSetup($key),
        ]);

        return view('admin.features', compact('flags'));
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(array_key_exists($key, Features::definitions()), 404);
        $enabled = $request->boolean('enabled');
        if ($enabled && ($reason = self::blockedReason($key))) {
            return back()->withErrors(['flags' => $reason]);
        }
        if (! $enabled && $key === 'local_login' && ! Features::enabled('oidc')) {
            return back()->withErrors(['flags' => 'Turn on single sign-on before turning off email and password login, or nobody can sign in.']);
        }
        Features::set($key, $enabled, $request->user()->id);
        Audit::log('feature_flag.changed', 'feature_flags', ['key' => $key, 'enabled' => $enabled]);

        return back()->with('status', 'Feature updated.');
    }
}
