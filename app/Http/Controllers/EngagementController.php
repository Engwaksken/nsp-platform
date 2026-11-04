<?php

namespace App\Http\Controllers;

use App\Experts\ExpertService;
use App\Models\Experts\Engagement;
use App\Models\Experts\ExpertProfile;
use App\Models\Experts\ExpertSession;
use App\Models\Experts\SessionAction;
use App\Models\Registry\StartupMembership;
use App\Registry\RegistryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** One engagement, shared by the expert and the startup team. Anyone else gets "not found". */
class EngagementController extends Controller
{
    public function __construct(private readonly ExpertService $experts) {}

    public function show(Request $request, Engagement $engagement): View
    {
        $side = $this->sideOr404($request, $engagement);
        $engagement->load('startup.profile', 'expert', 'requester', 'sessions', 'actions', 'review');
        $connected = in_array($engagement->status, ['active', 'completed'], true);

        return view('engagements.show', [
            'engagement' => $engagement,
            'isExpert' => $side['expert'],
            'canEdit' => $side['expert'] || (bool) $side['membership']?->canEdit(),
            'expertProfile' => ExpertProfile::query()->where('user_id', $engagement->expert_user_id)->first(),
            'contacts' => $connected ? StartupMembership::query()->with('user')->where('startup_uuid', $engagement->startup_uuid)
                ->where('status', 'active')->whereIn('role', StartupMembership::EDITOR_ROLES)->get() : collect(),
            'connected' => $connected,
        ]);
    }

    public function decide(Request $request, Engagement $engagement): RedirectResponse
    {
        $this->sideOr404($request, $engagement);
        $data = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])], 'response_note' => ['nullable', 'string', 'max:1000']]);

        return $this->attempt(fn () => $this->experts->decide($engagement, $request->user(), $data['decision'] === 'accept', $data['response_note'] ?? null),
            $data['decision'] === 'accept' ? 'Accepted. Plan your first session below.' : 'Declined. The founders have been told.');
    }

    public function schedule(Request $request, Engagement $engagement): RedirectResponse
    {
        $this->sideOr404($request, $engagement);
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now', 'before:'.now()->addYear()->toDateString()],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'mode' => ['required', Rule::in(array_keys(ExpertSession::MODES))],
            'location' => ['nullable', 'string', 'max:255', Rule::when($request->input('mode') === 'online', ['url:https'])],
        ]);

        return $this->attempt(fn () => $this->experts->schedule($engagement, $request->user(), $data), 'Session planned. The other side has been told.');
    }

    public function updateSession(Request $request, Engagement $engagement, ExpertSession $session): RedirectResponse
    {
        $this->sideOr404($request, $engagement);
        abort_unless($session->engagement_uuid === $engagement->uuid, 404);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(ExpertSession::STATUSES))],
            'shared_notes' => ['nullable', 'string', 'max:5000'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->attempt(fn () => $this->experts->updateSession($session, $request->user(), $data), 'Session saved.');
    }

    public function addAction(Request $request, Engagement $engagement): RedirectResponse
    {
        $this->sideOr404($request, $engagement);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'owner' => ['required', Rule::in(array_keys(SessionAction::OWNERS))],
            'due_on' => ['nullable', 'date'],
        ]);

        return $this->attempt(fn () => $this->experts->addAction($engagement, $request->user(), $data), 'Action added.');
    }

    public function toggleAction(Request $request, Engagement $engagement, SessionAction $action): RedirectResponse
    {
        $this->sideOr404($request, $engagement);
        abort_unless($action->engagement_uuid === $engagement->uuid, 404);

        return $this->attempt(fn () => $this->experts->toggleAction($action, $request->user()), 'Action updated.');
    }

    public function complete(Request $request, Engagement $engagement): RedirectResponse
    {
        $this->sideOr404($request, $engagement);
        $data = $request->validate(['outcome' => ['nullable', 'string', 'max:3000']]);

        return $this->attempt(fn () => $this->experts->complete($engagement, $request->user(), $data['outcome'] ?? null), 'Marked as completed.');
    }

    public function cancel(Request $request, Engagement $engagement): RedirectResponse
    {
        $this->sideOr404($request, $engagement);

        return $this->attempt(fn () => $this->experts->cancel($engagement, $request->user()), 'Cancelled.');
    }

    public function review(Request $request, Engagement $engagement): RedirectResponse
    {
        $this->sideOr404($request, $engagement);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'helped' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->attempt(fn () => $this->experts->review($engagement, $request->user(), $data), 'Thank you for your feedback.');
    }

    private function sideOr404(Request $request, Engagement $engagement): array
    {
        $side = $this->experts->sideOf($request->user(), $engagement);
        abort_unless($side !== null, 404);

        return $side;
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (RegistryException $exception) {
            return back()->withErrors(['engagement' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', $message);
    }
}
