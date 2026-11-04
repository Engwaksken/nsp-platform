<?php

namespace App\Http\Controllers\Admin;

use App\Analytics\Kpis;
use App\Http\Controllers\Controller;
use App\Models\Registry\Startup;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ecosystem KPIs for staff. Aggregates only; exports are audited. */
class InsightsController extends Controller
{
    public const PERIODS = [30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'];

    public function index(Request $request): View
    {
        $days = $this->days($request);
        $kpis = Kpis::forDays($days);

        return view('admin.insights.index', [
            'days' => $days,
            'kpis' => collect($kpis->all())->groupBy('group', true),
            'monthly' => Kpis::monthly(6),
            'from' => $kpis->from,
        ]);
    }

    public function exportKpis(Request $request): StreamedResponse
    {
        $days = $this->days($request);
        $kpis = Kpis::forDays($days);
        Audit::log('insights.kpis_exported', null, ['days' => $days]);
        $rows = collect($kpis->all())->map(fn ($kpi, $key) => [$key, $kpi['group'], $kpi['label'], $kpi['value'], $kpi['detail'], $kpis->from->toDateString(), $kpis->to->toDateString()]);

        return Csv::download('nsp-kpis-'.$days.'d-'.now()->format('Ymd').'.csv', ['KPI', 'Group', 'Label', 'Value', 'Detail', 'From', 'To'], $rows);
    }

    /** Registry list without contact details, financials or team members. */
    public function exportStartups(): StreamedResponse
    {
        abort_unless(auth()->user()->can('manage-startups'), 403);
        Audit::log('insights.startups_exported');
        $rows = Startup::query()->with('profile')->where('status', '!=', 'merged')->orderBy('display_name')->lazy()->map(fn (Startup $s) => [
            $s->uuid, $s->display_name, $s->legal_name, $s->registration_number, Startup::COUNTRIES[$s->country] ?? $s->country, $s->year_founded,
            $s->profile?->sector, $s->profile?->headquarters_location, $s->profile?->profile_completion, $s->status, $s->created_at->toDateString(),
        ]);

        return Csv::download('nsp-startups-'.now()->format('Ymd').'.csv',
            ['UUID', 'Display name', 'Legal name', 'Registration number', 'Country', 'Founded', 'Sector', 'Location', 'Profile %', 'Status', 'Registered'], $rows);
    }

    private function days(Request $request): int
    {
        $days = (int) $request->query('days', 30);

        return array_key_exists($days, self::PERIODS) ? $days : 30;
    }
}
