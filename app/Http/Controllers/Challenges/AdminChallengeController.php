<?php

namespace App\Http\Controllers\Challenges;

use App\Challenges\ChallengeForm;
use App\Challenges\ChallengeWorkflow;
use App\Http\Controllers\Controller;
use App\Models\Challenges\Challenge;
use App\Models\Challenges\ChallengeEvidence;
use App\Models\Challenges\ChallengeReview;
use App\Models\Challenges\ChallengeSolutionSubmission;
use App\Models\Challenges\ChallengeVersion;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Staff: review queue, editing (manage-challenges) and review/publish decisions (review-challenges). */
class AdminChallengeController extends Controller
{
    public const TABS = ['submitted', 'under_review', 'changes_requested', 'approved', 'live', 'draft', 'rejected', 'archived'];

    public function __construct(private readonly ChallengeWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $this->staffOr403($request);
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'submitted';
        $query = Challenge::query()->with('submitter', 'publishedVersion')->withCount('solutionSubmissions');
        if ($tab === 'live') {
            $query->whereNotNull('published_version_id')->whereNull('archived_at');
        } else {
            $query->where('status', $tab);
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.str_replace(['%', '_'], '', (string) $request->query('q')).'%');
        }
        $counts = Challenge::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.challenges.index', [
            'tab' => $tab,
            'counts' => $counts,
            'challenges' => $query->latest('updated_at')->paginate(25)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.challenges.form', ['challenge' => new Challenge(['priority' => 'medium', 'visibility' => 'public', 'category' => 'innovation'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $challenge = $this->workflow->saveContent(new Challenge, ChallengeForm::validated($request, staff: true), $request->user());
        if ($request->input('action') === 'submit') {
            $this->workflow->submit($challenge, $request->user());
        }

