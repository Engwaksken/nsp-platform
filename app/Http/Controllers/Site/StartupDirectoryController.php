<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Listing;
use App\Models\Registry\DataSharingConsent;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupProfile;
use App\Support\Features;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Public startup showcase. Only startups with the "nsp_directory" consent appear,
 * and only the fields that consent names (display name, sector, location,
 * description, website). Revoking the consent removes the startup at once.
 */
class StartupDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sector' => ['nullable', Rule::in(StartupProfile::SECTORS)],
            'country' => ['nullable', Rule::in(array_keys(Startup::COUNTRIES))],
        ]);
        $startups = $this->listed()->with('profile')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('display_name', 'like', '%'.$term.'%')->orWhere('description', 'like', '%'.$term.'%')))
            ->when($filters['sector'] ?? null, fn ($q, $sector) => $q->whereHas('profile', fn ($p) => $p->where('sector', $sector)))
            ->when($filters['country'] ?? null, fn ($q, $country) => $q->where('country', $country))
            ->orderBy('display_name')->paginate(24)->withQueryString();

        return view('directory.index', ['startups' => $startups, 'filters' => $filters]);
    }

    public function show(Startup $startup): View
    {
        abort_unless($this->listed()->whereKey($startup->uuid)->exists(), 404);

        return view('directory.show', [
            'startup' => $startup->load('profile'),
            'listings' => Features::enabled('marketplace') ? Listing::query()->live()->where('startup_uuid', $startup->uuid)->latest('published_at')->get() : collect(),
        ]);
    }

    private function listed()
    {
        return Startup::query()->active()->whereIn('uuid', DataSharingConsent::query()->where('purpose', 'nsp_directory')->whereNull('revoked_at')->select('startup_uuid'));
    }
}
