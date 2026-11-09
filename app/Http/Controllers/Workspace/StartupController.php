<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupFieldChange;
use App\Models\Registry\StartupMembership;
use App\Models\Registry\StartupProfile;
use App\Registry\ActiveStartup;
use App\Registry\RegistryException;
use App\Registry\RegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StartupController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly RegistryService $registry) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $memberships = StartupMembership::query()->with('startup.profile')
            ->where('user_id', $user->id)->where('status', 'active')
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'))->get()
            ->sortBy(fn ($m) => strtolower($m->startup->display_name));
        $invitations = StartupMembership::query()->with('startup', 'inviter')
            ->where('status', 'invited')->where('invited_email', strtolower($user->email))
            ->where('invite_expires_at', '>', now())->get();
        $requests = StartupMembership::query()->with('startup')
            ->where('user_id', $user->id)->where('status', 'requested')->get();

        return view('workspace.startups.index', [
            'memberships' => $memberships,
            'active' => ActiveStartup::membership($user),
            'invitations' => $invitations,
            'requests' => $requests,
        ]);
    }

    public function create(): View
    {
        return view('workspace.startups.form', ['startup' => new Startup(['country' => 'UG']), 'profile' => new StartupProfile, 'version' => 0, 'matches' => []]);
    }

    public function store(Request $request): RedirectResponse|View
    {
        [$core, $profile] = $this->validated($request);
        $matches = $this->registry->possibleDuplicates($core);
        $strong = array_filter($matches, fn ($m) => $m['strong']);
        if ($strong || ($matches && ! $request->boolean('confirm_different'))) {
            // Show who already exists instead of silently creating a second record.
            return view('workspace.startups.form', [
                'startup' => new Startup($core), 'profile' => new StartupProfile($profile), 'version' => 0,
                'matches' => $matches, 'blocked' => (bool) $strong,
            ]);
        }

        try {
            $startup = $this->registry->createStartup($request->user(), $core, $profile, $request->boolean('confirm_different'));
        } catch (RegistryException $exception) {
            return back()->withInput()->withErrors(['legal_name' => $exception->getMessage()]);
        }
        ActiveStartup::switchTo($startup);

        return redirect()->route('workspace.startups.show', $startup)->with('status', 'Your startup profile was created. Invite your co-founders from the Team page.');
    }

    public function show(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $startup->load('profile', 'founders', 'activeMemberships.user');

        return view('workspace.startups.show', ['startup' => $startup, 'profile' => $startup->profile ?? new StartupProfile, 'membership' => $membership]);
    }

    public function edit(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can edit the profile.');

        return view('workspace.startups.form', ['startup' => $startup, 'profile' => $startup->profile ?? new StartupProfile, 'version' => $startup->version, 'matches' => []]);
    }

    public function update(Request $request, Startup $startup): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can edit the profile.');
        [$core, $profile] = $this->validated($request);

        try {
            $changed = $this->registry->updateStartup($startup, $request->user(), $core, $profile, (int) $request->input('version'));
        } catch (RegistryException $exception) {
            return back()->withInput()->withErrors(['version' => $exception->getMessage()]);
        }

        return redirect()->route('workspace.startups.show', $startup)
            ->with('status', $changed ? 'Profile saved.' : 'Nothing changed.');
    }

    public function switch(Request $request, Startup $startup): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        ActiveStartup::switchTo($startup);

        return redirect()->route('workspace.startups.overview', $startup)->with('status', 'You are now working on '.$startup->display_name.'.');
    }

    public function history(Request $request, Startup $startup): View
    {
        $this->membershipOr404($request, $startup);
        $changes = StartupFieldChange::query()->with('changer')->where('startup_uuid', $startup->uuid)->latest('id')->paginate(50);

        return view('workspace.startups.history', compact('startup', 'changes'));
    }

    /** From the duplicate warning: ask an existing startup's team to let you in. */
    public function requestJoin(Request $request, Startup $startup): RedirectResponse
    {
        abort_unless($startup->status === 'active', 404);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);
        try {
            $this->registry->requestToJoin($startup, $request->user(), (string) ($data['message'] ?? ''));
        } catch (RegistryException $exception) {
            return redirect()->route('workspace.startups.index')->withErrors(['join' => $exception->getMessage()]);
        }

        return redirect()->route('workspace.startups.index')->with('status', 'Your request was sent to the startup’s owners and admins.');
    }

    private function validated(Request $request): array
    {
        $levels = array_keys(StartupProfile::VISIBILITY_LEVELS);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:200'],
            'display_name' => ['required', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'country' => ['required', Rule::in(array_keys(Startup::COUNTRIES))],
            'year_founded' => ['nullable', 'integer', 'min:1950', 'max:'.now()->year],
            'description' => ['nullable', 'string', 'max:3000'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
            'sector' => ['nullable', Rule::in(StartupProfile::SECTORS)],
            'sub_sector' => ['nullable', 'string', 'max:80'],
            'headquarters_location' => ['nullable', 'string', 'max:160'],
            'team_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'revenue_band' => ['nullable', Rule::in(array_keys(StartupProfile::REVENUE_BANDS))],
            'traction_summary' => ['nullable', 'string', 'max:3000'],
            'funding_raised_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'funding_currency' => ['nullable', Rule::in(['UGX', 'USD', 'EUR', 'KES', 'GBP'])],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'visibility' => ['nullable', 'array'],
            'visibility.*' => [Rule::in($levels)],
        ], ['website.url' => 'Enter the full website address, starting with https://']);

        $core = array_intersect_key($data, array_flip(Startup::CORE_FIELDS));
        $core = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $core + array_fill_keys(Startup::CORE_FIELDS, null));
        $profile = array_intersect_key($data, array_flip(RegistryService::PROFILE_FIELDS)) + array_fill_keys(RegistryService::PROFILE_FIELDS, null);
        $profile['visibility'] = array_intersect_key($data['visibility'] ?? [], StartupProfile::VISIBILITY_GROUPS);

        return [$core, $profile];
    }
}
