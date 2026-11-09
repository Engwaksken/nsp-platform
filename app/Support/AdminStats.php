<?php

namespace App\Support;

use App\Models\Challenges\Challenge;
use App\Models\ContactMessage;
use App\Models\Ecosystem\Opportunity;
use App\Models\Experts\ExpertProfile;
use App\Models\Funding\InvestorProfile;
use App\Models\Knowledge\KnowledgeResource;
use App\Models\Page;
use App\Models\Post;
use App\Models\Registry\DataSharingConsent;
use App\Models\Registry\Startup;
use App\Models\Talent\JobApplication;
use App\Models\Talent\Vacancy;
use App\Models\User;

/**
 * Status counts for the statistics cards at the top of admin list pages.
 * Each method returns rows for partials/stats.blade.php. Counts are live (not cached)
 * so they match the list underneath after a change.
 */
class AdminStats
{
    /** Count rows grouped by status in one query. */
    private static function byStatus(string $model, string $column = 'status'): array
    {
        return $model::query()->toBase()->selectRaw($column.' as s, count(*) as n')->groupBy($column)->pluck('n', 's')->map(fn ($n) => (int) $n)->all();
    }

    public static function startups(): array
    {
        $counts = self::byStatus(Startup::class);

        return [
            ['icon' => 'fa-rocket', 'tone' => 'orange', 'label' => 'Active startups', 'value' => $counts['active'] ?? 0],
            ['icon' => 'fa-calendar-plus', 'tone' => 'green', 'label' => 'Registered in 30 days', 'value' => Startup::query()->where('created_at', '>=', now()->subDays(30))->count()],
            ['icon' => 'fa-address-book', 'tone' => 'blue', 'label' => 'Listed in the directory', 'value' => DataSharingConsent::query()->where('purpose', 'nsp_directory')->whereNull('revoked_at')->distinct()->count('startup_uuid')],
            ['icon' => 'fa-box-archive', 'tone' => 'grey', 'label' => 'Not active', 'value' => array_sum($counts) - ($counts['active'] ?? 0)],
        ];
    }

    public static function opportunities(): array
    {
        $counts = self::byStatus(Opportunity::class);

        return [
            ['icon' => 'fa-bullhorn', 'tone' => 'green', 'label' => 'Open now', 'value' => Opportunity::query()->open()->count()],
            ['icon' => 'fa-inbox', 'tone' => 'amber', 'label' => 'Waiting for review', 'value' => $counts['pending_review'] ?? 0],
            ['icon' => 'fa-file-pen', 'tone' => 'grey', 'label' => 'Drafts', 'value' => $counts['draft'] ?? 0],
            ['icon' => 'fa-box-archive', 'tone' => 'purple', 'label' => 'Archived', 'value' => $counts['archived'] ?? 0],
        ];
    }

    public static function vacancies(): array
    {
        $counts = self::byStatus(Vacancy::class);

        return [
            ['icon' => 'fa-briefcase', 'tone' => 'green', 'label' => 'Open', 'value' => $counts['open'] ?? 0],
            ['icon' => 'fa-file-pen', 'tone' => 'grey', 'label' => 'Drafts', 'value' => $counts['draft'] ?? 0],
            ['icon' => 'fa-file-lines', 'tone' => 'blue', 'label' => 'Applications (30 days)', 'value' => JobApplication::query()->where('created_at', '>=', now()->subDays(30))->count()],
            ['icon' => 'fa-ban', 'tone' => 'red', 'label' => 'Taken down', 'value' => $counts['removed'] ?? 0],
        ];
    }

    public static function users(): array
    {
        return [
            ['icon' => 'fa-users', 'tone' => 'blue', 'label' => 'Active users', 'value' => User::query()->where('is_active', true)->count()],
            ['icon' => 'fa-user-plus', 'tone' => 'green', 'label' => 'Joined in 30 days', 'value' => User::query()->where('created_at', '>=', now()->subDays(30))->count()],
            ['icon' => 'fa-shield-halved', 'tone' => 'purple', 'label' => 'Two-step sign-in on', 'value' => User::query()->whereNotNull('mfa_enabled_at')->count()],
            ['icon' => 'fa-user-slash', 'tone' => 'grey', 'label' => 'Deactivated', 'value' => User::query()->where('is_active', false)->count()],
        ];
    }

