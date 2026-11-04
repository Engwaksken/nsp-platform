<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registry\OutboxEvent;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupAlias;
use App\Models\Registry\StartupMerge;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Staff view of the registry. Sensitive profile groups are only shown when
 * the startup chose "our team and Hive staff", and every staff view is logged.
 */
class StartupController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['active', 'archived', 'merged'], true) ? $request->query('status') : 'active';
        $startups = Startup::query()->with('profile')->withCount(['activeMemberships'])
            ->where('status', $status)
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->query('q').'%';
                $query->where(fn ($q) => $q->where('display_name', 'like', $term)->orWhere('legal_name', 'like', $term)->orWhere('registration_number', 'like', $term));
            })
            ->orderBy('display_name')->paginate(30)->withQueryString();

        return view('admin.startups.index', compact('startups', 'status'));
    }

    public function show(Startup $startup): View
    {
        Audit::log('registry.staff_viewed_startup', $startup);
        $startup->load('profile', 'aliases', 'memberships.user', 'consents');

        return view('admin.startups.show', [
            'startup' => $startup,
            'merges' => StartupMerge::query()->with('source', 'target', 'merger')
                ->where('source_uuid', $startup->uuid)->orWhere('target_uuid', $startup->uuid)->latest('merged_at')->get(),
            'events' => OutboxEvent::query()->where('subject_uuid', $startup->uuid)->latest('occurred_at')->limit(20)->get(),
        ]);
    }

    public function updateStatus(Request $request, Startup $startup): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'archived'])]]);
        abort_if($startup->status === 'merged', 422, 'Undo the merge instead.');
        $startup->forceFill(['status' => $data['status'], 'archived_at' => $data['status'] === 'archived' ? now() : null])->save();
        OutboxEvent::record('registry.startup.updated.v1', $startup->uuid, ['changed_fields' => ['status'], 'status' => $data['status']]);
        Audit::log('registry.startup_status_changed', $startup, $data);

        return back()->with('status', $data['status'] === 'archived' ? 'Startup archived.' : 'Startup restored.');
    }

    public function addAlias(Request $request, Startup $startup): RedirectResponse
    {
        $data = $request->validate([
            'source_system' => ['required', Rule::in(array_keys(StartupAlias::SOURCES))],
            'external_id' => ['required', 'string', 'max:100'],
        ]);
        $existing = StartupAlias::query()->where('source_system', $data['source_system'])->where('external_id', $data['external_id'])->first();
        if ($existing) {
            return back()->withErrors(['external_id' => 'That ID is already linked to '.($existing->startup_uuid === $startup->uuid ? 'this startup.' : 'another startup. Review it before linking.')]);
        }
        StartupAlias::query()->create($data + ['startup_uuid' => $startup->uuid, 'created_by' => $request->user()->id]);
        Audit::log('registry.alias_added', $startup, $data);

        return back()->with('status', 'Link added.');
    }
}
