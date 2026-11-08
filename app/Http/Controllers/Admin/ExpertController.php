<?php

namespace App\Http\Controllers\Admin;

use App\Experts\ExpertService;
use App\Http\Controllers\Controller;
use App\Models\Experts\Engagement;
use App\Models\Experts\ExpertProfile;
use App\Models\Experts\ExpertReview;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Staff verify experts, assign mentors and follow engagements. Session notes are not shown here. */
class ExpertController extends Controller
{
    public const STATUSES = ['pending' => 'Waiting for review', 'verified' => 'Verified', 'rejected' => 'Not verified', 'suspended' => 'Suspended'];

    public function __construct(private readonly ExpertService $experts) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $profiles = ExpertProfile::query()->with('user')
            ->when(array_key_exists($status, self::STATUSES), fn ($q) => $q->where('status', $status))
            ->latest('updated_at')->paginate(25)->withQueryString();
        $reviews = ExpertReview::query()->whereIn('expert_user_id', $profiles->pluck('user_id'))
            ->selectRaw('expert_user_id, avg(rating) as average, count(*) as total')->groupBy('expert_user_id')->get()->keyBy('expert_user_id');

        return view('admin.experts.index', ['profiles' => $profiles, 'status' => $status, 'statuses' => self::STATUSES, 'reviews' => $reviews]);
    }

    public function decide(Request $request, ExpertProfile $expert): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected', 'suspended'])],
            'verification_note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->input('status') !== 'verified')],
        ]);
        $expert->forceFill([
            'status' => $data['status'], 'verification_note' => $data['verification_note'] ?? null,
            'verified_by' => $request->user()->id, 'verified_at' => now(),
        ])->save();
        $user = $expert->user;
        if ($user) {
            $data['status'] === 'verified' ? $user->assignRole('expert') : $user->removeRole('expert');
        }
        Audit::log('experts.'.$data['status'], $expert, ['note' => $data['verification_note'] ?? null]);

        return back()->with('status', ($user?->name ?? 'Expert').': '.self::STATUSES[$data['status']].'.');
    }

    public function engagements(Request $request): View
    {
        $status = $request->query('status', 'active');
        $engagements = Engagement::query()->with('startup', 'expert', 'review')->withCount('sessions')
            ->when(array_key_exists($status, Engagement::STATUSES), fn ($q) => $q->where('status', $status))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.experts.engagements', [
            'engagements' => $engagements,
            'status' => $status,
            'mentors' => ExpertProfile::query()->verified()->whereIn('kind', ['mentor', 'both'])->with('user')->get()
                ->sortBy(fn ($p) => $p->user->name)->mapWithKeys(fn ($p) => [$p->user_id => $p->user->name.' · '.$p->headline])->all(),
            'startups' => Startup::query()->active()->orderBy('display_name')->pluck('display_name', 'uuid')->all(),
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'startup_uuid' => ['required', 'string'],
            'expert_user_id' => ['required', 'integer'],
            'topic' => ['required', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:3000'],
        ]);
        $startup = Startup::query()->findOrFail($data['startup_uuid']);
        $expert = User::query()->findOrFail($data['expert_user_id']);
        try {
            $this->experts->assignMentor($startup, $request->user(), $expert, $data['topic'], $data['details'] ?? null);
        } catch (RegistryException $exception) {
            return back()->withErrors(['assign' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', $expert->name.' now mentors '.$startup->display_name.'. Both have been told.');
    }
}
