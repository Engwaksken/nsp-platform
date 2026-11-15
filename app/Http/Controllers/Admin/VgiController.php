<?php

namespace App\Http\Controllers\Admin;

use App\Growth\FrameworkDefinition;
use App\Growth\GrowthService;
use App\Http\Controllers\Controller;
use App\Models\Growth\VgiAssessment;
use App\Models\Growth\VgiFramework;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Rubric versions (draft → approved → retired) and submitted assessments. */
class VgiController extends Controller
{
    public function __construct(private readonly GrowthService $growth) {}

    public function index(Request $request): View
    {
        $this->authorizeView($request);

        return view('admin.vgi.index', [
            'frameworks' => VgiFramework::query()->with('approver', 'editor')->orderByDesc('id')->get(),
            'current' => VgiFramework::current(),
            'assessments' => $request->user()->can('review-assessments')
                ? VgiAssessment::query()->with('startup', 'framework')->where('status', 'submitted')->latest('submitted_at')->paginate(25)
                : null,
        ]);
    }

    public function createDraft(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $existing = VgiFramework::query()->where('status', 'draft')->first();
        if ($existing) {
            return redirect()->route('admin.vgi.edit', $existing)->with('status', 'A draft already exists. Continue editing it.');
        }
        $framework = $this->growth->createDraft($request->user());

        return redirect()->route('admin.vgi.edit', $framework)->with('status', 'Draft version '.$framework->version.' created.');
    }

    public function edit(Request $request, VgiFramework $framework): View
    {
        $this->authorizeView($request);

        return view('admin.vgi.edit', [
            'framework' => $framework,
            'problems' => FrameworkDefinition::validate($framework->definition),
            'questionsByDimension' => old('questions', collect($framework->definition['dimensions'] ?? [])
                ->mapWithKeys(fn ($dimension) => [$dimension['key'] => $dimension['questions'] ?? []])->all()),
        ]);
    }

    public function update(Request $request, VgiFramework $framework): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
            'questions' => ['required', 'array'],
            'questions.*' => ['required', 'array', 'min:1'],
            'questions.*.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,59}$/', 'max:60'],
            'questions.*.*.prompt' => ['required', 'string', 'max:1000'],
            'questions.*.*.help' => ['nullable', 'string', 'max:2000'],
            'questions.*.*.weight' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'questions.*.*.evidence' => ['required', 'in:none,optional,required'],
            'questions.*.*.evidence_hint' => ['nullable', 'string', 'max:2000'],
            'questions.*.*.options' => ['required', 'array', 'min:2', 'max:7'],
            'questions.*.*.options.*.key' => ['required', 'string', 'regex:/^[a-z0-9_]{1,30}$/', 'max:30'],
            'questions.*.*.options.*.label' => ['required', 'string', 'max:500'],
            'questions.*.*.options.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'questions.*.*.options.*.insufficient' => ['nullable', 'boolean'],
            'questions.*.*.options.*.tip' => ['nullable', 'string', 'max:2000'],
        ]);
        $definition = $framework->definition;
        foreach ($definition['dimensions'] as &$dimension) {
            $submitted = $data['questions'][$dimension['key']] ?? null;
            if (! is_array($submitted)) {
                return back()->withInput()->withErrors(['questions' => 'Each dimension needs at least one question.']);
            }
            $dimension['questions'] = [];
            foreach (array_values($submitted) as $question) {
                $question['help'] = filled($question['help'] ?? null) ? trim($question['help']) : null;
                if (filled($question['evidence_hint'] ?? null)) {
                    $question['evidence_hint'] = trim($question['evidence_hint']);
                } else {
                    unset($question['evidence_hint']);
                }
                $question['options'] = array_values($question['options']);
                foreach ($question['options'] as &$option) {
                    $option['insufficient'] = (bool) ($option['insufficient'] ?? false);
                    if ($option['insufficient']) {
                        unset($option['score']);
                    } else {
                        $option['score'] = (float) ($option['score'] ?? -1);
                    }
                    $option['tip'] = filled($option['tip'] ?? null) ? trim($option['tip']) : null;
                }
                unset($option);
                $dimension['questions'][] = $question;
            }
        }
        unset($dimension);
        $problems = FrameworkDefinition::validate($definition);
        if ($problems) {
            return back()->withInput()->withErrors(['questions' => $problems]);
        }
        try {
            $this->growth->updateDraft($framework, $request->user(), $definition, $data['description'] ?? null);
        } catch (RegistryException $exception) {
            return back()->withInput()->withErrors(['questions' => $exception->getMessage()]);
        }

        return back()->with('status', 'Draft saved.');
    }

    public function approve(Request $request, VgiFramework $framework): RedirectResponse
    {
        abort_unless($request->user()->can('approve-vgi-frameworks'), 403);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        try {
            $this->growth->approve($framework, $request->user(), $data['note']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['approve' => $exception->getMessage()]);
        }

        return redirect()->route('admin.vgi.index')->with('status', 'Version '.$framework->version.' approved. Founders now use it for new assessments.');
    }

    public function showAssessment(Request $request, VgiAssessment $assessment): View
    {
        abort_unless($request->user()->can('review-assessments') && $assessment->status === 'submitted', 403);
        Audit::log('vgi.staff_viewed_assessment', $assessment);

        return view('admin.vgi.assessment', [
            'assessment' => $assessment->load('startup', 'framework', 'responses', 'submitter', 'overrider'),
            'reproducible' => $this->growth->verify($assessment),
        ]);
    }

    public function override(Request $request, VgiAssessment $assessment): RedirectResponse
    {
        abort_unless($request->user()->can('review-assessments'), 403);
        $bands = array_column($assessment->framework->definition['bands'], 'label');
        $data = $request->validate([
            'override_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'override_band' => ['nullable', 'in:'.implode(',', array_map(fn ($b) => str_replace(',', '', $b), $bands))],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        try {
            $this->growth->override($assessment, $request->user(),
                isset($data['override_score']) ? round((float) $data['override_score'], 1) : null, $data['override_band'] ?? null, $data['reason']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        return back()->with('status', 'Adjustment saved. Founders see both the calculated result and your adjustment with its reason.');
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()->canAny(['edit-vgi-frameworks', 'approve-vgi-frameworks', 'review-assessments']), 403);
    }

    private function authorizeEdit(Request $request): void
    {
        abort_unless($request->user()->can('edit-vgi-frameworks'), 403);
    }
}
