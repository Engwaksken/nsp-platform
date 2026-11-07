<?php

namespace App\Http\Controllers\Workspace;

use App\Growth\GrowthService;
use App\Http\Controllers\Controller;
use App\Intelligence\GuidanceService;
use App\Models\Builder\BuilderCurriculum;
use App\Models\Builder\VentureBuilderEnrollment;
use App\Models\Growth\GrowthAction;
use App\Models\Growth\VgiAssessment;
use App\Models\Growth\VgiFramework;
use App\Models\Growth\VgiGuidanceFeedback;
use App\Models\Registry\Startup;
use App\Registry\RegistryException;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class GrowthController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly GrowthService $growth, private readonly GuidanceService $guidance) {}

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $assessments = VgiAssessment::query()->with('framework')->where('startup_uuid', $startup->uuid)
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 ELSE 1 END")->latest('submitted_at')->get();
        $latest = $assessments->firstWhere('status', 'submitted');
        $submittedHistory = $assessments->where('status', 'submitted')->values();
        $previous = $submittedHistory->get(1);
        $dimensionTrends = collect($latest?->result['dimensions'] ?? [])->map(function (array $dimension) use ($latest, $previous) {
            $prior = collect($previous?->result['dimensions'] ?? [])->firstWhere('key', $dimension['key']);
            $comparable = $previous && $latest->framework_id === $previous->framework_id
                && $dimension['score'] !== null && ($prior['score'] ?? null) !== null;
            $change = $comparable ? round((float) $dimension['score'] - (float) $prior['score'], 1) : null;

            return [
                'key' => $dimension['key'], 'title' => $dimension['title'], 'score' => $dimension['score'],
                'previous' => $comparable ? $prior['score'] : null, 'change' => $change,
                'direction' => $change === null ? 'not_comparable' : ($change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'unchanged')),
            ];
        })->all();
        $planned = GrowthAction::query()->where('startup_uuid', $startup->uuid)->whereNotNull('question_key')->pluck('question_key')->all();

        return view('workspace.growth.index', [
            'startup' => $startup,
            'membership' => $membership,
            'framework' => VgiFramework::current(),
            'latest' => $latest,
            'dimensionTrends' => $dimensionTrends,
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

        $builderLinks = [];
        if ($assessment->status === 'submitted' && Features::enabled('venture_builder') && Route::has('workspace.builder')) {
            $curriculum = BuilderCurriculum::current();
            if ($curriculum) {
                $enrollment = VentureBuilderEnrollment::activeFor($startup);
                $submissions = $enrollment?->submissions()->get()->keyBy('step_key') ?? collect();
                $gapDimensionKeys = array_values(array_unique(array_column($assessment->result['gaps'] ?? [], 'dimension_key')));
                foreach ($curriculum->orderedSteps() as $step) {
                    foreach ($step->vgi_dimension_keys ?? [] as $dimensionKey) {
                        if (in_array($dimensionKey, $gapDimensionKeys, true) && ! isset($builderLinks[$dimensionKey])) {
                            $submission = $submissions->get($step->key);
                            $builderLinks[$dimensionKey] = $submission
                                ? route('workspace.builder.step', [$startup, $submission])
                                : route('workspace.builder', $startup).'#step-'.$step->key;
                        }
                    }
                }
            }
        }
        $knowledgeGuidance = Features::enabled('knowledge')
            ? $this->guidance->knowledgeForVgiGaps($startup, $request->user(), $assessment->result['gaps'] ?? [])
            : [];

        return view($assessment->status === 'draft' ? 'workspace.growth.form' : 'workspace.growth.result', [
            'startup' => $startup,
            'membership' => $membership,
            'assessment' => $assessment,
            'definition' => $assessment->framework->definition,
            'responses' => $assessment->responses->keyBy('question_key'),
            'planned' => GrowthAction::query()->where('startup_uuid', $startup->uuid)->whereNotNull('question_key')->pluck('question_key')->all(),
            'builderLinks' => $builderLinks,
            'knowledgeGuidance' => $knowledgeGuidance,
            'guidanceFeedback' => VgiGuidanceFeedback::query()->where('assessment_uuid', $assessment->uuid)
                ->where('user_id', $request->user()->id)->get()->keyBy('dimension_key'),
        ]);
    }

    public function guidanceFeedback(Request $request, Startup $startup, VgiAssessment $assessment, string $dimension): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only startup editors can respond to guidance.');
        abort_unless($assessment->startup_uuid === $startup->uuid && $assessment->status === 'submitted', 404);
        abort_unless(collect($assessment->result['gaps'] ?? [])->contains('dimension_key', $dimension), 404);

        $data = $request->validate([
            'feedback' => ['required', 'in:accepted,dismissed'],
            'explanation' => ['nullable', 'string', 'required_if:feedback,dismissed', 'min:5', 'max:1000'],
        ]);
        $feedback = VgiGuidanceFeedback::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'assessment_uuid' => $assessment->uuid, 'dimension_key' => $dimension],
            [
                'startup_uuid' => $startup->uuid,
                'feedback' => $data['feedback'],
                'explanation' => isset($data['explanation']) ? trim($data['explanation']) : null,
            ],
        );
        Audit::log('growth.guidance_feedback', $feedback, [
            'startup_uuid' => $startup->uuid, 'assessment_uuid' => $assessment->uuid,
            'dimension_key' => $dimension, 'feedback' => $feedback->feedback,
        ]);

        return back()->with('status', 'Thanks. Your guidance feedback has been saved.');
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
