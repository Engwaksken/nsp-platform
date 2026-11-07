<?php

namespace App\Http\Controllers\Workspace;

use App\Capital\CapitalService;
use App\Http\Controllers\Controller;
use App\Models\Capital\DiligenceItem;
use App\Models\Capital\DocumentRequest;
use App\Models\Capital\Interaction;
use App\Models\Capital\MatchFeedback;
use App\Models\Capital\ShareGrant;
use App\Models\Ecosystem\Opportunity;
use App\Models\Funding\DataRoomFile;
use App\Models\Funding\Introduction;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Capital Exchange for founders: funding profile extras, what each investor may see,
 * due-diligence checklists, document requests, readiness report and investor activity.
 */
class CapitalController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly CapitalService $capital) {}

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $connections = Introduction::query()->with('investor')->where('startup_uuid', $startup->uuid)->where('status', 'accepted')->latest('decided_at')->get();

        return view('workspace.capital.index', [
            'startup' => $startup,
            'membership' => $membership,
            'profile' => $this->capital->profile($startup),
            'open' => $this->capital->openRequest($startup),
            'connections' => $connections,
            'shares' => ShareGrant::query()->active()->with('investor')->where('startup_uuid', $startup->uuid)->get()->keyBy('investor_user_id'),
            'items' => DiligenceItem::query()->with('investor', 'file')->where('startup_uuid', $startup->uuid)
                ->orderByRaw("case status when 'requested' then 0 when 'provided' then 1 else 2 end")->orderBy('due_date')->get()->groupBy('investor_user_id'),
            'documentRequests' => DocumentRequest::query()->with('investor', 'file')->where('startup_uuid', $startup->uuid)->latest()->get(),
            'files' => DataRoomFile::query()->where('startup_uuid', $startup->uuid)->orderBy('title')->get(['uuid', 'title', 'category']),
            'feedback' => MatchFeedback::query()->where('startup_uuid', $startup->uuid)->where('side', 'founder')->get()->keyBy('investor_user_id'),
            'opportunities' => Features::enabled('opportunities')
                ? Opportunity::query()->open()->whereIn('type', config('capital.opportunity_types', []))->orderByRaw('deadline_at is null')->orderBy('deadline_at')->limit(6)->get()
                : collect(),
        ]);
    }

    public function saveProfile(Request $request, Startup $startup): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        $data = $request->validate([
            'round_stage' => ['nullable', Rule::in(array_keys(config('capital.round_stages')))],
            'instruments_sought' => ['nullable', 'array'],
            'instruments_sought.*' => [Rule::in(array_keys(config('capital.instruments')))],
            'use_of_funds_breakdown' => ['nullable', 'array'],
            'use_of_funds_breakdown.*' => ['nullable', 'integer', 'min:0', 'max:100'],
            'traction_summary' => ['nullable', 'string', 'max:3000'],
            'financial_summary' => ['nullable', 'string', 'max:3000'],
        ]);
        $breakdown = collect($data['use_of_funds_breakdown'] ?? [])->only(array_keys(config('capital.use_of_funds_categories')))
            ->map(fn ($p) => (int) $p)->filter()->all();
        if (array_sum($breakdown) > 100) {
            return back()->withErrors(['use_of_funds_breakdown' => 'The use-of-funds percentages add up to more than 100.'])->withInput();
        }
        $this->capital->saveProfile($startup, $request->user(), [
            'round_stage' => $data['round_stage'] ?? null,
            'instruments_sought' => array_values($data['instruments_sought'] ?? []),
            'use_of_funds_breakdown' => $breakdown,
            'traction_summary' => $data['traction_summary'] ?? null,
            'financial_summary' => $data['financial_summary'] ?? null,
        ]);

        return back()->with('status', 'Funding profile saved. Traction and financial summaries stay private unless you share them with an investor.');
    }

    public function grantScopes(Request $request, Startup $startup): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $data = $request->validate([
            'investor_user_id' => ['required', 'integer'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => [Rule::in(array_keys(CapitalService::scopes()))],
            'expires_at' => ['nullable', 'date', 'after:today', 'before_or_equal:'.now()->addDays((int) config('capital.share_max_days', 365))->toDateString()],
        ]);
        $investor = User::query()->findOrFail($data['investor_user_id']);
        try {
            $this->capital->grantScopes($startup, $request->user(), $investor, $data['scopes'] ?? [], $data['expires_at'] ?? null);
        } catch (RegistryException $exception) {
            return back()->withErrors(['share' => $exception->getMessage()]);
        }

        return back()->with('status', 'Sharing updated for '.$investor->name.'.');
    }

    public function revokeScopes(Request $request, Startup $startup, ShareGrant $share): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        abort_unless($share->startup_uuid === $startup->uuid, 404);
        try {
            $this->capital->revokeScopes($startup, $request->user(), $share);
        } catch (RegistryException $exception) {
            return back()->withErrors(['share' => $exception->getMessage()]);
        }

        return back()->with('status', 'Sharing removed. The investor no longer sees this information.');
    }

    public function updateItem(Request $request, Startup $startup, DiligenceItem $item): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        abort_unless($item->startup_uuid === $startup->uuid, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['requested', 'provided'])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'data_room_file_uuid' => ['nullable', 'uuid'],
        ]);
        try {
            $this->capital->updateDiligenceItem($item, $request->user(), $data + ['notes' => null]);
        } catch (RegistryException $exception) {
            return back()->withErrors(['diligence' => $exception->getMessage()]);
        }

        return back()->with('status', 'Checklist item updated.');
    }

    /** Answer a document request with a new upload or an existing data-room document. */
    public function fulfil(Request $request, Startup $startup, DocumentRequest $documentRequest): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($documentRequest->startup_uuid === $startup->uuid, 404);
        abort_unless($membership->canEdit(), 403);
        $data = $request->validate([
            'data_room_file_uuid' => ['nullable', 'uuid', 'required_without:file'],
            'file' => ['nullable', 'required_without:data_room_file_uuid', 'file', 'max:'.FundingController::MAX_KB,
                'extensions:'.FundingController::EXTENSIONS, 'mimetypes:'.implode(',', FundingController::MIME_TYPES)],
            'share_data_room' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:today', 'before_or_equal:'.now()->addDays(180)->toDateString()],
        ]);
        if ($upload = $request->file('file')) {
            $path = $upload->store('data-room/'.$startup->uuid, 'local');
            $file = DataRoomFile::query()->create([
                'startup_uuid' => $startup->uuid, 'title' => $documentRequest->title,
                'category' => array_key_exists($documentRequest->category, DataRoomFile::CATEGORIES) ? $documentRequest->category : 'other',
                'disk' => 'local', 'path' => $path, 'original_name' => mb_substr(basename($upload->getClientOriginalName()), 0, 200),
                'mime_type' => (string) $upload->getMimeType(), 'size' => $upload->getSize(), 'uploaded_by' => $request->user()->id,
            ]);
            Audit::log('funding.file_uploaded', $file, ['title' => $file->title]);
        } else {
            $file = DataRoomFile::query()->where('startup_uuid', $startup->uuid)->findOrFail($data['data_room_file_uuid']);
        }
        try {
            $this->capital->fulfilDocument($documentRequest, $request->user(), $file, $request->boolean('share_data_room'), $data['expires_at'] ?? null);
        } catch (RegistryException $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }

        return back()->with('status', 'Document added to your data room and the investor was told.');
    }

    public function decline(Request $request, Startup $startup, DocumentRequest $documentRequest): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        abort_unless($documentRequest->startup_uuid === $startup->uuid, 404);
        $data = $request->validate(['response_note' => ['nullable', 'string', 'max:1000']]);
        try {
            $this->capital->declineDocument($documentRequest, $request->user(), $data['response_note'] ?? null);
        } catch (RegistryException $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }

        return back()->with('status', 'Request declined.');
    }

    public function feedback(Request $request, Startup $startup): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        $data = $request->validate([
            'investor_user_id' => ['required', 'integer'],
            'rating' => ['required', Rule::in(array_keys(MatchFeedback::RATINGS))],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $investor = User::query()->findOrFail($data['investor_user_id']);
        $this->capital->feedback($startup, $investor, 'founder', $request->user(), $data['rating'], $data['reason'] ?? null);

        return back()->with('status', 'Thanks. Your feedback improves future matches.');
    }

    public function readiness(Request $request, Startup $startup): View
    {
        $this->membershipOr404($request, $startup);
        $startup->loadMissing('profile');

        return view($request->boolean('print') ? 'capital.readiness-print' : 'workspace.capital.readiness', [
            'startup' => $startup,
            'report' => $this->capital->readiness($startup),
            'audience' => 'founder',
            'scopes' => array_keys(CapitalService::scopes()),
            'backUrl' => route('workspace.capital.index', $startup),
        ]);
    }

    public function activity(Request $request, Startup $startup): View
    {
        $this->membershipOr404($request, $startup);

        return view('workspace.capital.activity', [
            'startup' => $startup,
            'interactions' => Interaction::query()->visibleToStartup()->with('investor', 'actor')->where('startup_uuid', $startup->uuid)
                ->latest('created_at')->latest('id')->paginate(50),
        ]);
    }

    private function editorOr403(Request $request, Startup $startup): StartupMembership
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can change funding details.');

        return $membership;
    }
}
