<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registry\DuplicateCandidate;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMerge;
use App\Registry\RegistryException;
use App\Registry\RegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Human review of possible duplicates. Nothing is merged without a reviewer and written evidence. */
class DuplicateController extends Controller
{
    public function __construct(private readonly RegistryService $registry) {}

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'not_duplicate', 'merged'], true) ? $request->query('status') : 'pending';

        return view('admin.duplicates.index', [
            'status' => $status,
            'candidates' => DuplicateCandidate::query()->with(['startupA.profile', 'startupB.profile', 'decider'])
                ->where('status', $status)->orderByDesc('score')->latest('id')->paginate(25)->withQueryString(),
            'merges' => StartupMerge::query()->with('source', 'target', 'merger')->latest('merged_at')->limit(20)->get(),
        ]);
    }

    public function scan(): RedirectResponse
    {
        $found = $this->registry->scanForDuplicates();

        return back()->with('status', $found === 0 ? 'No new possible duplicates found.' : $found.' new possible duplicate'.($found === 1 ? '' : 's').' to review.');
    }

    public function decide(Request $request, DuplicateCandidate $candidate): RedirectResponse
    {
        abort_unless($candidate->status === 'pending', 409, 'This pair was already reviewed.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['not_duplicate', 'merge'])],
            'keep' => ['required_if:decision,merge', Rule::in([$candidate->startup_a_uuid, $candidate->startup_b_uuid])],
            'notes' => ['required', 'string', 'min:10', 'max:2000'],
        ], ['notes.required' => 'Write down the evidence for your decision.', 'notes.min' => 'Write down the evidence for your decision.']);

        try {
            if ($data['decision'] === 'not_duplicate') {
                $this->registry->dismissCandidate($candidate, $request->user(), $data['notes']);

                return back()->with('status', 'Marked as different companies.');
            }
            $target = Startup::query()->findOrFail($data['keep']);
            $source = Startup::query()->findOrFail($data['keep'] === $candidate->startup_a_uuid ? $candidate->startup_b_uuid : $candidate->startup_a_uuid);
            $this->registry->merge($source, $target, $request->user(), $data['notes'], $candidate);
        } catch (RegistryException $exception) {
            return back()->withErrors(['merge' => $exception->getMessage()]);
        }

        return back()->with('status', 'Merged into '.$target->display_name.'. You can undo this from the list of recent merges.');
    }

    public function undo(Request $request, StartupMerge $merge): RedirectResponse
    {
        try {
            $this->registry->undoMerge($merge, $request->user());
        } catch (RegistryException $exception) {
            return back()->withErrors(['merge' => $exception->getMessage()]);
        }

        return back()->with('status', 'Merge undone. Both startups are active again.');
    }
}
