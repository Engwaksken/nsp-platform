<?php

namespace App\Http\Controllers;

use App\Contracts\StartupRegistry;
use App\Ecosystem\Eligibility;
use App\Exceptions\RegistryUnavailable;
use App\Models\Registry\StartupMembership;
use App\Models\Talent\JobApplication;
use App\Support\Features;
use App\Support\MemberNavigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Member dashboard: what the member has (startups, roles, unread notifications),
 * what to do next, and which services are not available yet.
 * It never invents startup data: every figure is counted from the member's own records.
 */
class WorkspaceController extends Controller
{
    public function index(Request $request, StartupRegistry $registry): View
    {
        $user = $request->user();
        $nav = MemberNavigation::for($user);
        $active = $nav->membership;
        $startup = $active?->startup;
        $on = fn (string $flag) => Features::enabled($flag);

        $startups = null;
        $registryMessage = null;
        if ($on('registry')) {
            try {
                $startups = $registry->startupsForUser($user);
            } catch (RegistryUnavailable $exception) {
                $registryMessage = 'Your startup profiles cannot be loaded right now. Please try again later.';
            }
        }

        // Key figures: only counts of the member's own records.
        $stats = [];
        if (is_array($startups)) {
            $stats[] = ['icon' => 'fa-rocket', 'tone' => 'purple', 'label' => 'My startups', 'value' => count($startups), 'url' => route('workspace.startups.index')];
        }
        $stats[] = ['icon' => 'fa-bell', 'tone' => $nav->unread ? 'red' : 'grey', 'label' => 'Unread notifications', 'value' => $nav->unread, 'url' => route('notifications.index')];
        $completion = null;
        $stage = null;
        if ($startup) {
            $startup->loadMissing('profile');
            $completion = (int) ($startup->profile?->profile_completion ?? 0);
            $stats[] = ['icon' => 'fa-id-card', 'tone' => 'blue', 'label' => 'Profile complete', 'value' => $completion.'%', 'note' => $startup->display_name,
                'url' => route($active->canEdit() ? 'workspace.startups.edit' : 'workspace.startups.show', $startup)];
            $stats[] = ['icon' => 'fa-users', 'tone' => 'purple', 'label' => 'Team members', 'note' => $startup->display_name,
                'value' => StartupMembership::query()->where('startup_uuid', $startup->uuid)->where('status', 'active')->count(),
                'url' => route('workspace.startups.team', $startup)];
            if ($on('vgi')) {
                $stage = Eligibility::stage($startup);
                $stats[] = ['icon' => 'fa-seedling', 'tone' => 'green', 'label' => 'Growth stage', 'value' => $stage ?? 'Not assessed', 'url' => route('workspace.growth', $startup)];
            }
        }
        if ($nav->isCandidate) {
            $stats[] = ['icon' => 'fa-file-lines', 'tone' => 'amber', 'label' => 'Job applications',
                'value' => JobApplication::query()->where('applicant_id', $user->id)->count(), 'url' => route('talent.dashboard')];
        }

        // Next steps: [text, button label, url].
        $next = [];
        if ($startup && $active->canEdit() && $completion < 80) {
            $next[] = ['The '.$startup->display_name.' profile is '.$completion.'% complete. A fuller profile helps matching and support.', 'Complete the profile', route('workspace.startups.edit', $startup)];
        }
        if ($startup && $on('vgi') && $stage === null) {
            $next[] = ['Take the growth assessment to see your stage and get tailored guidance.', 'Start the assessment', route('workspace.growth', $startup)];
        }
        if ($nav->unread) {
            $next[] = [trans_choice('You have :count unread notification.|You have :count unread notifications.', $nav->unread), 'Read them', route('notifications.index', ['show' => 'unread'])];
        }
        if (! $user->hasMfa()) {
            $next[] = ['Protect your account with two-factor sign-in (a code from an app on your phone).', 'Turn it on', route('mfa.setup')];
        }
        if ($nav->investor && ! $nav->investor->isVerified()) {
            $next[] = [self::profileStep('investor', $nav->investor->status), 'View profile', route('investor.apply')];
        }
        if ($nav->expert && ! $nav->expert->isVerified()) {
            $next[] = [self::profileStep('expert', $nav->expert->status), 'View profile', route('experts.profile')];
        }

        // Quick actions: each only when its feature is on and the member can open it.
        $actions = array_values(array_filter([
            $on('registry') && ! $startup ? ['fa-circle-plus', 'Register a startup', 'Create your startup’s shared profile.', route('workspace.startups.create'), 'purple'] : null,
            $on('opportunities') ? ['fa-bullhorn', 'Find opportunities', $startup ? 'Grants and programs matched to '.$startup->display_name.'.' : 'Grants, accelerators, competitions and events.',
                $startup ? route('workspace.opportunities', $startup) : route('opportunities.index'), 'amber'] : null,
            $on('experts') && $startup ? ['fa-user-tie', 'Find an expert', 'Request a mentor or consultant.', route('workspace.experts', $startup), 'blue'] : null,
            $on('funding') && $startup ? ['fa-sack-dollar', 'Raise funding', 'Funding request, data room and investors.', route('workspace.funding', $startup), 'green'] : null,
            $on('talent') && $startup ? ['fa-user-plus', 'Hire talent', 'Post a vacancy and track applicants.', route('workspace.talent', $startup), 'purple'] : null,
            $on('talent') && ! $startup ? ['fa-briefcase', 'Browse jobs', 'Roles at startups in the ecosystem.', route('jobs.index'), 'purple'] : null,
            $on('knowledge') ? ['fa-book-open', 'Knowledge Hub', 'Research, templates and toolkits.', route('knowledge.index'), 'blue'] : null,
            $on('marketplace') ? ['fa-envelope', 'Marketplace messages', 'Conversations with sellers and partners.', route('marketplace.inbox'), 'red'] : null,
            $on('challenges') ? ['fa-lightbulb', 'Challenge Exchange', 'Innovation and market challenges to solve.', route('challenges.index'), 'amber'] : null,
        ]));
        $actions = array_slice($actions, 0, 6);

        // Roles the member has, and ones they can take on.
        $roles = [];
        $join = [];
        if ($on('funding') && $nav->investor) {
            $verified = $nav->investor->isVerified();
            $roles[] = ['fa-building-columns', 'Investor', $verified ? 'Verified' : self::profileLabel($nav->investor->status), route($verified ? 'investor.dashboard' : 'investor.apply')];
        } elseif ($on('funding')) {
            $join[] = ['fa-building-columns', 'Invest in startups', 'Apply to be a verified investor.', route('investor.apply')];
        }
        if ($on('experts') && $nav->expert) {
            $verified = $nav->expert->isVerified();
            $roles[] = ['fa-user-tie', 'Expert or mentor', $verified ? 'Verified' : self::profileLabel($nav->expert->status), route($verified ? 'experts.dashboard' : 'experts.profile')];
        } elseif ($on('experts')) {
            $join[] = ['fa-user-tie', 'Mentor founders', 'Offer your expertise as a mentor or consultant.', route('experts.profile')];
        }
        if ($on('talent') && $nav->isCandidate) {
            $roles[] = ['fa-id-badge', 'Job seeker', 'Applications and candidate profile', route('talent.dashboard')];
        } elseif ($on('talent')) {
            $join[] = ['fa-id-badge', 'Find a job', 'Create a candidate profile and apply to startups.', route('talent.profile')];
        }
        foreach ($nav->organizations as $membership) {
            $roles[] = ['fa-building', $membership->organization->name, 'Organization member', route('organizations.show', $membership->organization)];
        }

        // Platform services; ones switched off are shown as not available yet.
        $sections = [
            ['key' => 'registry', 'icon' => 'fa-id-card', 'tone' => 'purple', 'title' => 'My Startup Profile', 'text' => 'Your startup\'s shared Hive profile, team and privacy settings.'],
            ['key' => 'vgi', 'icon' => 'fa-seedling', 'tone' => 'green', 'title' => 'Growth Dashboard', 'text' => 'VGI assessment, growth stage and growth plan.'],
            ['key' => 'opportunities', 'icon' => 'fa-bullhorn', 'tone' => 'amber', 'title' => 'Opportunities', 'text' => 'Grants, accelerators, competitions and events matched to your startup.'],
            ['key' => 'funding', 'icon' => 'fa-sack-dollar', 'tone' => 'green', 'title' => 'Funding & Investors', 'text' => 'Funding needs, investor readiness and data room.'],
            ['key' => 'experts', 'icon' => 'fa-user-tie', 'tone' => 'blue', 'title' => 'Experts', 'text' => 'Mentors, consultants and booked sessions.'],
            ['key' => 'talent', 'icon' => 'fa-briefcase', 'tone' => 'purple', 'title' => 'Talent', 'text' => 'Vacancies, internships and candidates.'],
            ['key' => 'marketplace', 'icon' => 'fa-store', 'tone' => 'red', 'title' => 'Marketplace', 'text' => 'Sell, buy and find partners; messages with buyers and suppliers.'],
            ['key' => 'knowledge', 'icon' => 'fa-book-open', 'tone' => 'blue', 'title' => 'Knowledge Hub', 'text' => 'Research, policy briefs, templates and toolkits.'],
            ['key' => 'challenges', 'icon' => 'fa-lightbulb', 'tone' => 'amber', 'title' => 'Challenge Exchange', 'text' => 'Innovation and market challenges with solution proposals.'],
            ['key' => 'ims_integration', 'icon' => 'fa-file-signature', 'tone' => 'grey', 'title' => 'My Applications', 'text' => 'Applications you made to Hive programs (from IMS).'],
            ['key' => 'ims_integration', 'icon' => 'fa-graduation-cap', 'tone' => 'grey', 'title' => 'My Programs', 'text' => 'Programs and cohorts you take part in (from IMS).'],
        ];
        foreach ($sections as &$section) {
            $section['available'] = $on($section['key']);
            $section['actions'] = $section['available'] ? $this->sectionActions($section['key'], $startup) : [];
        }
        unset($section);

        return view('workspace.index', compact('user', 'nav', 'sections', 'startups', 'registryMessage', 'active', 'stats', 'next', 'actions', 'roles', 'join'));
    }

