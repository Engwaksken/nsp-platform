<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Communication\EmailDelivery;
use App\Models\ContactMessage;
use App\Models\Ecosystem\Opportunity;
use App\Models\Experts\ExpertProfile;
use App\Models\Funding\InvestorProfile;
use App\Models\Marketplace\Report;
use App\Models\Page;
use App\Models\Post;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Support\Features;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        return view('admin.dashboard', [
            'counts' => [
                'Published pages' => Page::query()->published()->count(),
                'Draft pages' => Page::query()->where('status', 'draft')->count(),
                'Published news' => Post::query()->published()->count(),
                'New messages' => $user->can('manage-contact-messages') ? ContactMessage::query()->where('status', 'new')->count() : null,
                'Active users' => $user->can('manage-users') ? User::query()->where('is_active', true)->count() : null,
                'Active startups' => $user->can('manage-startups') ? Startup::query()->active()->count() : null,
                'Opportunities to review' => $user->can('manage-opportunities') ? Opportunity::query()->where('status', 'pending_review')->count() : null,
                'Investors to verify' => $user->can('verify-investors') ? InvestorProfile::query()->where('status', 'pending')->count() : null,
                'Experts to verify' => $user->can('manage-experts') ? ExpertProfile::query()->where('status', 'pending')->count() : null,
                'Open marketplace reports' => $user->can('moderate-marketplace') ? Report::query()->where('status', 'open')->count() : null,
                'Failed emails (7 days)' => $user->can('manage-communications') ? EmailDelivery::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count() : null,
            ],
            'features' => collect(Features::definitions())->map(fn ($definition, $key) => [
                'label' => $definition['label'],
                'enabled' => Features::enabled($key),
            ]),
            'recentActivity' => $user->can('view-audit-log')
                ? AuditLog::query()->with('actor')->latest('id')->limit(8)->get()
                : collect(),
        ]);
    }
}
