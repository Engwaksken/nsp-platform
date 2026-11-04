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
    public const NOT_BUILT = ['registry', 'ims_integration', 'vgi', 'funding', 'experts', 'talent', 'opportunities', 'ai_assistant'];

    public function index(): View
    {
        $flags = collect(Features::definitions())->map(fn ($definition, $key) => $definition + [
            'key' => $key,
            'enabled' => Features::enabled($key),
            'locked' => in_array($key, self::NOT_BUILT, true),
        ]);

        return view('admin.features', compact('flags'));
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(array_key_exists($key, Features::definitions()), 404);
        $enabled = $request->boolean('enabled');
        if ($enabled && in_array($key, self::NOT_BUILT, true)) {
            return back()->withErrors(['flags' => 'This module is not built yet, so it cannot be switched on.']);
        }
        if (! $enabled && $key === 'local_login' && ! Features::enabled('oidc')) {
            return back()->withErrors(['flags' => 'Turn on single sign-on before turning off email and password login, or nobody can sign in.']);
        }
        Features::set($key, $enabled, $request->user()->id);
        Audit::log('feature_flag.changed', 'feature_flags', ['key' => $key, 'enabled' => $enabled]);

        return back()->with('status', 'Feature updated.');
    }
}
