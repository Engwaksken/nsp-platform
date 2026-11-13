<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginAudit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Admin → Audit log. The tables show a short summary of each entry; the full record opens from the
 * row's View button (in a popup with JavaScript, as its own page without).
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'logins' ? 'logins' : 'changes';
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'who' => ['nullable', 'string', 'max:120'],
            'result' => ['nullable', 'in:success,failed'],
            'method' => ['nullable', 'string', 'max:10'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $from = filled($filters['from'] ?? null) ? Carbon::parse($filters['from'])->startOfDay() : null;
        $to = filled($filters['to'] ?? null) ? Carbon::parse($filters['to'])->endOfDay() : null;

        if ($tab === 'logins') {
            $query = LoginAudit::query()
                ->when($filters['result'] ?? null, fn ($q, $result) => $q->where('success', $result === 'success'))
                ->when($filters['method'] ?? null, fn ($q, $method) => $q->where('method', $method));
        } else {
            $query = AuditLog::query()->with('actor:id,name,email')
                ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', 'like', addcslashes($action, '%_\\').'%'))
                ->when($filters['who'] ?? null, function ($q, $who) {
                    $term = '%'.addcslashes($who, '%_\\').'%';
                    $q->whereIn('actor_id', User::query()->select('id')->where(fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
                });
        }
        $query->when($from, fn ($q) => $q->where('created_at', '>=', $from))->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $logs = $query->latest('id')->paginate(50)->withQueryString();

        return view('admin.audit', [
            'tab' => $tab,
            'logs' => $logs,
            'filters' => $filters,
            'filtered' => collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty(),
            'stats' => $this->stats($tab),
            'actionGroups' => $tab === 'changes' ? $this->actionGroups() : [],
            'methods' => $tab === 'logins' ? LoginAudit::query()->distinct()->orderBy('method')->pluck('method')->all() : [],
        ]);
    }

    public function show(AuditLog $log): View
    {
        $log->load('actor:id,name,email');

        return view('admin.audit-show', ['log' => $log, 'kind' => 'change']);
    }

    public function showLogin(LoginAudit $login): View
    {
        return view('admin.audit-show', [
            'log' => $login,
            'kind' => 'login',
            'user' => $login->user_id ? User::query()->find($login->user_id, ['id', 'name', 'email']) : null,
        ]);
    }

    /** Summary cards for the top of the page (partials.stats). */
    private function stats(string $tab): array
    {
        $today = now()->startOfDay();
        $week = now()->subDays(7);
        if ($tab === 'logins') {
            $failed = LoginAudit::query()->where('created_at', '>=', $today)->where('success', false)->count();

            return [
                ['icon' => 'fa-right-to-bracket', 'tone' => 'blue', 'label' => 'Sign-ins today', 'value' => LoginAudit::query()->where('created_at', '>=', $today)->where('success', true)->count()],
                ['icon' => 'fa-triangle-exclamation', 'tone' => $failed ? 'red' : 'grey', 'label' => 'Failed today', 'value' => $failed],
                ['icon' => 'fa-calendar-week', 'tone' => 'purple', 'label' => 'Attempts in 7 days', 'value' => LoginAudit::query()->where('created_at', '>=', $week)->count()],
            ];
        }

        return [
            ['icon' => 'fa-clock-rotate-left', 'tone' => 'blue', 'label' => 'Changes today', 'value' => AuditLog::query()->where('created_at', '>=', $today)->count()],
            ['icon' => 'fa-user-pen', 'tone' => 'green', 'label' => 'People active today', 'value' => AuditLog::query()->where('created_at', '>=', $today)->whereNotNull('actor_id')->distinct()->count('actor_id')],
            ['icon' => 'fa-calendar-week', 'tone' => 'purple', 'label' => 'Changes in 7 days', 'value' => AuditLog::query()->where('created_at', '>=', $week)->count()],
        ];
    }

    /** The first part of each action name ("page" in "page.updated"), for the filter list. */
    private function actionGroups(): array
    {
        return Cache::remember('admin.audit.action_groups', 600, fn () => AuditLog::query()->distinct()->pluck('action')
            ->map(fn ($action) => explode('.', (string) $action)[0])->unique()->sort()->values()->all());
    }
}
