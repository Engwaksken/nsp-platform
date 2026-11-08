<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\OpportunityController as SiteOpportunityController;
use App\Models\Ecosystem\Opportunity;
use App\Models\Ecosystem\OpportunityInterest;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Staff: create opportunities, moderate submissions and see registrations. */
class OpportunityController extends Controller
{
    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['pending_review', 'published', 'draft', 'closed', 'rejected', 'archived'], true) ? $request->query('tab') : 'published';
        $query = Opportunity::query()->withCount(['interests' => fn ($q) => $q->whereIn('status', ['registered', 'confirmed'])]);
        if ($tab === 'closed') {
            $query->where('status', 'published')->where(fn ($q) => $q->where('deadline_at', '<', now())->orWhere('ends_at', '<', now()));
        } elseif ($tab === 'published') {
            $query->open();
        } else {
            $query->where('status', $tab);
        }

        return view('admin.opportunities.index', [
            'tab' => $tab,
            'opportunities' => $query->latest('updated_at')->paginate(25)->withQueryString(),
            'pendingCount' => Opportunity::query()->where('status', 'pending_review')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.opportunities.form', ['opportunity' => new Opportunity(['type' => 'grant', 'status' => 'draft'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = SiteOpportunityController::validated($request, staff: true);
        $status = $request->input('publish') ? 'published' : 'draft';
        $opportunity = new Opportunity($data + ['status' => $status]);
        $opportunity->created_by = $request->user()->id;
        $opportunity->published_at = $status === 'published' ? now() : null;
        $opportunity->save();
        Audit::log('opportunity.created', $opportunity, ['status' => $status]);

        return redirect()->route('admin.opportunities.edit', $opportunity)->with('status', $status === 'published' ? 'Published.' : 'Saved as draft.');
    }

    public function edit(Opportunity $opportunity): View
    {
        return view('admin.opportunities.form', ['opportunity' => $opportunity]);
    }

    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $opportunity->fill(SiteOpportunityController::validated($request, staff: true));
        $opportunity->save();
        Audit::log('opportunity.updated', $opportunity);

        return back()->with('status', 'Saved.');
    }

    /** Publish, reject (with a note to the submitter), unpublish or archive. */
    public function moderate(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['publish', 'reject', 'unpublish', 'archive'])],
            'note' => ['nullable', 'required_if:decision,reject', 'string', 'max:1000'],
        ], ['note.required_if' => 'Tell the submitter why it was not published.']);
        $status = ['publish' => 'published', 'reject' => 'rejected', 'unpublish' => 'draft', 'archive' => 'archived'][$data['decision']];
        $opportunity->forceFill([
            'status' => $status,
            'moderation_note' => $data['note'] ?? $opportunity->moderation_note,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'published_at' => $status === 'published' ? ($opportunity->published_at ?? now()) : $opportunity->published_at,
        ])->save();
        Audit::log('opportunity.'.$data['decision'], $opportunity, ['note' => $data['note'] ?? null]);

        return back()->with('status', ['publish' => 'Published.', 'reject' => 'Rejected.', 'unpublish' => 'Moved back to draft.', 'archive' => 'Archived.'][$data['decision']]);
    }

    public function interests(Opportunity $opportunity): View
    {
        return view('admin.opportunities.interests', [
            'opportunity' => $opportunity,
            'interests' => $opportunity->interests()->with('user', 'startup')->latest()->get(),
        ]);
    }

    public function updateInterest(Request $request, Opportunity $opportunity, OpportunityInterest $interest): RedirectResponse
    {
        abort_unless($interest->opportunity_id === $opportunity->id, 404);
        $interest->update($request->validate(['status' => ['required', Rule::in(array_keys(OpportunityInterest::STATUSES))]]));

        return back()->with('status', 'Updated.');
    }

    /** CSV of registrations: names, emails and startups only (no private profile data). */
    public function export(Opportunity $opportunity): StreamedResponse
    {
        Audit::log('opportunity.registrations_exported', $opportunity);
        $rows = $opportunity->interests()->with('user', 'startup')->orderBy('created_at')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Registered at', 'Name', 'Email', 'Startup', 'Status', 'Message']);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => self::csvSafe((string) $v), [
                    $row->created_at->format('Y-m-d H:i'), $row->user?->name, $row->user?->email, $row->startup?->display_name, $row->status, $row->message,
                ]));
            }
            fclose($out);
        }, 'registrations-'.$opportunity->slug.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stop spreadsheet formula injection (=, +, -, @ at the start of a cell). */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
