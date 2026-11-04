<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Growth\GrowthAction;
use App\Models\Growth\GrowthUpdate;
use App\Models\Growth\VgiAssessment;
use App\Models\Registry\Startup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The startup's growth plan: actions, owners, due dates and progress notes. */
class GrowthActionController extends Controller
{
    use ResolvesMembership;

    public function store(Request $request, Startup $startup): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can change the growth plan.');
        $data = $request->validate([
            'title' => ['required_without:question_key', 'nullable', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:2000'],
            'assessment_uuid' => ['nullable', 'uuid'],
            'question_key' => ['nullable', 'string', 'max:60'],
            'owner_user_id' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
        ]);

        $action = new GrowthAction(['startup_uuid' => $startup->uuid, 'status' => 'todo', 'created_by' => $request->user()->id]);
        if (! empty($data['question_key']) && ! empty($data['assessment_uuid'])) {
            // From a gap in a submitted assessment: copy its tip so the plan explains itself.
            $assessment = VgiAssessment::query()->where('startup_uuid', $startup->uuid)->where('status', 'submitted')->findOrFail($data['assessment_uuid']);
            $gap = collect($assessment->result['gaps'] ?? [])->firstWhere('question_key', $data['question_key']);
            abort_unless($gap !== null, 404);
            $action->fill([
                'assessment_uuid' => $assessment->uuid, 'dimension_key' => $gap['dimension_key'], 'question_key' => $gap['question_key'],
                'title' => mb_substr($gap['tip'] ?: 'Improve: '.$gap['prompt'], 0, 200),
                'details' => 'From your growth assessment ('.$gap['dimension_title'].'): “'.$gap['prompt'].'” Your answer: '.$gap['answer'].'.',
            ]);
        } else {
            $action->fill(['title' => $data['title'], 'details' => $data['details'] ?? null]);
        }
        $action->owner_user_id = $this->validOwner($startup, $data['owner_user_id'] ?? null);
        $action->due_date = $data['due_date'] ?? null;
        $action->save();

        return back()->with('status', 'Added to your growth plan.');
    }

    public function update(Request $request, Startup $startup, GrowthAction $action): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($action->startup_uuid === $startup->uuid, 404);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(GrowthAction::STATUSES))],
            'note' => ['nullable', 'string', 'max:2000'],
            'owner_user_id' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
        ]);
        // Any team member can report progress; only editors can reassign or reschedule.
        if (isset($data['status'])) {
            $action->status = $data['status'];
            $action->completed_at = $data['status'] === 'done' ? ($action->completed_at ?? now()) : null;
        }
        if ($membership->canEdit() && $request->has('owner_user_id')) {
            $action->owner_user_id = $this->validOwner($startup, $data['owner_user_id'] ?? null);
        }
        if ($membership->canEdit() && $request->has('due_date')) {
            $action->due_date = $data['due_date'] ?? null;
        }
        $action->save();
        if (! empty($data['note'])) {
            GrowthUpdate::query()->create(['startup_uuid' => $startup->uuid, 'growth_action_id' => $action->id, 'user_id' => $request->user()->id, 'note' => $data['note']]);
        }

        return back()->with('status', 'Growth plan updated.');
    }

    public function destroy(Request $request, Startup $startup, GrowthAction $action): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($action->startup_uuid === $startup->uuid, 404);
        abort_unless($membership->canEdit(), 403);
        $action->delete();

        return back()->with('status', 'Removed from your growth plan.');
    }

    /** Owners must be on this startup's team. */
    private function validOwner(Startup $startup, mixed $userId): ?int
    {
        if (! $userId) {
            return null;
        }

        return $startup->activeMemberships()->where('user_id', (int) $userId)->exists() ? (int) $userId : null;
    }
}
