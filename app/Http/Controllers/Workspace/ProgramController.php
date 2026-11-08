<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Integration\ProgramRecord;
use App\Models\Registry\Startup;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "My Applications" and "My Programs": summaries from IMS, shown only to the startup's team. */
class ProgramController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, Startup $startup): View
    {
        $this->membershipOr404($request, $startup);
        $records = ProgramRecord::query()->where('startup_uuid', $startup->uuid)
            ->orderByDesc('enrolled')->orderByDesc('submitted_at')->get();

        return view('workspace.startups.programs', [
            'startup' => $startup,
            'programs' => $records->where('enrolled', true),
            'applications' => $records,
            'imsUrl' => config('nsp.ims_url'),
        ]);
    }
}
