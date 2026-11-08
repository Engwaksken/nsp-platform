<?php

namespace App\Http\Controllers;

use App\Experts\ExpertService;
use App\Models\Experts\Engagement;
use App\Models\Experts\ExpertProfile;
use App\Models\Experts\ExpertSession;
use App\Models\Registry\StartupProfile;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The expert's own profile and dashboard. */
class ExpertController extends Controller
{
    public function __construct(private readonly ExpertService $experts) {}

    public function profile(Request $request): View
    {
        return view('experts.profile', ['profile' => ExpertProfile::query()->where('user_id', $request->user()->id)->first()]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'headline' => ['required', 'string', 'max:150'],
            'bio' => ['required', 'string', 'max:3000'],
            'kind' => ['required', Rule::in(array_keys(ExpertProfile::KINDS))],
            'expertise' => ['required', 'array', 'min:1', 'max:6'],
            'expertise.*' => [Rule::in(array_keys(ExpertProfile::areas()))],
            'sectors' => ['nullable', 'array'],
            'sectors.*' => [Rule::in(StartupProfile::SECTORS)],
            'languages' => ['nullable', 'array'],
            'languages.*' => [Rule::in(array_keys(ExpertProfile::LANGUAGES))],
            'website' => ['nullable', 'url:https,http', 'max:255'],
            'availability' => ['nullable', 'string', 'max:500'],
            'rate_note' => ['nullable', 'string', 'max:255'],
            'max_active' => ['required', 'integer', 'min:1', 'max:30'],
        ]);
        $data += ['sectors' => [], 'languages' => []];
        $data['offers_free'] = $request->boolean('offers_free');
        $data['is_listed'] = $request->boolean('is_listed');

        $profile = ExpertProfile::query()->where('user_id', $request->user()->id)->first() ?? new ExpertProfile;
        $profile->user_id = $request->user()->id;
        $isNew = ! $profile->exists;
        $profile->fill($data);
        $identityChanged = ! $isNew && $profile->status === 'verified' && $profile->isDirty(['headline', 'website']);
        if ($isNew || $profile->status === 'rejected' || $identityChanged) {
            $profile->status = 'pending';
        }
        $profile->save();
        Audit::log($isNew ? 'experts.applied' : 'experts.profile_updated', $profile);

        return redirect()->route('experts.profile')->with('status', $profile->status === 'pending'
            ? 'Thank you. Hive will check your profile and email you when founders can find you.'
            : 'Expert profile saved.');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $profile = $this->experts->expertProfile($request->user());
        if (! $profile) {
            return redirect()->route('experts.profile');
        }
        $engagements = Engagement::query()->with('startup.profile')->withCount(['sessions' => fn ($q) => $q->where('status', 'completed')])
            ->where('expert_user_id', $request->user()->id)->latest()->get();
        $upcoming = ExpertSession::query()->with('engagement.startup')
            ->whereIn('engagement_uuid', $engagements->where('status', 'active')->pluck('uuid'))
            ->where('status', 'scheduled')->where('scheduled_at', '>=', now()->subHours(2))->orderBy('scheduled_at')->limit(10)->get();

        return view('experts.dashboard', [
            'profile' => $profile,
            'requests' => $engagements->where('status', 'requested'),
            'active' => $engagements->where('status', 'active'),
            'past' => $engagements->whereIn('status', ['completed', 'declined', 'cancelled'])->take(20),
            'upcoming' => $upcoming,
            'atCapacity' => $engagements->where('status', 'active')->count() >= $profile->max_active,
        ]);
    }
}
