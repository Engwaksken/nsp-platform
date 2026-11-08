<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceService;
use App\Models\Marketplace\Inquiry;
use App\Models\Marketplace\Listing;
use App\Models\Marketplace\Report;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Registry\RegistryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Public marketplace, plus the signed-in conversations ("My messages"). */
class MarketplaceController extends Controller
{
    public function __construct(private readonly MarketplaceService $market) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(array_keys(Listing::KINDS))],
            'category' => ['nullable', Rule::in(array_keys(Listing::categories()))],
        ]);
        $listings = Listing::query()->live()->with('startup')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('title', 'like', '%'.$term.'%')->orWhere('summary', 'like', '%'.$term.'%')))
            ->when($filters['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->latest('published_at')->paginate(24)->withQueryString();

        return view('marketplace.index', ['listings' => $listings, 'filters' => $filters]);
    }

    public function show(Listing $listing): View
    {
        abort_unless($listing->isLive(), 404);
        $user = Auth::user();

        return view('marketplace.show', [
            'listing' => $listing->load('startup.profile'),
            'isOwnTeam' => (bool) ($user && $listing->startup->membershipOf($user)),
            'openInquiry' => $user ? Inquiry::query()->where('listing_uuid', $listing->uuid)->where('inquirer_id', $user->id)->where('status', 'open')->first() : null,
            'myStartups' => $user ? Startup::query()->active()->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active')
                ->whereIn('role', StartupMembership::EDITOR_ROLES))->pluck('display_name', 'uuid')->all() : [],
        ]);
    }

    public function inquire(Request $request, Listing $listing): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:3000'],
            'on_behalf_of' => ['nullable', 'string'],
        ]);
        $startup = ! empty($data['on_behalf_of']) ? Startup::query()->find($data['on_behalf_of']) : null;
        try {
            $inquiry = $this->market->inquire($listing, $request->user(), $data['subject'], $data['body'], $startup);
        } catch (RegistryException $exception) {
            return back()->withErrors(['inquiry' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('marketplace.inquiries.show', $inquiry)->with('status', 'Message sent. We will email you when they reply.');
    }

    public function reportListing(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless($listing->isLive(), 404);
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(Report::REASONS))], 'details' => ['nullable', 'string', 'max:2000']]);
        $this->market->reportListing($listing, $request->user(), $data['reason'], $data['details'] ?? null);

        return back()->with('status', 'Thank you. Hive will review this listing.');
    }

    /** Conversations the user started. Startup teams see theirs in the workspace. */
    public function inbox(Request $request): View
    {
        return view('marketplace.inbox', [
            'inquiries' => Inquiry::query()->with('listing', 'startup')->where('inquirer_id', $request->user()->id)->latest('last_message_at')->get(),
        ]);
    }

    public function conversation(Request $request, Inquiry $inquiry): View
    {
        $side = $this->market->sideOf($request->user(), $inquiry);
        abort_unless($side !== null, 404);
        if ($side !== 'team_viewer') {
            $this->market->markRead($inquiry, $side);
        }

        return view('marketplace.conversation', [
            'inquiry' => $inquiry->load('listing', 'startup', 'inquirer', 'inquirerStartup', 'messages.sender'),
            'side' => $side,
        ]);
    }

    public function reply(Request $request, Inquiry $inquiry): RedirectResponse
    {
        abort_unless($this->market->sideOf($request->user(), $inquiry) !== null, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);

        return $this->attempt(fn () => $this->market->reply($inquiry, $request->user(), $data['body']), 'Message sent.');
    }

    public function close(Request $request, Inquiry $inquiry): RedirectResponse
    {
        abort_unless($this->market->sideOf($request->user(), $inquiry) !== null, 404);

        return $this->attempt(fn () => $this->market->close($inquiry, $request->user()), 'Conversation closed.');
    }

    public function reportInquiry(Request $request, Inquiry $inquiry): RedirectResponse
    {
        abort_unless($this->market->sideOf($request->user(), $inquiry) !== null, 404);
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(Report::REASONS))], 'details' => ['nullable', 'string', 'max:2000']]);

        return $this->attempt(fn () => $this->market->reportInquiry($inquiry, $request->user(), $data['reason'], $data['details'] ?? null),
            'Reported. Hive staff can now read this conversation to help resolve it.');
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (RegistryException $exception) {
            return back()->withErrors(['inquiry' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', $message);
    }
}
