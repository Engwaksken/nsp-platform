<?php

namespace App\Http\Controllers\Workspace;

use App\Experts\ExpertService;
use App\Http\Controllers\Controller;
use App\Models\Experts\Engagement;
use App\Models\Experts\ExpertProfile;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupProfile;
use App\Models\User;
use App\Registry\RegistryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Founders find verified experts and ask for a consultation or mentoring. */
class ExpertDirectoryController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly ExpertService $experts) {}

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $areas = array_values(array_intersect((array) $request->query('areas', []), array_keys(ExpertProfile::areas())));
        $kind = $request->query('kind');
        $q = trim((string) $request->query('q', ''));

        $profiles = ExpertProfile::query()->verified()->where('is_listed', true)->with('user')
            ->when($kind === 'mentor', fn ($query) => $query->whereIn('kind', ['mentor', 'both']))
            ->when($kind === 'consultant', fn ($query) => $query->whereIn('kind', ['consultant', 'both']))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('headline', 'like', '%'.$q.'%')->orWhere('bio', 'like', '%'.$q.'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%'))))
            ->get()
            ->filter(fn (ExpertProfile $p) => $areas === [] || array_intersect($p->expertise ?? [], $areas));

        $rows = $profiles->map(fn (ExpertProfile $p) => ['expert' => $p, 'reasons' => $this->experts->reasons($p, $startup, $areas)])
            ->sortByDesc(fn ($row) => count($row['reasons']))->values();

        $engagements = Engagement::query()->with('expert')->where('startup_uuid', $startup->uuid)->latest()->get();

        return view('workspace.startups.experts', [
            'startup' => $startup,
            'membership' => $membership,
            'rows' => $rows,
            'ratings' => $this->experts->ratings($profiles->pluck('user_id')->all()),
            'engagements' => $engagements,
            'openWith' => $engagements->filter->isOpen()->pluck('expert_user_id')->all(),
            'areas' => $areas,
            'kind' => $kind,
            'q' => $q,
            'sectors' => StartupProfile::SECTORS,
        ]);
    }

    public function request(Request $request, Startup $startup): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $data = $request->validate([
            'expert_user_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(Engagement::TYPES))],
            'topic' => ['required', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:3000'],
            'preferred_times' => ['nullable', 'string', 'max:255'],
        ]);
        $expert = User::query()->findOrFail($data['expert_user_id']);
        try {
            $engagement = $this->experts->request($startup, $request->user(), $expert, $data);
        } catch (RegistryException $exception) {
            return back()->withErrors(['expert' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('engagements.show', $engagement)->with('status', 'Request sent. We will email you when '.$expert->name.' replies.');
    }
}
