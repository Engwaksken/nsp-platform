<?php

namespace App\Http\Controllers;

use App\Contracts\StartupRegistry;
use App\Exceptions\RegistryUnavailable;
use App\Support\Features;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Founder workspace. In Phase 1 it shows the unified navigation and which
 * services are not available yet; it never invents startup data.
 */
class WorkspaceController extends Controller
{
    public function index(Request $request, StartupRegistry $registry): View
    {
        $user = $request->user();
        $sections = [
            ['key' => 'registry', 'title' => 'My Startup Profile', 'text' => 'Your startup\'s shared Hive profile, team and privacy settings.'],
            ['key' => 'ims_integration', 'title' => 'My Applications', 'text' => 'Applications you made to Hive programs (from IMS).'],
            ['key' => 'ims_integration', 'title' => 'My Programs', 'text' => 'Programs and cohorts you take part in (from IMS).'],
            ['key' => 'vgi', 'title' => 'Growth Dashboard', 'text' => 'VGI assessment, growth stage and growth plan.'],
            ['key' => 'funding', 'title' => 'Funding & Investors', 'text' => 'Funding needs, investor readiness and data room.'],
            ['key' => 'experts', 'title' => 'Experts', 'text' => 'Mentors, consultants and booked sessions.'],
            ['key' => 'talent', 'title' => 'Talent', 'text' => 'Vacancies, internships and candidates.'],
            ['key' => 'opportunities', 'title' => 'Opportunities', 'text' => 'Grants, events, competitions and the marketplace.'],
        ];
        foreach ($sections as &$section) {
            $section['available'] = Features::enabled($section['key']);
        }
        unset($section);

        $startups = null;
        $registryMessage = null;
        if (Features::enabled('registry')) {
            try {
                $startups = $registry->startupsForUser($user);
            } catch (RegistryUnavailable $exception) {
                $registryMessage = 'Your startup profiles cannot be loaded right now. Please try again later.';
            }
        }

        return view('workspace.index', compact('user', 'sections', 'startups', 'registryMessage'));
    }
}
