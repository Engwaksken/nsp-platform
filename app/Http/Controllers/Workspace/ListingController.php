<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Inquiry;
use App\Models\Marketplace\Listing;
use App\Models\Registry\Startup;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** A startup's marketplace listings and the conversations about them. */
class ListingController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);

        return view('workspace.marketplace.index', [
            'startup' => $startup,
            'membership' => $membership,
            'listings' => Listing::query()->where('startup_uuid', $startup->uuid)->withCount('inquiries')->latest()->get(),
            'inquiries' => Inquiry::query()->with('listing', 'inquirer', 'inquirerStartup')->where('startup_uuid', $startup->uuid)->latest('last_message_at')->limit(50)->get(),
            'listing' => new Listing(['kind' => 'offer']),
        ]);
    }

    public function store(Request $request, Startup $startup): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        $listing = Listing::query()->create($this->validated($request) + ['startup_uuid' => $startup->uuid, 'created_by' => $request->user()->id]);
        Audit::log('marketplace.listing_created', $listing);

        return redirect()->route('workspace.marketplace.edit', [$startup, $listing])->with('status', 'Draft saved. Publish it when you are ready.');
    }

    public function edit(Request $request, Startup $startup, Listing $listing): View
    {
        $membership = $this->editorOr403($request, $startup);
        abort_unless($listing->startup_uuid === $startup->uuid, 404);

        return view('workspace.marketplace.edit', ['startup' => $startup, 'membership' => $membership, 'listing' => $listing]);
    }

    public function update(Request $request, Startup $startup, Listing $listing): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        abort_unless($listing->startup_uuid === $startup->uuid, 404);
        abort_if($listing->status === 'removed', 403, 'Hive took this listing down.');
        $listing->update($this->validated($request));
        Audit::log('marketplace.listing_updated', $listing);

        return back()->with('status', 'Listing saved.');
    }

    public function setStatus(Request $request, Startup $startup, Listing $listing): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        abort_unless($listing->startup_uuid === $startup->uuid, 404);
        abort_if($listing->status === 'removed', 403, 'Hive took this listing down.');
        $status = $request->validate(['status' => ['required', Rule::in(['draft', 'published', 'closed'])]])['status'];
        $listing->forceFill(['status' => $status, 'published_at' => $status === 'published' ? ($listing->published_at ?? now()) : $listing->published_at])->save();
        Audit::log('marketplace.listing_'.$status, $listing);

        return back()->with('status', ['draft' => 'Moved back to draft.', 'published' => 'Published in the marketplace.', 'closed' => 'Closed.'][$status]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', Rule::in(array_keys(Listing::KINDS))],
            'category' => ['required', Rule::in(array_keys(Listing::categories()))],
            'title' => ['required', 'string', 'max:150'],
            'summary' => ['required', 'string', 'max:300'],
            'description' => ['required', 'string', 'max:6000'],
            'price_note' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:120'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
    }

    private function editorOr403(Request $request, Startup $startup)
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can manage listings.');

        return $membership;
    }
}
