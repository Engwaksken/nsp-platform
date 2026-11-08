<?php

namespace App\Http\Controllers\Challenges;

use App\Challenges\ChallengeForm;
use App\Challenges\ChallengeWorkflow;
use App\Http\Controllers\Controller;
use App\Models\Challenges\Challenge;
use App\Models\Challenges\ChallengeBookmark;
use App\Models\Challenges\ChallengeEvidence;
use App\Models\Challenges\ChallengeSolutionSubmission;
use App\Models\Registry\StartupMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Signed-in members: submit challenges, follow their review, and see their bookmarks and proposals. */
class MyChallengeController extends Controller
{
    public function __construct(private readonly ChallengeWorkflow $workflow) {}

    public function mine(Request $request): View
    {
        $user = $request->user();
        $startupUuids = StartupMembership::query()->where('user_id', $user->id)->where('status', 'active')->pluck('startup_uuid');

        return view('challenges.mine', [
            'challenges' => Challenge::query()->with(['reviews' => fn ($q) => $q->whereIn('decision', ['approve', 'reject', 'request_changes'])])
                ->where('submitted_by', $user->id)->latest('updated_at')->get(),
            'proposals' => ChallengeSolutionSubmission::query()->with('challenge.publishedVersion', 'startup', 'user')
                ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('startup_uuid', $startupUuids))
                ->latest()->get(),
            'bookmarks' => ChallengeBookmark::query()->with('challenge.publishedVersion')->where('user_id', $user->id)->latest()->get()
                ->filter(fn ($b) => $b->challenge?->isVisibleTo($user)),
        ]);
    }

    public function create(): View
    {
        return view('challenges.form', ['challenge' => new Challenge(['priority' => 'medium', 'visibility' => 'public', 'category' => 'innovation'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $challenge = $this->workflow->saveContent(new Challenge, ChallengeForm::validated($request, staff: false), $request->user());
        if ($request->input('action') === 'submit') {
            $this->workflow->submit($challenge, $request->user());

            return redirect()->route('challenges.mine')->with('status', 'Submitted. Hive reviewers will check it before anything is published.');
        }

        return redirect()->route('challenges.edit', $challenge)->with('status', 'Draft saved. Add supporting evidence, then submit it for review.');
    }

    public function edit(Request $request, Challenge $challenge): View
    {
        $this->ownOr404($request, $challenge);

        return view('challenges.form', ['challenge' => $challenge->load('evidence', 'publishedVersion')]);
    }

    public function update(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->ownOr404($request, $challenge);
        $this->workflow->saveContent($challenge, ChallengeForm::validated($request, staff: false), $request->user());
        if ($request->input('action') === 'submit') {
            $this->workflow->submit($challenge, $request->user());

            return redirect()->route('challenges.mine')->with('status', 'Submitted for review.');
        }

        return back()->with('status', $challenge->published_version_id ? 'Revision saved as a draft. The published version stays online until reviewers publish this one.' : 'Draft saved.');
    }

    public function submit(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->ownOr404($request, $challenge);
        $this->workflow->submit($challenge, $request->user());

        return redirect()->route('challenges.mine')->with('status', 'Submitted for review.');
    }

    public function storeEvidence(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->ownOr404($request, $challenge);
        abort_unless($challenge->isEditable(), 409);
        ChallengeForm::addEvidence($request, $challenge);

        return back()->with('status', 'Source added. It will show as unreviewed until Hive reviewers check it.');
    }

    public function destroyEvidence(Request $request, Challenge $challenge, ChallengeEvidence $evidence): RedirectResponse
    {
        $this->ownOr404($request, $challenge);
        abort_unless($challenge->isEditable(), 409);
        ChallengeForm::removeEvidence($challenge, $evidence);

        return back()->with('status', 'Source removed.');
    }

    /** Private evidence file: submitter and challenge staff only. */
    public function download(Request $request, Challenge $challenge, ChallengeEvidence $evidence): StreamedResponse
    {
        abort_unless($evidence->challenge_id === $challenge->id && $evidence->path, 404);
        abort_unless(ChallengeForm::canSeePrivateEvidence($request->user(), $challenge), 404);

        return Storage::disk($evidence->disk ?: 'local')->download($evidence->path, $evidence->original_name ?: 'evidence');
    }

    private function ownOr404(Request $request, Challenge $challenge): void
    {
        abort_unless($challenge->submitted_by === $request->user()->id, 404);
    }
}
