<?php

namespace App\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Platform KPIs (ANA-603). Each one follows the definition in docs/kpi-definitions.md,
 * uses aggregate counts only, and never returns names or contact details.
 * IMS figures come from startup_program_records (the summaries IMS sends) and are
 * reported as counts of startups, never individual applications.
 */
class Kpis
{
    public function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    public static function forDays(int $days): self
    {
        $to = CarbonImmutable::now();

        return new self($to->subDays($days)->startOfDay(), $to);
    }

    /** @return array<string, array{label: string, value: string|int|float, detail: string, group: string}> */
    public function all(): array
    {
        $activeStartups = $this->activeStartupCount();
        $liveStartups = DB::table('startups')->where('status', 'active')->count();

        return [
            'registrations_users' => $this->kpi('Growth', 'New accounts', $this->countBetween('users', 'created_at'), 'People who created an account in the period.'),
            'registrations_startups' => $this->kpi('Growth', 'New startups', DB::table('startups')->where('status', '!=', 'merged')->whereBetween('created_at', $this->range())->count(), 'Startup profiles registered in the period (merged duplicates excluded).'),
            'active_startups' => $this->kpi('Growth', 'Active startups', $activeStartups, 'Active startups where at least one team member signed in during the period, out of '.number_format($liveStartups).' active profiles.'),
            'retention' => $this->kpi('Growth', 'Returning users', $this->retention(), 'Of people who signed in during the previous period of the same length, the share who signed in again in this one.'),
            'onboarding_completion' => $this->kpi('Startups', 'Profiles at least 80% complete', $this->percent(DB::table('startup_profiles')->join('startups', 'startups.uuid', '=', 'startup_profiles.startup_uuid')->where('startups.status', 'active')->where('profile_completion', '>=', 80)->count(), $liveStartups), 'Share of active startups whose profile is at least 80% complete (today).'),
            'vgi_completion' => $this->kpi('Startups', 'Startups with a growth assessment', $this->percent(DB::table('vgi_assessments')->join('startups', 'startups.uuid', '=', 'vgi_assessments.startup_uuid')->where('startups.status', 'active')->where('vgi_assessments.status', 'submitted')->distinct()->count('vgi_assessments.startup_uuid'), $liveStartups), number_format($this->countBetween('vgi_assessments', 'submitted_at', fn ($q) => $q->where('status', 'submitted'))).' assessments submitted in the period. Share of active startups with at least one (today).'),
            'program_participation' => $this->kpi('Startups', 'Startups enrolled in Hive programs', DB::table('startup_program_records')->where('enrolled', true)->distinct()->count('startup_uuid'), 'From IMS summaries (aggregate only). '.number_format(DB::table('startup_program_records')->whereBetween('submitted_at', $this->range())->distinct()->count('startup_uuid')).' startups applied through NSP prefill in the period.'),
            'opportunity_engagement' => $this->kpi('Ecosystem', 'Opportunity saves and registrations', $this->countBetween('opportunity_bookmarks', 'created_at') + $this->countBetween('opportunity_interests', 'created_at'), number_format(DB::table('opportunities')->where('status', 'published')->where(fn ($q) => $q->whereNull('deadline_at')->orWhere('deadline_at', '>=', now()))->count()).' opportunities open now.'),
            'matches' => $this->kpi('Ecosystem', 'New matches', $this->countBetween('investor_introductions', 'created_at') + $this->countBetween('expert_engagements', 'created_at'), 'Investor introductions and expert or mentor engagements started in the period.'),
            'introductions' => $this->kpi('Ecosystem', 'Introductions accepted', $this->countBetween('investor_introductions', 'decided_at', fn ($q) => $q->where('status', 'accepted')), 'Investor introductions accepted in the period.'),
            'funding_pipeline' => $this->kpi('Ecosystem', 'Open funding requests', DB::table('funding_requests')->where('status', 'open')->count(), $this->fundingTotals()),
            'jobs' => $this->kpi('Ecosystem', 'Vacancies published', $this->countBetween('vacancies', 'published_at'), number_format($this->countBetween('job_applications', 'created_at')).' applications and '.number_format($this->countBetween('hiring_pipeline_events', 'created_at', fn ($q) => $q->where('to_status', 'hired'))).' hires in the period.'),
            'marketplace' => $this->kpi('Ecosystem', 'Marketplace conversations', $this->countBetween('marketplace_inquiries', 'created_at'), number_format(DB::table('marketplace_listings')->where('status', 'published')->count()).' listings published now.'),
        ];
    }

    /** New accounts and startups per month, for the last $months months. @return list<array{month: string, users: int, startups: int}> */
    public static function monthly(int $months = 6): array
    {
        $rows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths($i);
            $range = [$start, $start->endOfMonth()];
            $rows[] = [
                'month' => $start->format('M Y'),
                'users' => DB::table('users')->whereBetween('created_at', $range)->count(),
                'startups' => DB::table('startups')->where('status', '!=', 'merged')->whereBetween('created_at', $range)->count(),
            ];
        }

        return $rows;
    }

    private function activeStartupCount(): int
    {
        $signedIn = DB::table('login_audits')->where('success', true)->whereBetween('created_at', $this->range())->whereNotNull('user_id')->select('user_id');

        return DB::table('startup_memberships')->join('startups', 'startups.uuid', '=', 'startup_memberships.startup_uuid')
            ->where('startups.status', 'active')->where('startup_memberships.status', 'active')
            ->whereIn('startup_memberships.user_id', $signedIn)->distinct()->count('startup_memberships.startup_uuid');
    }

    private function retention(): string
    {
        $length = $this->from->diffInSeconds($this->to);
        $previous = [$this->from->subSeconds((int) $length), $this->from];
        $before = DB::table('login_audits')->where('success', true)->whereNotNull('user_id')->whereBetween('created_at', $previous)->distinct()->pluck('user_id');
        if ($before->isEmpty()) {
            return 'n/a';
        }
        $returned = DB::table('login_audits')->where('success', true)->whereIn('user_id', $before)->whereBetween('created_at', $this->range())->distinct()->count('user_id');

        return $this->percent($returned, $before->count());
    }

    private function fundingTotals(): string
    {
        $totals = DB::table('funding_requests')->where('status', 'open')->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency');

        return $totals->isEmpty() ? 'No open requests.' : 'Being raised: '.$totals->map(fn ($total, $currency) => $currency.' '.number_format((float) $total))->join(', ').'.';
    }

    private function countBetween(string $table, string $column, ?callable $scope = null): int
    {
        $query = DB::table($table)->whereBetween($column, $this->range());
        if ($scope) {
            $scope($query);
        }

        return $query->count();
    }

    private function percent(int $part, int $whole): string
    {
        return $whole > 0 ? round($part / $whole * 100).'%' : 'n/a';
    }

    private function range(): array
    {
        return [$this->from, $this->to];
    }

    private function kpi(string $group, string $label, string|int|float $value, string $detail): array
    {
        return ['group' => $group, 'label' => $label, 'value' => $value, 'detail' => $detail];
    }
}
