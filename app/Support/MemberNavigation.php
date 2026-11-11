<?php

namespace App\Support;

use App\Ai\Assistant;
use App\Models\Communication\UserNotification;
use App\Models\Experts\ExpertProfile;
use App\Models\Funding\InvestorProfile;
use App\Models\Organizations\OrganizationMembership;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\Talent\JobApplication;
use App\Models\Talent\TalentProfile;
use App\Models\User;
use App\Registry\ActiveStartup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar for signed-in members (layouts/app.blade.php).
 *
 * Every link is checked against the same things its route checks: the feature
 * flag (the "feature:" middleware), the route and view existing, and for
 * startup pages an active membership of that startup (ResolvesMembership).
 * A link the member would get a 403 or 404 on is never listed.
 *
 * Built once per request; the dashboard reads the same facts (roles, unread count).
 */
class MemberNavigation
{
    public readonly ?StartupMembership $membership;

    public readonly ?Startup $startup;

    /** @var Collection<int, Startup> other startups the member can switch to */
    public readonly Collection $otherStartups;

    public readonly int $unread;

    public readonly ?InvestorProfile $investor;

    public readonly ?ExpertProfile $expert;

    public readonly bool $isCandidate;

    /** @var Collection<int, OrganizationMembership> */
    public readonly Collection $organizations;

    private function __construct(public readonly User $user, ?Startup $viewing)
    {
        $registry = Features::enabled('registry');

        // Startup pages pass the startup being viewed; their controllers already
        // checked membership (404 otherwise). Elsewhere use the active startup.
        $membership = null;
        if ($registry && $viewing?->exists) {
            $membership = StartupMembership::query()->with('startup')->where('user_id', $user->id)
                ->where('startup_uuid', $viewing->uuid)->where('status', 'active')
                ->whereHas('startup', fn ($q) => $q->where('status', 'active'))->first();
        }
        $this->membership = $membership ?? ($registry ? ActiveStartup::membership($user) : null);
        $this->startup = $this->membership?->startup;

        $this->otherStartups = $registry && $this->startup
            ? StartupMembership::query()->with('startup')->where('user_id', $user->id)->where('status', 'active')
                ->where('startup_uuid', '!=', $this->startup->uuid)
                ->whereHas('startup', fn ($q) => $q->where('status', 'active'))->get()
                ->map->startup->sortBy(fn (Startup $s) => strtolower($s->display_name))->values()
            : collect();

        $this->unread = UserNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count();

        // isVerified() reads the owner's is_active flag: hand it the user we already have.
        $this->investor = Features::enabled('funding') ? InvestorProfile::query()->where('user_id', $user->id)->first()?->setRelation('user', $user) : null;
        $this->expert = Features::enabled('experts') ? ExpertProfile::query()->where('user_id', $user->id)->first()?->setRelation('user', $user) : null;
        $this->isCandidate = Features::enabled('talent') && (
            TalentProfile::query()->where('user_id', $user->id)->exists()
            || JobApplication::query()->where('applicant_id', $user->id)->exists()
        );
        $this->organizations = Features::enabled('organizations')
            ? OrganizationMembership::query()->with('organization')->where('user_id', $user->id)->where('status', 'active')
                ->whereHas('organization', fn ($q) => $q->where('status', '!=', 'suspended'))
                ->orderBy('accepted_at')->orderBy('id')->get()
            : collect();
    }

    public static function for(User $user, ?Startup $viewing = null): self
    {
        // Kept on the request, so the layout and the dashboard share one set of queries.
        $key = 'member_navigation:'.$user->id.':'.($viewing?->uuid ?? '');
        $attributes = request()->attributes;
        if (! $attributes->has($key)) {
            $attributes->set($key, new self($user, $viewing));
        }

        return $attributes->get($key);
    }

