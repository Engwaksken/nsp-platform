<?php

namespace App\Http\Controllers\Capital;

use App\Capital\CapitalService;
use App\Funding\FundingService;
use App\Http\Controllers\Controller;
use App\Models\Capital\DiligenceItem;
use App\Models\Capital\DocumentRequest;
use App\Models\Capital\MatchFeedback;
use App\Models\Capital\PipelineEvent;
use App\Models\Capital\PipelineItem;
use App\Models\Capital\WatchlistEntry;
use App\Models\Funding\DataRoomFile;
use App\Models\Funding\Introduction;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Registry\RegistryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Capital Exchange for investors: private pipeline and watchlist, the readiness
 * report founders shared, and due diligence inside an accepted introduction.
 *
 * Access rules (everything else answers "not found", like InvestorController):
 * - only Hive-verified investors (FundingService::investorProfile);
 * - pipeline and watchlist: startups the investor may already see (listed,
 *   connected or holding a data-room grant); an existing pipeline entry can
 *   still be moved while the startup is active;
 * - readiness: an accepted introduction AND an active share grant, showing
 *   only the granted scopes, re-checked on every request so expiry and
 *   revocation take effect immediately;
 * - diligence and document requests: an accepted introduction.
 */
class InvestorCapitalController extends Controller
{
    public function __construct(private readonly CapitalService $capital, private readonly FundingService $funding) {}

    public function pipeline(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $this->funding->investorProfile($user)) {
            return redirect()->route('investor.apply');
        }

        $items = PipelineItem::query()->with('startup')->where('investor_user_id', $user->id)
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'))->latest('stage_changed_at')->get();
        $watchlist = WatchlistEntry::query()->with('startup')->where('investor_user_id', $user->id)
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'))->latest()->get();
        $connected = Introduction::query()->where('investor_user_id', $user->id)->where('status', 'accepted')->pluck('startup_uuid')->unique()->all();
        $visible = collect([...$items->pluck('startup'), ...$watchlist->pluck('startup')])->unique('uuid')
            ->mapWithKeys(fn (Startup $startup) => [$startup->uuid => $this->funding->investorMaySee($user, $startup)]);

