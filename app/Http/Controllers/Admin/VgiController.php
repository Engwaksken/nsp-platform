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
            'json' => old('definition', json_encode($framework->definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'problems' => FrameworkDefinition::validate($framework->definition),
        ]);
    }

    public function update(Request $request, VgiFramework $framework): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $request->validate(['definition' => ['required', 'string', 'max:500000'], 'description' => ['nullable', 'string', 'max:2000']]);
        $definition = json_decode($data['definition'], true);
        if (! is_array($definition)) {
            return back()->withInput()->withErrors(['definition' => 'This is not valid JSON: '.json_last_error_msg().'.']);
        }
        $problems = FrameworkDefinition::validate($definition);
        if ($problems) {
            return back()->withInput()->withErrors(['definition' => $problems]);
        }
        try {
            $this->growth->updateDraft($framework, $request->user(), $definition, $data['description'] ?? null);
        } catch (RegistryException $exception) {
            return back()->withInput()->withErrors(['definition' => $exception->getMessage()]);
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
