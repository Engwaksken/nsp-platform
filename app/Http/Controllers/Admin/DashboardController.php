<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Post;
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
