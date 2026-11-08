<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceService;
use App\Models\Marketplace\Inquiry;
use App\Models\Marketplace\Listing;
use App\Models\Marketplace\Report;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Reports and disputes, listing moderation and simple marketplace figures. */
class MarketplaceController extends Controller
{
    public function __construct(private readonly MarketplaceService $market) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');

        return view('admin.marketplace.index', [
            'status' => $status,
            'reports' => Report::query()->with('listing.startup', 'reporter', 'resolver')
                ->when(array_key_exists($status, Report::STATUSES), fn ($q) => $q->where('status', $status))->latest()->paginate(25)->withQueryString(),
            'figures' => [
                'Published listings' => Listing::query()->live()->count(),
                'Offers' => Listing::query()->live()->where('kind', 'offer')->count(),
                'Requests to buy' => Listing::query()->live()->where('kind', 'request')->count(),
                'Partnerships' => Listing::query()->live()->where('kind', 'partnership')->count(),
                'Conversations (30 days)' => Inquiry::query()->where('created_at', '>=', now()->subDays(30))->count(),
                'Open reports' => Report::query()->where('status', 'open')->count(),
            ],
        ]);
    }

    /** A reported conversation. Only reachable through a report, and the view is audited. */
    public function report(Report $report): View
    {
        $report->load('listing.startup', 'reporter', 'inquiry.messages.sender', 'inquiry.inquirer');
        if ($report->inquiry) {
            Audit::log('marketplace.dispute_viewed', $report->inquiry, ['report_id' => $report->id]);
        }

        return view('admin.marketplace.report', ['report' => $report]);
    }

    public function resolve(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['resolved', 'dismissed'])],
            'resolution_note' => ['required', 'string', 'max:2000'],
        ]);
        $this->market->resolve($report, $request->user(), $data['status'], $data['resolution_note'], $request->boolean('remove_listing'));

        return redirect()->route('admin.marketplace.index')->with('status', 'Report '.$data['status'].'. The reporter has been told.');
    }

    public function restore(Listing $listing): RedirectResponse
    {
        abort_unless($listing->status === 'removed', 404);
        $listing->forceFill(['status' => 'draft', 'moderation_note' => null])->save();
        Audit::log('marketplace.listing_restored', $listing);

        return back()->with('status', 'Listing restored as a draft.');
    }
}
