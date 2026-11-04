<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeatureFlagController extends Controller
{
    /** Modules that are not built yet cannot be switched on from the admin screen. */
    public const NOT_BUILT = [];

    /** Why a module cannot be switched on yet, or null. */
    public static function blockedReason(string $key): ?string
    {
        if (in_array($key, self::NOT_BUILT, true)) {
            return 'This module is not built yet, so it cannot be switched on.';
        }
        if ($key === 'ai_assistant' && (! config('nsp.ai.api_key') || ! config('nsp.ai.legal_approved'))) {
            return 'The AI assistant can be switched on only after an API key is set and legal approval is recorded (ANTHROPIC_API_KEY and NSP_AI_LEGAL_APPROVED in .env).';
        }

        return null;
    }

    public function index(): View
    {
        $flags = collect(Features::definitions())->map(fn ($definition, $key) => $definition + [
            'key' => $key,
            'enabled' => Features::enabled($key),
            'locked' => self::blockedReason($key) !== null,
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