        return view('investors.capital.pipeline', [
            'stages' => PipelineItem::stages(),
            'funnel' => $this->capital->funnel($user),
            'items' => $items->groupBy('stage'),
            'tracked' => $items->keyBy('startup_uuid'),
            'watchlist' => $watchlist,
            'visible' => $visible,
            'connected' => $connected,
            'events' => PipelineEvent::query()->where('investor_user_id', $user->id)->latest('created_at')->latest('id')->limit(25)->get(),
            'names' => Startup::query()->whereIn('uuid', PipelineEvent::query()->where('investor_user_id', $user->id)->select('startup_uuid'))
                ->where('status', 'active')->pluck('display_name', 'uuid'),
        ]);
    }

    public function transition(Request $request, Startup $startup): RedirectResponse
    {
        $user = $this->investorOr404($request);
        abort_unless($startup->status === 'active', 404);
        $tracked = PipelineItem::query()->where('investor_user_id', $user->id)->where('startup_uuid', $startup->uuid)->exists();
        abort_unless($tracked || $this->funding->investorMaySee($user, $startup), 404);
        $data = $request->validate(['stage' => ['required', Rule::in(array_keys(PipelineItem::stages()))]]);
        try {
            $item = $this->capital->transition($user, $startup, $data['stage']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['stage' => $exception->getMessage()]);
        }

        return back()->with('status', $startup->display_name.' moved to '.$item->stageLabel().'. Only you can see your pipeline.');
    }

    public function watch(Request $request, Startup $startup): RedirectResponse
    {
        $user = $this->investorOr404($request);
        abort_unless($this->funding->investorMaySee($user, $startup), 404);
        $this->capital->watch($user, $startup);

        return back()->with('status', $startup->display_name.' added to your watchlist.');
    }

    public function unwatch(Request $request, Startup $startup): RedirectResponse
    {
        $user = $this->investorOr404($request);
        $this->capital->unwatch($user, $startup);

        return back()->with('status', 'Removed from your watchlist.');
    }

    public function readiness(Request $request, Startup $startup): View
    {
        $user = $this->investorOr404($request);
        $this->connectionOr404($user, $startup);
        $share = $this->capital->activeShare($startup, $user);
        abort_unless($share !== null && ($share->scopes ?? []) !== [], 404);
        $scopes = $this->capital->scopesFor($startup, $user);
        $dataRoom = $this->capital->dataRoomGrant($startup, $user) !== null;
        $this->capital->record($startup->uuid, $user->id, 'viewed_readiness', $user, [], oncePerDay: true);
        $startup->loadMissing('profile');

        return view($request->boolean('print') ? 'capital.readiness-print' : 'investors.capital.readiness', [
            'startup' => $startup,
            'report' => $this->capital->readiness($startup, $scopes, $dataRoom),
            'audience' => 'investor',
            'scopes' => $scopes,
            'share' => $share,
            'backUrl' => route('investor.capital.readiness', $startup),
        ]);
    }

    public function diligence(Request $request, Startup $startup): View
    {
        $user = $this->investorOr404($request);
        $connection = $this->connectionOr404($user, $startup);
        $grant = $this->capital->dataRoomGrant($startup, $user);

        return view('investors.capital.diligence', [
            'startup' => $startup,
            'connection' => $connection,
            'items' => DiligenceItem::query()->with('file')->where('startup_uuid', $startup->uuid)->where('investor_user_id', $user->id)
                ->orderByRaw("case status when 'provided' then 0 when 'requested' then 1 else 2 end")->orderBy('due_date')->orderBy('id')->get(),
            'documentRequests' => DocumentRequest::query()->with('file')->where('startup_uuid', $startup->uuid)->where('investor_user_id', $user->id)->latest()->get(),
            'grant' => $grant,
            'hasScopes' => $this->capital->scopesFor($startup, $user) !== [],
            'templates' => collect(config('capital.diligence_templates', []))->map(fn ($t) => $t['label'])->all(),
        ]);
    }

    public function addItems(Request $request, Startup $startup): RedirectResponse
    {
        $user = $this->investorOr404($request);
        $connection = $this->connectionOr404($user, $startup);
        $data = $request->validate([
            'template' => ['nullable', Rule::in(array_keys(config('capital.diligence_templates', [])))],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'items' => ['nullable', 'array', 'max:20'],
            'items.*.title' => ['nullable', 'string', 'max:200'],
            'items.*.category' => ['nullable', Rule::in(array_keys(config('capital.diligence_categories', [])))],
            'items.*.due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        try {
            if (! empty($data['template'])) {
                $created = $this->capital->applyTemplate($connection, $user, $data['template'], $data['due_date'] ?? null);
            } else {
                $items = collect($data['items'] ?? [])->filter(fn ($item) => filled($item['title'] ?? null))
                    ->map(fn ($item) => ['title' => trim($item['title']), 'category' => $item['category'] ?? 'other', 'due_date' => $item['due_date'] ?? ($data['due_date'] ?? null)])
                    ->values()->all();
                if ($items === []) {
                    return back()->withErrors(['items' => 'Choose a template or give at least one item a title.'])->withInput();
                }
                $created = $this->capital->addDiligenceItems($connection, $user, $items);
            }
        } catch (RegistryException $exception) {
            return back()->withErrors(['items' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', $created->count().' checklist item(s) added. The founders were told.');
    }

    public function updateItem(Request $request, DiligenceItem $item): RedirectResponse
    {
        $user = $this->investorOr404($request);
        abort_unless($item->investor_user_id === $user->id && $item->startup?->status === 'active', 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(DiligenceItem::statuses()))],
            'notes' => ['nullable', 'string', 'max:3000'],
            'due_date' => ['nullable', 'date'],
        ]);
        try {
            // A missing due_date leaves the date unchanged; an empty one clears it.
            $this->capital->updateDiligenceItem($item, $user, $data + ['notes' => null]);
        } catch (RegistryException $exception) {
            return back()->withErrors(['diligence' => $exception->getMessage()]);
        }

        return back()->with('status', 'Checklist item updated.');
    }

    public function requestDocument(Request $request, Startup $startup): RedirectResponse
    {
        $user = $this->investorOr404($request);
        $connection = $this->connectionOr404($user, $startup);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::in(array_keys(DataRoomFile::CATEGORIES))],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $this->capital->requestDocument($connection, $user, $data);
        } catch (RegistryException $exception) {
            return back()->withErrors(['document' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Document requested. The founders add it to their data room or decline.');
    }

    public function feedback(Request $request, Startup $startup): RedirectResponse
    {
        $user = $this->investorOr404($request);
        abort_unless($this->funding->investorMaySee($user, $startup), 404);
        $data = $request->validate([
            'rating' => ['required', Rule::in(array_keys(MatchFeedback::RATINGS))],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->capital->feedback($startup, $user, 'investor', $user, $data['rating'], $data['reason'] ?? null);

        return back()->with('status', 'Thanks. Your feedback improves future matches.');
    }

    private function investorOr404(Request $request): User
    {
        $user = $request->user();
        abort_unless($this->funding->investorProfile($user) !== null, 404);

        return $user;
    }

    /** The accepted introduction with an active startup, or 404. */
    private function connectionOr404(User $investor, Startup $startup): Introduction
    {
        abort_unless($startup->status === 'active', 404);
        $connection = $this->capital->connection($startup, $investor);
        abort_unless($connection !== null, 404);

        return $connection;
    }
}
