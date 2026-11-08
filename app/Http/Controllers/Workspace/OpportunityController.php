<?php

namespace App\Http\Controllers\Workspace;

use App\Ecosystem\Recommender;
use App\Http\Controllers\Controller;
use App\Models\Ecosystem\Opportunity;
use App\Models\Ecosystem\OpportunityBookmark;
use App\Models\Ecosystem\OpportunityInterest;
use App\Models\Ecosystem\RecommendationFeedback;
use App\Models\Registry\Startup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A startup's opportunities: recommended, saved and registered. */
class OpportunityController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, Startup $startup): View
    {
        $this->membershipOr404($request, $startup);
        $user = $request->user();

        return view('workspace.opportunities', [
            'startup' => $startup,
            'recommended' => Recommender::forStartup($startup->load('profile'), $user),
            'saved' => OpportunityBookmark::query()->with('opportunity')->where('user_id', $user->id)->get()
                ->filter(fn ($b) => $b->opportunity?->status === 'published')->sortBy(fn ($b) => $b->opportunity->deadline_at?->timestamp ?? PHP_INT_MAX),
            'registrations' => OpportunityInterest::query()->with('opportunity', 'user')
                ->where(fn ($q) => $q->where('startup_uuid', $startup->uuid)->orWhere('user_id', $user->id))
                ->where('status', '!=', 'withdrawn')->latest()->get(),
            'mySubmissions' => Opportunity::query()->where('submitted_by', $user->id)->latest()->limit(10)->get(),
        ]);
    }

    public function feedback(Request $request, Startup $startup, Opportunity $opportunity): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        RecommendationFeedback::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'item_type' => 'opportunity', 'item_id' => $opportunity->id],
            ['startup_uuid' => $startup->uuid, 'feedback' => $request->input('feedback') === 'useful' ? 'useful' : 'not_relevant'],
        );

        return back()->with('status', $request->input('feedback') === 'useful' ? 'Thanks for the feedback.' : 'Hidden from your recommendations.');
    }
}