    public static function messages(): array
    {
        $counts = self::byStatus(ContactMessage::class);

        return [
            ['icon' => 'fa-envelope', 'tone' => 'red', 'label' => 'New', 'value' => $counts['new'] ?? 0],
            ['icon' => 'fa-spinner', 'tone' => 'amber', 'label' => 'In progress', 'value' => $counts['in_progress'] ?? 0],
            ['icon' => 'fa-circle-check', 'tone' => 'green', 'label' => 'Closed', 'value' => $counts['closed'] ?? 0],
            ['icon' => 'fa-ban', 'tone' => 'grey', 'label' => 'Spam', 'value' => $counts['spam'] ?? 0],
        ];
    }

    /** Investors and experts share the same review statuses. */
    private static function profiles(string $model, string $icon): array
    {
        $counts = self::byStatus($model);

        return [
            ['icon' => 'fa-inbox', 'tone' => 'amber', 'label' => 'Waiting for review', 'value' => $counts['pending'] ?? 0],
            ['icon' => $icon, 'tone' => 'green', 'label' => 'Verified', 'value' => $counts['verified'] ?? 0],
            ['icon' => 'fa-circle-xmark', 'tone' => 'grey', 'label' => 'Not verified', 'value' => $counts['rejected'] ?? 0],
            ['icon' => 'fa-user-lock', 'tone' => 'red', 'label' => 'Suspended', 'value' => $counts['suspended'] ?? 0],
        ];
    }

    public static function investors(): array
    {
        return self::profiles(InvestorProfile::class, 'fa-building-columns');
    }

    public static function experts(): array
    {
        return self::profiles(ExpertProfile::class, 'fa-user-tie');
    }

    public static function challenges(): array
    {
        $counts = self::byStatus(Challenge::class);

        return [
            ['icon' => 'fa-lightbulb', 'tone' => 'green', 'label' => 'Published', 'value' => $counts['published'] ?? 0],
            ['icon' => 'fa-inbox', 'tone' => 'amber', 'label' => 'To review', 'value' => ($counts['submitted'] ?? 0) + ($counts['under_review'] ?? 0)],
            ['icon' => 'fa-rotate', 'tone' => 'blue', 'label' => 'Changes requested', 'value' => $counts['changes_requested'] ?? 0],
            ['icon' => 'fa-file-pen', 'tone' => 'grey', 'label' => 'Drafts', 'value' => $counts['draft'] ?? 0],
        ];
    }

    public static function knowledge(): array
    {
        $counts = self::byStatus(KnowledgeResource::class);

        return [
            ['icon' => 'fa-book-open', 'tone' => 'green', 'label' => 'Published', 'value' => $counts['published'] ?? 0],
            ['icon' => 'fa-inbox', 'tone' => 'amber', 'label' => 'In review', 'value' => $counts['in_review'] ?? 0],
            ['icon' => 'fa-file-pen', 'tone' => 'grey', 'label' => 'Drafts', 'value' => $counts['draft'] ?? 0],
            ['icon' => 'fa-box-archive', 'tone' => 'purple', 'label' => 'Archived', 'value' => $counts['archived'] ?? 0],
        ];
    }

    public static function pages(): array
    {
        $counts = self::byStatus(Page::class);

        return [
            ['icon' => 'fa-file-lines', 'tone' => 'green', 'label' => 'Published', 'value' => Page::query()->published()->count()],
            ['icon' => 'fa-file-pen', 'tone' => 'grey', 'label' => 'Drafts', 'value' => $counts['draft'] ?? 0],
            ['icon' => 'fa-calendar-check', 'tone' => 'blue', 'label' => 'Scheduled', 'value' => Page::query()->where('status', 'published')->where('published_at', '>', now())->count()],
            ['icon' => 'fa-box-archive', 'tone' => 'purple', 'label' => 'Archived', 'value' => $counts['archived'] ?? 0],
        ];
    }

    public static function posts(): array
    {
        $counts = self::byStatus(Post::class);

        return [
            ['icon' => 'fa-newspaper', 'tone' => 'green', 'label' => 'Published', 'value' => Post::query()->published()->count()],
            ['icon' => 'fa-file-pen', 'tone' => 'grey', 'label' => 'Drafts', 'value' => $counts['draft'] ?? 0],
            ['icon' => 'fa-calendar-check', 'tone' => 'blue', 'label' => 'Scheduled', 'value' => Post::query()->where('status', 'published')->where('published_at', '>', now())->count()],
            ['icon' => 'fa-calendar-plus', 'tone' => 'orange', 'label' => 'Published in 30 days', 'value' => Post::query()->published()->where('published_at', '>=', now()->subDays(30))->count()],
        ];
    }
}
