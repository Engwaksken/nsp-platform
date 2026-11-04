<?php

namespace App\Http\Controllers\Workspace;

use App\Growth\GrowthService;
use App\Http\Controllers\Controller;
use App\Models\Growth\GrowthAction;
use App\Models\Growth\VgiAssessment;
use App\Models\Growth\VgiFramework;
use App\Models\Registry\Startup;
use App\Registry\RegistryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GrowthController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly GrowthService $growth) {}

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $assessments = VgiAssessment::query()->with('framework')->where('startup_uuid', $startup->uuid)
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 ELSE 1 END")->latest('submitted_at')->get();
        $latest = $assessments->firstWhere('status', 'submitted');
        $planned = GrowthAction::query()->where('startup_uuid', $startup->uuid)->whereNotNull('question_key')->pluck('question_key')->all();

        return view('workspace.growth.index', [
            'startup' => $startup,
            'membership' => $membership,
            'framework' => VgiFramework::current(),
            'latest' => $latest,
            'draft' => $assessments->firstWhere('status', 'draft'),
            'history' => $assessments->where('status', 'submitted'),
            'actions' => GrowthAction::query()->with('owner', 'updates.user')->where('startup_uuid', $startup->uuid)
                ->orderByRaw("CASE status WHEN 'doing' THEN 0 WHEN 'todo' THEN 1 ELSE 2 END")->orderBy('due_date')->get(),
            'planned' => $planned,
            'members' => $startup->activeMemberships()->with('user')->get(),
        ]);
    }

    public function start(Request $request, Startup $startup): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can fill in the assessment.');
        try {
            $assessment = $this->growth->start($startup, $request->user());
        } catch (RegistryException $exception) {
            return back()->withErrors(['growth' => $exception->getMessage()]);
        }

        return redirect()->route('workspace.growth.assessment', [$startup, $assessment]);
    }

    public function show(Request $request, Startup $startup, VgiAssessment $assessment): View
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($assessment->startup_uuid === $startup->uuid, 404);
        $assessment->load('framework', 'responses', 'submitter', 'overrider');

        return view($assessment->status === 'draft' ? 'workspace.growth.form' : 'workspace.growth.result', [
            'startup' => $startup,
            'membership' => $membership,
            'assessment' => $assessment,
            'definition' => $assessment->framework->definition,
            'responses' => $assessment->responses->keyBy('question_key'),
            'planned' => GrowthAction::query()->where('startup_uuid', $startup->uuid)->whereNotNull('question_key')->pluck('question_key')->all(),
        ]);
    }

    public function update(Request $request, Startup $startup, VgiAssessment $assessment): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($assessment->startup_uuid === $startup->uuid, 404);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can fill in the assessment.');
        $data = $request->validate([
            'answers' => ['nullable', 'array'], 'answers.*' => ['nullable', 'string', 'max:30'],
            'evidence' => ['nullable', 'array'], 'evidence.*' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->growth->saveAnswers($assessment, $request->user(), $data['answers'] ?? [], $data['evidence'] ?? []);
            if ($request->input('intent') === 'submit') {
                $missing = $this->growth->missing($assessment->fresh('framework'));
                if ($missing) {
                    return back()->withErrors(['growth' => 'Your answers were saved. Still to do: '.implode('; ', array_slice($missing, 0, 5)).(count($missing) > 5 ? '…' : '')]);
                }
                $this->growth->submit($assessment, $request->user());

                return redirect()->route('workspace.growth.assessment', [$startup, $assessment])->with('status', 'Assessment submitted. Here are your results.');
            }
        } catch (RegistryException $exception) {
            return back()->withErrors(['growth' => $exception->getMessage()]);
        }

        return back()->with('status', 'Answers saved. You can come back and finish later.');
    }

    public function report(Request $request, Startup $startup, VgiAssessment $assessment): View
    {
        $this->membershipOr404($request, $startup);
        abort_unless($assessment->startup_uuid === $startup->uuid && $assessment->status === 'submitted', 404);

        return view('workspace.growth.report', [
            'startup' => $startup->load('profile'),
            'assessment' => $assessment->load('framework', 'responses', 'submitter', 'overrider'),
            'actions' => GrowthAction::query()->with('owner')->where('startup_uuid', $startup->uuid)->where('status', '!=', 'done')->orderBy('due_date')->get(),
        ]);
    }
}
