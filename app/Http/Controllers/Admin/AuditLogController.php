<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginAudit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'logins' ? 'logins' : 'changes';

        $logs = $tab === 'logins'
            ? LoginAudit::query()->latest('id')->paginate(50)->withQueryString()
            : AuditLog::query()->with('actor')
                ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', $request->query('action').'%'))
                ->latest('id')->paginate(50)->withQueryString();

        return view('admin.audit', compact('logs', 'tab'));
    }
}
