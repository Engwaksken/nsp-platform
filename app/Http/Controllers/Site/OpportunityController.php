<?php

namespace App\Http\Controllers\Site;

use App\Ecosystem\Eligibility;
use App\Http\Controllers\Controller;
use App\Models\Ecosystem\Opportunity;
use App\Models\Ecosystem\OpportunityBookmark;
use App\Models\Ecosystem\OpportunityInterest;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\Registry\StartupProfile;
use App\Registry\ActiveStartup;
use App\Support\Audit;
use App\Support\Markdown;
use App\Support\PageBlocks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Public directory of opportunities, plus bookmarks, registrations and submissions for signed-in users. */
class OpportunityController extends Controller
{
    public function index(Request $request): View
    {
        $type = array_key_exists((string) $request->query('type'), Opportunity::TYPES) ? (string) $request->query('type') : null;
        $sector = in_array($request->query('sector'), StartupProfile::SECTORS, true) ? (string) $request->query('sector') : null;
        $opportunities = Opportunity::query()->open()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($request->boolean('online'), fn ($q) => $q->where('is_online', true))
            ->when($request->query('closing') === 'soon', fn ($q) => $q->whereBetween('deadline_at', [now(), now()->addDays(14)]))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('organisation', 'like', $term)->orWhere('summary', 'like', $term));
            })
            ->orderByRaw('deadline_at IS NULL')->orderBy('deadline_at')->orderByDesc('published_at')
            ->get()
            // Sector filtering happens here because eligibility is stored as JSON (portable across MySQL and SQLite).
            ->filter(fn (Opportunity $o) => ! $sector || empty($o->eligibility['sectors']) || in_array($sector, $o->eligibility['sectors'], true))
            ->values();
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator($opportunities->forPage($page, 12), $opportunities->count(), 12, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('site.opportunities.index', [
            'opportunities' => $paginator, 'type' => $type, 'sector' => $sector,
            'bookmarked' => $request->user() ? OpportunityBookmark::query()->where('user_id', $request->user()->id)->pluck('opportunity_id')->all() : [],
        ]);
    }

    public function show(Request $request, Opportunity $opportunity): View
    {
        $user = $request->user();
        $canStaffView = $user && $user->can('manage-opportunities');
        abort_unless($opportunity->status === 'published' || $canStaffView, 404);
        $active = ActiveStartup::membership($user);

        return view('site.opportunities.show', [
            'opportunity' => $opportunity,
            'bodyHtml' => Markdown::render($opportunity->body),
            'checks' => $active ? Eligibility::check($opportunity, $active->startup->load('profile')) : null,
            'activeStartup' => $active?->startup,
            'bookmarked' => $user && OpportunityBookmark::query()->where('user_id', $user->id)->where('opportunity_id', $opportunity->id)->exists(),
            'interest' => $user ? OpportunityInterest::query()->where('user_id', $user->id)->where('opportunity_id', $opportunity->id)->first() : null,
            'memberships' => $user ? StartupMembership::query()->with('startup')->where('user_id', $user->id)->where('status', 'active')->get() : collect(),
        ]);
    }

    public function bookmark(Request $request, Opportunity $opportunity): RedirectResponse
    {
        abort_unless($opportunity->status === 'published', 404);
        $existing = OpportunityBookmark::query()->where('user_id', $request->user()->id)->where('opportunity_id', $opportunity->id)->first();
        if ($existing) {
            $existing->delete();

            return back()->with('status', 'Removed from your saved opportunities.');
        }
        OpportunityBookmark::query()->create(['user_id' => $request->user()->id, 'opportunity_id' => $opportunity->id]);

        return back()->with('status', $opportunity->deadline_at ? 'Saved. We will remind you by email 3 days before the deadline.' : 'Saved.');
    }

    public function register(Request $request, Opportunity $opportunity): RedirectResponse
    {
        abort_unless($opportunity->isOpen() && $opportunity->accepts_interest, 404);
        $data = $request->validate([
            'startup_uuid' => ['nullable', 'uuid'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        $startupUuid = null;
        if (! empty($data['startup_uuid'])) {
            // You can only register on behalf of a startup you are on.
            $membership = StartupMembership::query()->where('user_id', $request->user()->id)->where('status', 'active')->where('startup_uuid', $data['startup_uuid'])->first();
            abort_unless($membership !== null, 403);
            $startupUuid = $membership->startup_uuid;
        }
        $existing = OpportunityInterest::query()->where('opportunity_id', $opportunity->id)->where('user_id', $request->user()->id)->first();
        if (! $existing || $existing->status === 'withdrawn') {
            $left = $opportunity->spacesLeft();
            if ($left !== null && $left <= 0) {
                return back()->withErrors(['register' => 'Sorry, this is full.']);
            }
        }
        OpportunityInterest::query()->updateOrCreate(
            ['opportunity_id' => $opportunity->id, 'user_id' => $request->user()->id],
            ['startup_uuid' => $startupUuid, 'message' => $data['message'] ?? null, 'status' => 'registered'],
        );

        return back()->with('status', $opportunity->type === 'event' ? 'You are registered.' : 'Your interest was sent to the organiser.');
    }

    public function withdraw(Request $request, Opportunity $opportunity): RedirectResponse
    {
        OpportunityInterest::query()->where('opportunity_id', $opportunity->id)->where('user_id', $request->user()->id)->update(['status' => 'withdrawn']);

        return back()->with('status', 'Withdrawn.');
    }

    public function create(): View
    {
        return view('site.opportunities.submit', ['opportunity' => new Opportunity(['type' => 'grant'])]);
    }

    /** Anyone signed in can suggest an opportunity; staff review it before it is published. */
    public function store(Request $request): RedirectResponse
    {
        $data = self::validated($request, staff: false);
        $opportunity = new Opportunity($data + ['status' => 'pending_review', 'accepts_interest' => false]);
        $opportunity->submitted_by = $request->user()->id;
        $opportunity->save();
        Audit::log('opportunity.submitted', $opportunity);

        return redirect()->route('opportunities.index')->with('status', 'Thank you. Hive staff will review your opportunity before it is published.');
    }

    /** Shared by the public submission form and the admin editor. */
    public static function validated(Request $request, bool $staff): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Opportunity::TYPES))],
            'title' => ['required', 'string', 'max:200'],
            'organisation' => ['nullable', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
            'location' => ['nullable', 'string', 'max:160'],
            'is_online' => ['nullable', 'boolean'],
            'amount_text' => ['nullable', 'string', 'max:120'],
            'deadline_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'apply_url' => ['nullable', 'url:https,http', 'max:500'],
            'sectors' => ['nullable', 'array'], 'sectors.*' => [Rule::in(StartupProfile::SECTORS)],
            'countries' => ['nullable', 'array'], 'countries.*' => [Rule::in(array_keys(Startup::COUNTRIES))],
            'stages' => ['nullable', 'array'], 'stages.*' => ['string', 'max:80'],
            'registered_only' => ['nullable', 'boolean'],
            'max_age_years' => ['nullable', 'integer', 'min:1', 'max:50'],
            'accepts_interest' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], ['apply_url.url' => 'Enter the full address, starting with https://']);

        $eligibility = array_filter([
            'sectors' => array_values($data['sectors'] ?? []),
            'countries' => array_values($data['countries'] ?? []),
            'stages' => array_values($data['stages'] ?? []),
            'registered_only' => $request->boolean('registered_only') ?: null,
            'max_age_years' => $data['max_age_years'] ?? null,
        ]);
        $clean = array_intersect_key($data, array_flip(['type', 'title', 'organisation', 'summary', 'body', 'location', 'amount_text', 'deadline_at', 'starts_at', 'ends_at', 'apply_url']));
        $clean['apply_url'] = PageBlocks::safeUrl((string) ($clean['apply_url'] ?? '')) ?: null;
        $clean['is_online'] = $request->boolean('is_online');
        $clean['eligibility'] = $eligibility ?: null;
        if ($staff) {
            $clean['accepts_interest'] = $request->boolean('accepts_interest');
            $clean['capacity'] = $data['capacity'] ?? null;
        }

        return $clean;
    }
}