        return redirect()->route('admin.challenges.show', $challenge)->with('status', $request->input('action') === 'submit' ? 'Saved and submitted for review.' : 'Draft saved.');
    }

    public function show(Request $request, Challenge $challenge): View
    {
        $this->staffOr403($request);
        $challenge->load(['versions.author', 'versions.approver', 'reviews.reviewer', 'reviews.version', 'evidence.validator', 'submitter', 'publishedVersion']);
        $versions = $challenge->versions;
        $from = $versions->firstWhere('version', (int) $request->query('from')) ?? $challenge->publishedVersion ?? $versions->skip(1)->first();
        $to = $versions->firstWhere('version', (int) $request->query('to')) ?? $versions->first();

        return view('admin.challenges.show', [
            'challenge' => $challenge,
            'from' => $from && $to && $from->id !== $to->id ? $from : null,
            'to' => $to,
            'diff' => $from && $to && $from->id !== $to->id ? self::diff($from, $to) : [],
            'workingDiffers' => $to && self::diffContent($to->content, $challenge->contentSnapshot()) !== [],
            'proposalCount' => $challenge->solutionSubmissions()->count(),
        ]);
    }

    public function edit(Challenge $challenge): View
    {
        return view('admin.challenges.form', ['challenge' => $challenge->load('evidence', 'publishedVersion')]);
    }

    public function update(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->workflow->saveContent($challenge, ChallengeForm::validated($request, staff: true), $request->user());
        if ($request->input('action') === 'submit') {
            $this->workflow->submit($challenge, $request->user());

            return redirect()->route('admin.challenges.show', $challenge)->with('status', 'Submitted for review.');
        }

        return back()->with('status', 'Saved.');
    }

    public function submit(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->workflow->submit($challenge, $request->user());

        return back()->with('status', 'Submitted for review.');
    }

    public function review(Request $request, Challenge $challenge): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(array_keys(ChallengeReview::DECISIONS))],
            'reason' => ['nullable', 'required_if:decision,reject', Rule::in(array_keys(config('challenges.rejection_reasons')))],
            'comments' => ['nullable', 'required_if:decision,reject,request_changes,comment', 'string', 'max:5000'],
            'source_validation_notes' => ['nullable', 'string', 'max:5000'],
        ], ['comments.required_if' => 'Add a comment explaining your decision.', 'reason.required_if' => 'Choose a reason for rejecting.']);
        $this->workflow->review($challenge, $request->user(), $data['decision'], $data['comments'] ?? null, $data['reason'] ?? null, $data['source_validation_notes'] ?? null);

        return back()->with('status', 'Review recorded: '.ChallengeReview::DECISIONS[$data['decision']].'.');
    }

    public function publish(Request $request, Challenge $challenge): RedirectResponse
    {
        $version = $this->workflow->publish($challenge, $request->user());

        return back()->with('status', "Version {$version->version} is now published.");
    }

    public function archive(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->workflow->archive($challenge, $request->user());

        return back()->with('status', 'Archived. It is no longer shown on the public site.');
    }

    public function validateEvidence(Request $request, Challenge $challenge, ChallengeEvidence $evidence): RedirectResponse
    {
        abort_unless($evidence->challenge_id === $challenge->id, 404);
        $data = $request->validate(['validated' => ['required', 'boolean'], 'validation_note' => ['nullable', 'string', 'max:1000']]);
        $this->workflow->validateEvidence($evidence, $request->user(), (bool) $data['validated'], $data['validation_note'] ?? null);

        return back()->with('status', $data['validated'] ? 'Source marked as reviewed.' : 'Source marked as not reviewed.');
    }

    public function storeEvidence(Request $request, Challenge $challenge): RedirectResponse
    {
        abort_unless($challenge->isEditable(), 409);
        ChallengeForm::addEvidence($request, $challenge);

        return back()->with('status', 'Source added.');
    }

    public function destroyEvidence(Challenge $challenge, ChallengeEvidence $evidence): RedirectResponse
    {
        abort_unless($challenge->isEditable(), 409);
        ChallengeForm::removeEvidence($challenge, $evidence);

        return back()->with('status', 'Source removed.');
    }

    public function proposals(Challenge $challenge): View
    {
        return view('admin.challenges.proposals', [
            'challenge' => $challenge,
            'proposals' => $challenge->solutionSubmissions()->with('user', 'startup')->latest()->get(),
        ]);
    }

    public function updateProposal(Request $request, Challenge $challenge, ChallengeSolutionSubmission $proposal): RedirectResponse
    {
        abort_unless($proposal->challenge_id === $challenge->id, 404);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ChallengeSolutionSubmission::STATUSES))]]);
        $old = $proposal->status;
        $proposal->forceFill(['status' => $data['status'], 'status_changed_by' => $request->user()->id, 'status_changed_at' => now()])->save();
        Audit::log('challenge.solution_status', $proposal, ['from' => $old, 'to' => $data['status']]);

        return back()->with('status', 'Proposal updated.');
    }

    /** Field-by-field comparison of two version snapshots. */
    public static function diff(ChallengeVersion $from, ChallengeVersion $to): array
    {
        return self::diffContent($from->content, $to->content);
    }

    public static function diffContent(array $from, array $to): array
    {
        $rows = [];
        foreach (array_merge(Challenge::CONTENT_FIELDS, ['evidence_ids']) as $field) {
            $a = self::display($from[$field] ?? null);
            $b = self::display($to[$field] ?? null);
            if ($a !== $b) {
                $rows[$field] = ['from' => $a, 'to' => $b];
            }
        }

        return $rows;
    }

    private static function display(mixed $value): string
    {
        if (is_array($value)) {
            return implode("\n", array_map(fn ($v) => is_array($v) ? implode(' · ', array_filter(array_map('strval', $v))) : (string) $v, $value));
        }

        return (string) $value;
    }

    private function staffOr403(Request $request): void
    {
        abort_unless($request->user()->canAny(['manage-challenges', 'review-challenges']), 403);
    }
}