    /**
     * Sidebar groups: [key, label, icon, links[]] with links [label, url, icon, active, badge].
     * Groups without links are left out.
     *
     * @return array<int, array{key: string, label: string, icon: string, links: array<int, array{label: string, url: string, icon: string, active: bool, badge: int|null}>}>
     */
    public function groups(): array
    {
        $on = fn (string $flag) => Features::enabled($flag);
        $s = $this->startup;
        $groups = [];

        $groups[] = $this->group('overview', 'Overview', 'fa-house', [
            $this->link('workspace', 'Dashboard', 'fa-gauge-high', 'workspace'),
            $this->link('notifications.index', 'Notifications', 'fa-bell', 'notifications.index|notifications.open', badge: $this->unread ?: null),
            $on('registry') && ! $s ? $this->link('workspace.startups.create', 'Register a startup', 'fa-circle-plus', 'workspace.startups.create') : null,
        ]);

        if ($on('registry') && $s) {
            $groups[] = $this->group('startup', 'My startup', 'fa-rocket', [
                $this->link('workspace.startups.overview', 'Startup dashboard', 'fa-chart-simple', 'workspace.startups.overview', $s),
                $this->link('workspace.startups.show', 'Profile', 'fa-id-card', 'workspace.startups.show|workspace.startups.edit', $s),
                $this->link('workspace.startups.team', 'Team', 'fa-users', 'workspace.startups.team*', $s),
                $this->link('workspace.startups.consents', 'Data sharing', 'fa-shield-halved', 'workspace.startups.consents*', $s),
                $this->link('workspace.startups.history', 'Change history', 'fa-clock-rotate-left', 'workspace.startups.history', $s),
                $on('organizations') ? $this->link('workspace.organizations', 'Organizations', 'fa-building', 'workspace.organizations*', $s) : null,
                $this->link('workspace.startups.index', 'All my startups', 'fa-layer-group', 'workspace.startups.index|workspace.startups.create'),
            ]);
            $groups[] = $this->group('grow', 'Grow', 'fa-seedling', [
                $on('vgi') ? $this->link('workspace.growth', 'Growth', 'fa-arrow-trend-up', 'workspace.growth*', $s) : null,
                $on('intelligence') ? $this->link('workspace.intelligence', 'Guidance', 'fa-compass', 'workspace.intelligence*', $s) : null,
                $on('venture_builder') ? $this->link('workspace.builder', 'Venture Builder', 'fa-diagram-project', 'workspace.builder*', $s) : null,
                $on('ai_assistant') && Assistant::unavailableReason() === null
                    ? $this->link('workspace.assistant', 'AI assistant', 'fa-wand-magic-sparkles', 'workspace.assistant*', $s) : null,
                $on('ims_integration') ? $this->link('workspace.startups.programs', 'Applications & programs', 'fa-graduation-cap', 'workspace.startups.programs', $s) : null,
            ]);
        }

        if ($on('opportunities')) {
            $groups[] = $this->group('opportunities', 'Opportunities', 'fa-bullhorn', [
                $s ? $this->link('workspace.opportunities', 'Matched for '.$s->display_name, 'fa-bullseye', 'workspace.opportunities*', $s) : null,
                $this->link('opportunities.index', 'Browse opportunities', 'fa-magnifying-glass', 'opportunities.index|opportunities.show'),
            ]);
        }
        if ($on('funding') && $s) {
            $groups[] = $this->group('funding', 'Funding', 'fa-sack-dollar', [
                $this->link('workspace.funding', 'Fundraising', 'fa-hand-holding-dollar', 'workspace.funding*', $s),
            ]);
        }
        if ($on('experts') && $s) {
            $groups[] = $this->group('experts', 'Experts', 'fa-user-tie', [
                $this->link('workspace.experts', 'Find an expert', 'fa-user-graduate', 'workspace.experts*', $s),
            ]);
        }
        if ($on('talent')) {
            $groups[] = $this->group('talent', 'Talent', 'fa-briefcase', [
                $s ? $this->link('workspace.talent', 'Hiring', 'fa-user-plus', 'workspace.talent*', $s) : null,
                $this->link('jobs.index', 'Jobs board', 'fa-briefcase', 'jobs.*'),
                $this->isCandidate ? null : $this->link('talent.dashboard', 'Looking for a job?', 'fa-id-badge', 'talent.*'),
            ]);
        }
        if ($on('marketplace')) {
            $groups[] = $this->group('marketplace', 'Marketplace', 'fa-store', [
                $s ? $this->link('workspace.marketplace', 'My listings', 'fa-tags', 'workspace.marketplace*', $s) : null,
                $this->link('marketplace.inbox', 'Messages', 'fa-envelope', 'marketplace.inbox|marketplace.inquiries.*'),
                $this->link('marketplace.index', 'Browse marketplace', 'fa-store', 'marketplace.index|marketplace.show'),
                $this->link('directory.index', 'Startup directory', 'fa-building-flag', 'directory.*'),
            ]);
        }
        if ($on('knowledge')) {
            $groups[] = $this->group('knowledge', 'Knowledge', 'fa-book-open', [
                $this->link('knowledge.index', 'Knowledge Hub', 'fa-book-open', 'knowledge.index|knowledge.show'),
                $this->link('knowledge.mine', 'Saved and recent', 'fa-bookmark', 'knowledge.mine'),
            ]);
        }
        if ($on('challenges')) {
            $groups[] = $this->group('challenges', 'Challenges', 'fa-lightbulb', [
                $this->link('challenges.index', 'Challenge Exchange', 'fa-lightbulb', 'challenges.index|challenges.show|challenges.proposals.*'),
                $this->link('challenges.mine', 'My challenges', 'fa-list-check', 'challenges.mine|challenges.create|challenges.edit'),
            ]);
        }

        // Role sections: only for people who have that role.
        if ($this->investor) {
            $groups[] = $this->group('investor', 'Investor', 'fa-building-columns', [
                // The dashboard sends unverified investors back to their profile, so list it only once verified.
                $this->investor->isVerified() ? $this->link('investor.dashboard', 'Deal flow', 'fa-chart-line', 'investor.dashboard|investor.startup') : null,
                $this->link('investor.apply', 'Investor profile', 'fa-id-card-clip', 'investor.apply'),
            ]);
        }
        if ($this->expert) {
            $groups[] = $this->group('expert', 'Expert', 'fa-user-tie', [
                $this->expert->isVerified() ? $this->link('experts.dashboard', 'Engagements', 'fa-handshake', 'experts.dashboard|engagements.*') : null,
                $this->link('experts.profile', 'Expert profile', 'fa-id-card-clip', 'experts.profile'),
            ]);
        }
        if ($this->isCandidate) {
            $groups[] = $this->group('candidate', 'Job seeker', 'fa-id-badge', [
                $this->link('talent.dashboard', 'My applications', 'fa-file-lines', 'talent.dashboard'),
                $this->link('talent.profile', 'Candidate profile', 'fa-id-card-clip', 'talent.profile'),
            ]);
        }
        if ($this->organizations->isNotEmpty()) {
            $orgLinks = $this->organizations->take(5)->map(fn (OrganizationMembership $m) => [
                'label' => $m->organization->name,
                'url' => route('organizations.show', $m->organization),
                'icon' => 'fa-building',
                'active' => request()->routeIs('organizations.show', 'organizations.members', 'organizations.startup')
                    && (string) optional(request()->route('organization'))->getKey() === (string) $m->organization->getKey(),
                'badge' => null,
            ])->all();
            $groups[] = $this->group('organization', 'Organization', 'fa-building', [
                ...$orgLinks,
                $this->link('organizations.index', 'All my organizations', 'fa-layer-group', 'organizations.index'),
            ]);
        }

        $groups[] = $this->group('account', 'Account', 'fa-user-gear', [
            $this->link('account.edit', 'My profile', 'fa-id-card', 'account.*'),
            $this->link('mfa.setup', 'Sign-in security', 'fa-lock', 'mfa.*'),
            $this->link('notifications.preferences', 'Notification settings', 'fa-sliders', 'notifications.preferences*'),
        ]);

        return array_values(array_filter($groups, fn ($group) => $group['links'] !== []));
    }

    /** Whether any link in the group is the current page (the group then starts open). */
    public static function isActive(array $group): bool
    {
        return collect($group['links'])->contains('active', true);
    }

    private function group(string $key, string $label, string $icon, array $links): array
    {
        return ['key' => $key, 'label' => $label, 'icon' => $icon, 'links' => array_values(array_filter($links))];
    }

    private function link(string $route, string $label, string $icon, string $active, mixed $parameter = null, ?int $badge = null): ?array
    {
        if (! Route::has($route)) {
            return null;
        }

        return [
            'label' => $label,
            'url' => $parameter === null ? route($route) : route($route, $parameter),
            'icon' => $icon,
            'active' => request()->routeIs(...explode('|', $active)),
            'badge' => $badge,
        ];
    }
}