    /**
     * Buttons for one service card: [label, url, primary?]. Startup pages need an active startup.
     *
     * @return array<int, array{0: string, 1: string, 2: bool}>
     */
    private function sectionActions(string $key, $startup): array
    {
        return array_values(array_filter(match ($key) {
            'registry' => [['Open my startups', route('workspace.startups.index'), true]],
            'vgi' => [$startup ? ['Open growth', route('workspace.growth', $startup), true] : null],
            'opportunities' => [['See opportunities', $startup ? route('workspace.opportunities', $startup) : route('opportunities.index'), true]],
            'funding' => [$startup ? ['Open funding', route('workspace.funding', $startup), true] : null, ['I am an investor', route('investor.dashboard'), false]],
            'experts' => [$startup ? ['Find an expert', route('workspace.experts', $startup), true] : null, ['I am an expert or mentor', route('experts.dashboard'), false]],
            'talent' => [$startup ? ['Hire', route('workspace.talent', $startup), true] : null, ['Find a job', route('talent.dashboard'), false]],
            'marketplace' => [$startup ? ['My listings', route('workspace.marketplace', $startup), true] : null, ['My messages', route('marketplace.inbox'), false]],
            'knowledge' => [['Browse resources', route('knowledge.index'), true], ['Saved', route('knowledge.mine'), false]],
            'challenges' => [['Browse challenges', route('challenges.index'), true]],
            'ims_integration' => [$startup ? ['Open programs', route('workspace.startups.programs', $startup), true] : null],
            default => [],
        }));
    }

    /** Short status for an investor or expert profile that is not (or no longer) verified. */
    private static function profileLabel(?string $status): string
    {
        return match ($status) {
            'rejected' => 'Not verified',
            'suspended' => 'Suspended',
            'verified' => 'Account inactive',
            default => 'Waiting for verification',
        };
    }

    private static function profileStep(string $kind, ?string $status): string
    {
        return match ($status) {
            'rejected' => "Hive did not verify your {$kind} profile. Open it to see what to change.",
            'suspended' => "Your {$kind} profile is suspended. Contact Hive if you think this is a mistake.",
            default => "Your {$kind} profile is waiting for Hive to verify it.",
        };
    }
}
