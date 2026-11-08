<?php

namespace App\Http\Controllers\Workspace;

use App\Ecosystem\Eligibility;
use App\Http\Controllers\Controller;
use App\Models\Registry\DataSharingConsent;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** One startup at a glance, and a download of the startup's own data. Team only. */
class OverviewController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $uuid = $startup->uuid;
        $cards = [];
        $cards[] = ['Profile complete', ($startup->profile?->profile_completion ?? 0).'%', route('workspace.startups.edit', $startup)];
        $cards[] = ['Team members', StartupMembership::query()->where('startup_uuid', $uuid)->where('status', 'active')->count(), route('workspace.startups.team', $startup)];
        if (Features::enabled('vgi')) {
            $cards[] = ['Growth stage', Eligibility::stage($startup) ?? 'Not assessed', route('workspace.growth', $startup)];
            $cards[] = ['Open growth actions', DB::table('growth_actions')->where('startup_uuid', $uuid)->where('status', '!=', 'done')->count(), route('workspace.growth', $startup)];
        }
        if (Features::enabled('funding')) {
            $cards[] = ['Investor introductions', DB::table('investor_introductions')->where('startup_uuid', $uuid)->whereIn('status', ['requested', 'accepted'])->count(), route('workspace.funding', $startup)];
        }
        if (Features::enabled('experts')) {
            $cards[] = ['Active expert engagements', DB::table('expert_engagements')->where('startup_uuid', $uuid)->where('status', 'active')->count(), route('workspace.experts', $startup)];
        }
        if (Features::enabled('talent')) {
            $cards[] = ['New job applications', DB::table('job_applications')->where('startup_uuid', $uuid)->where('status', 'submitted')->count(), route('workspace.talent', $startup)];
        }
        if (Features::enabled('marketplace')) {
            $cards[] = ['Unread marketplace messages', DB::table('marketplace_inquiries')->where('startup_uuid', $uuid)->whereNotNull('last_message_at')->whereNull('team_read_at')->count(), route('workspace.marketplace', $startup)];
        }
        if (Features::enabled('ims_integration')) {
            $cards[] = ['Hive programs', DB::table('startup_program_records')->where('startup_uuid', $uuid)->where('enrolled', true)->count(), route('workspace.startups.programs', $startup)];
        }

        return view('workspace.startups.overview', ['startup' => $startup, 'membership' => $membership, 'cards' => $cards]);
    }

    /**
     * The startup's own records as JSON (data portability). Owners, founders and admins only.
     * It contains what the startup entered and its team list; it leaves out other people's
     * private data (candidates' CVs and notes, investors' and experts' private notes, messages).
     */
    public function export(Request $request, Startup $startup): JsonResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can download the startup’s data.');
        $uuid = $startup->uuid;
        $startup->load('profile');
        Audit::log('workspace.data_exported', $startup);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'startup' => $startup->only(['uuid', 'legal_name', 'display_name', 'registration_number', 'country', 'year_founded', 'description', 'website', 'status', 'created_at']),
            'profile' => $startup->profile?->only(['sector', 'sub_sector', 'headquarters_location', 'team_size', 'revenue_band', 'traction_summary', 'funding_raised_amount', 'funding_currency', 'contact_email', 'contact_phone', 'visibility', 'profile_completion']),
            'team' => StartupMembership::query()->with('user:id,name,email')->where('startup_uuid', $uuid)->where('status', 'active')->get()
                ->map(fn ($m) => ['name' => $m->user?->name, 'email' => $m->user?->email, 'role' => $m->role])->values(),
            'consents' => DataSharingConsent::query()->where('startup_uuid', $uuid)->get(['purpose', 'policy_version', 'granted_at', 'revoked_at']),
            'growth_assessments' => DB::table('vgi_assessments')->where('startup_uuid', $uuid)->get(['uuid', 'status', 'score', 'band_label', 'submitted_at']),
            'growth_actions' => DB::table('growth_actions')->where('startup_uuid', $uuid)->get(['title', 'status', 'due_date', 'completed_at', 'created_at']),
            'funding_requests' => DB::table('funding_requests')->where('startup_uuid', $uuid)->get(['amount', 'currency', 'instruments', 'pitch', 'use_of_funds', 'status', 'created_at', 'closed_at']),
            'data_room_files' => DB::table('data_room_files')->where('startup_uuid', $uuid)->get(['title', 'category', 'original_name', 'size', 'created_at']),
            'vacancies' => DB::table('vacancies')->where('startup_uuid', $uuid)->get(['title', 'type', 'status', 'published_at', 'closes_on']),
            'marketplace_listings' => DB::table('marketplace_listings')->where('startup_uuid', $uuid)->get(['kind', 'category', 'title', 'summary', 'status', 'published_at']),
            'program_records' => DB::table('startup_program_records')->where('startup_uuid', $uuid)->get(['program_title', 'cohort_name', 'status', 'enrolled', 'submitted_at']),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="'.str($startup->display_name)->slug().'-data-'.now()->format('Ymd').'.json"',
            'Cache-Control' => 'private, no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
