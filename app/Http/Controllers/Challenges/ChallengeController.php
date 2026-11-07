<?php

namespace App\Http\Controllers\Challenges;

use App\Challenges\ChallengeLinks;
use App\Challenges\ChallengeSearch;
use App\Http\Controllers\Controller;
use App\Models\Challenges\Challenge;
use App\Models\Challenges\ChallengeBookmark;
use App\Models\Challenges\ChallengeEvidence;
use App\Models\Challenges\ChallengeSolutionSubmission;
use App\Models\Registry\StartupMembership;
use App\Support\Audit;
use App\Support\Markdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public Challenge Exchange: browse, read, bookmark and propose solutions. Only live published snapshots are shown. */
class ChallengeController extends Controller
{
    public function __construct(private readonly ChallengeSearch $search) {}

    public function index(Request $request): View
    {
        $criteria = ChallengeSearch::criteriaFrom($request);
        $user = $request->user();

        return view('challenges.index', [
            'challenges' => $this->search->search($criteria, $user),
            'criteria' => $criteria,
            'bookmarked' => $user ? ChallengeBookmark::query()->where('user_id', $user->id)->pluck('challenge_id')->all() : [],
        ]);
    }

    public function show(Request $request, Challenge $challenge): View
    {
        $user = $request->user();
        $challenge = $this->visibleOr404($request, $challenge);
        $version = $challenge->publishedVersion;
        $evidenceIds = (array) ($version->field('evidence_ids') ?? []);

        return view('challenges.show', [
            'challenge' => $challenge,
            'version' => $version,
            'evidence' => ChallengeEvidence::query()->with('validator')->whereIn('id', $evidenceIds)->where('challenge_id', $challenge->id)->orderBy('id')->get(),
            'links' => ChallengeLinks::for($version->content),
            'html' => fn (?string $field) => Markdown::render($version->field($field)),
            'bookmarked' => $user && ChallengeBookmark::query()->where('user_id', $user->id)->where('challenge_id', $challenge->id)->exists(),
            'proposal' => $user ? ChallengeSolutionSubmission::query()->where('challenge_id', $challenge->id)->where('user_id', $user->id)->latest('id')->first() : null,
            'memberships' => $user ? StartupMembership::query()->with('startup')->where('user_id', $user->id)->where('status', 'active')->get() : collect(),
        ]);
    }

    public function bookmark(Request $request, Challenge $challenge): RedirectResponse
    {
        $challenge = $this->visibleOr404($request, $challenge);
        $existing = ChallengeBookmark::query()->where('user_id', $request->user()->id)->where('challenge_id', $challenge->id)->first();
        if ($existing) {
            $existing->delete();

            return back()->with('status', 'Removed from your saved challenges.');
        }
        ChallengeBookmark::query()->create(['user_id' => $request->user()->id, 'challenge_id' => $challenge->id]);

        return back()->with('status', 'Saved to My challenges.');
    }

    /** Controlled intake: express interest or propose a solution. Private to you, your startup team and Hive reviewers. */
    public function propose(Request $request, Challenge $challenge): RedirectResponse
    {
        $challenge = $this->visibleOr404($request, $challenge);
        $data = $request->validate([
            'startup_uuid' => ['nullable', 'uuid'],
            'summary' => ['required', 'string', 'min:20', 'max:1000'],
            'approach' => ['nullable', 'string', 'max:5000'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Confirm that you understand who can see your proposal.']);
        $startupUuid = null;
        if (! empty($data['startup_uuid'])) {
            $membership = StartupMembership::query()->where('user_id', $request->user()->id)->where('status', 'active')
                ->where('startup_uuid', $data['startup_uuid'])->first();
            abort_unless($membership !== null, 403);
            $startupUuid = $membership->startup_uuid;
        }
        $open = ChallengeSolutionSubmission::query()->where('challenge_id', $challenge->id)->where('user_id', $request->user()->id)
            ->where('status', '!=', 'declined')->exists();
        if ($open) {
            return back()->withErrors(['summary' => 'You already have a proposal for this challenge. See My challenges.']);
        }
        $submission = ChallengeSolutionSubmission::query()->create([
            'challenge_id' => $challenge->id, 'user_id' => $request->user()->id, 'startup_uuid' => $startupUuid,
            'summary' => $data['summary'], 'approach' => $data['approach'] ?? null, 'status' => 'submitted',
        ]);
        Audit::log('challenge.solution_submitted', $submission, ['challenge_id' => $challenge->id]);

        return redirect()->route('challenges.proposals.show', $submission)->with('status', 'Thank you. Hive reviewers will look at your proposal.');
    }

    public function proposal(Request $request, ChallengeSolutionSubmission $proposal): View
    {
        abort_unless($proposal->isViewableBy($request->user()), 404);

        return view('challenges.proposal', ['proposal' => $proposal->load('challenge.publishedVersion', 'startup', 'user')]);
    }

    private function visibleOr404(Request $request, Challenge $challenge): Challenge
    {
        // Same visibility rule as search: one source of truth in ChallengeSearch::visible().
        abort_unless($this->search->visible($request->user())->where('challenges.id', $challenge->id)->exists(), 404);
        $challenge->loadMissing('publishedVersion');

        return $challenge;
    }
}
