<?php

namespace App\Http\Controllers;

use App\Ecosystem\Eligibility;
use App\Funding\FundingService;
use App\Models\Funding\DataRoomAccessLog;
use App\Models\Funding\DataRoomFile;
use App\Models\Funding\DataRoomGrant;
use App\Models\Funding\FundingRequest;
use App\Models\Funding\Introduction;
use App\Models\Funding\InvestorProfile;
use App\Models\Registry\DataSharingConsent;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\Registry\StartupProfile;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The investor area. Only Hive-verified investors see startups; everything else answers "not found". */
class InvestorController extends Controller
{
    public function __construct(private readonly FundingService $funding) {}

    public function apply(Request $request): View
    {
        return view('investors.apply', [
            'profile' => InvestorProfile::query()->where('user_id', $request->user()->id)->first(),
            'stageOptions' => FundingService::stageOptions(),
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organisation' => ['nullable', 'string', 'max:150'],
            'investor_type' => ['required', Rule::in(array_keys(InvestorProfile::TYPES))],
            'bio' => ['required', 'string', 'max:2000'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
            'thesis' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', Rule::in(InvestorProfile::CURRENCIES)],
            'ticket_min' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'ticket_max' => ['nullable', 'integer', 'min:0', 'max:1000000000000', 'gte:ticket_min'],
            'sectors' => ['nullable', 'array'],
            'sectors.*' => [Rule::in(StartupProfile::SECTORS)],
            'countries' => ['nullable', 'array'],
            'countries.*' => [Rule::in(array_keys(Startup::COUNTRIES))],
            'stages' => ['nullable', 'array'],
            'stages.*' => ['string', 'max:60'],
            'instruments' => ['nullable', 'array'],
            'instruments.*' => [Rule::in(array_keys(InvestorProfile::INSTRUMENTS))],
        ]);
        $data += ['sectors' => [], 'countries' => [], 'stages' => [], 'instruments' => []];
        $data['is_listed'] = $request->boolean('is_listed');

        $profile = InvestorProfile::query()->where('user_id', $request->user()->id)->first() ?? new InvestorProfile;
        $profile->user_id = $request->user()->id;
        $isNew = ! $profile->exists;
        $profile->fill($data);
        if ($isNew || $profile->status === 'rejected') {
            $profile->status = 'pending';
        }
        $profile->save();
        Audit::log($isNew ? 'funding.investor_applied' : 'funding.investor_profile_updated', $profile);

        return redirect()->route('investor.apply')->with('status', $profile->status === 'pending'
            ? 'Thank you. Hive will check your details and email you when your investor profile is verified.'
            : 'Investor profile saved.');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $profile = $this->funding->investorProfile($request->user());
        if (! $profile) {
            return redirect()->route('investor.apply');
        }
        $userId = $request->user()->id;

        $consented = DataSharingConsent::query()->where('purpose', 'investor_listing')->whereNull('revoked_at')->pluck('startup_uuid');
        $requests = FundingRequest::query()->with('startup.profile')->where('status', 'open')->whereIn('startup_uuid', $consented)
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'))->latest()->get();
        $filter = $request->query('show', 'fits');
        $startups = $requests->map(fn (FundingRequest $fr) => ['request' => $fr, 'startup' => $fr->startup] + $this->funding->fit($profile, $fr->startup, $fr))
            ->filter(fn ($row) => $filter === 'all' || $row['fits'])
            ->sortByDesc(fn ($row) => count($row['reasons']))->values();

        $introductions = Introduction::query()->with('startup')->where('investor_user_id', $userId)->latest()->get();

        return view('investors.dashboard', [
            'profile' => $profile,
            'startups' => $startups,
            'filter' => $filter,
            'incoming' => $introductions->where('status', 'requested')->where('requested_by', 'founder'),
            'pipeline' => $introductions->whereIn('status', ['requested', 'accepted'])->reject(fn ($i) => $i->status === 'requested' && $i->requested_by === 'founder'),
            'grants' => DataRoomGrant::query()->active()->with('startup')->where('investor_user_id', $userId)->get(),
        ]);
    }

    public function startup(Request $request, Startup $startup): View
    {
        abort_unless($this->funding->investorMaySee($request->user(), $startup), 404);
        $user = $request->user();
        $introduction = Introduction::query()->where('startup_uuid', $startup->uuid)->where('investor_user_id', $user->id)->latest()->first();
        $connected = $introduction?->status === 'accepted';
        $grant = $this->funding->activeGrant($startup, $user);

        return view('investors.startup', [
            'startup' => $startup,
            'profile' => $startup->profile,
            'stage' => Eligibility::stage($startup),
            'request' => $this->funding->openRequest($startup),
            'introduction' => $introduction,
            'connected' => $connected,
            'contacts' => $connected ? StartupMembership::query()->with('user')->where('startup_uuid', $startup->uuid)->where('status', 'active')
                ->whereIn('role', StartupMembership::EDITOR_ROLES)->get() : collect(),
            'grant' => $grant,
            'files' => $grant ? DataRoomFile::query()->where('startup_uuid', $startup->uuid)->orderBy('category')->orderBy('title')->get() : collect(),
            'fit' => ($open = $this->funding->openRequest($startup)) ? $this->funding->fit($this->funding->investorProfile($user), $startup, $open) : null,
        ]);
    }

    public function interest(Request $request, Startup $startup): RedirectResponse
    {
        abort_unless($this->funding->investorMaySee($request->user(), $startup), 404);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        try {
            $this->funding->expressInterest($startup, $request->user(), $data['message']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['interest' => $exception->getMessage()]);
        }

        return back()->with('status', 'Interest sent. The founders decide whether to connect.');
    }

    /** The investor's private pipeline: never shown to the startup. */
    public function pipeline(Request $request, Introduction $introduction): RedirectResponse
    {
        abort_unless($introduction->investor_user_id === $request->user()->id && $this->funding->investorProfile($request->user()), 404);
        $data = $request->validate([
            'investor_stage' => ['nullable', Rule::in(array_keys(Introduction::STAGES))],
            'investor_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $introduction->forceFill($data)->save();

        return back()->with('status', 'Your private notes were saved.');
    }

    public function decide(Request $request, Introduction $introduction): RedirectResponse
    {
        abort_unless($introduction->investor_user_id === $request->user()->id && $this->funding->investorProfile($request->user()), 404);
        try {
            $this->funding->decide($introduction, $request->user(), $request->input('decision') === 'accept');
        } catch (RegistryException $exception) {
            return back()->withErrors(['introduction' => $exception->getMessage()]);
        }

        return back()->with('status', $introduction->status === 'accepted' ? 'Connected. You can now see the founders’ contact details.' : 'Introduction declined.');
    }

    /** Only signed-in users who are either on the team or a verified investor with an active grant may download. */
    public function download(Request $request, DataRoomFile $file)
    {
        /** @var User $user */
        $user = $request->user();
        $access = $this->funding->downloadAccess($user, $file);
        abort_unless($access !== null, 404);

        DataRoomAccessLog::query()->create([
            'file_uuid' => $file->uuid, 'startup_uuid' => $file->startup_uuid, 'user_id' => $user->id,
            'access_type' => $access, 'ip_hash' => Audit::hashIdentifier($request->ip()), 'accessed_at' => now(),
        ]);
        if ($access === 'investor') {
            Audit::log('funding.file_downloaded', $file, ['title' => $file->title]);
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
