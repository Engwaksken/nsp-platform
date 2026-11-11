<?php

namespace App\Support;

use App\Models\Challenges\Challenge;
use App\Models\Challenges\ChallengeSolutionSubmission;
use App\Models\Ecosystem\Opportunity;
use App\Models\Knowledge\KnowledgeCategory;
use App\Models\Knowledge\KnowledgeResource;
use App\Models\Marketplace\Listing;
use App\Models\Registry\DataSharingConsent;
use App\Models\Registry\Startup;
use App\Models\Talent\Vacancy;
use Illuminate\Support\Facades\Cache;

/**
 * Summary counts for the statistics cards on the public listing pages.
 * Only counts of what the public can already see; cached for ten minutes.
 * Each method returns rows for partials/stats-summary.blade.php: the first row is the headline total;
 * a row with 'query' links to the listing filtered that way.
 */
class PublicStats
{
    private static function remember(string $key, callable $build): array
    {
        return Cache::remember('nsp.public_stats.v2.'.$key, now()->addMinutes(10), $build);
    }

    public static function opportunities(): array
    {
        return self::remember('opportunities', fn () => [
            ['icon' => 'fa-bullhorn', 'tone' => 'orange', 'label' => 'Open opportunities', 'value' => Opportunity::query()->open()->count()],
            ['icon' => 'fa-hourglass-half', 'tone' => 'red', 'label' => 'Closing in 14 days', 'query' => ['closing' => 'soon'], 'value' => Opportunity::query()->open()->whereBetween('deadline_at', [now(), now()->addDays(14)])->count()],
            ['icon' => 'fa-hand-holding-dollar', 'tone' => 'green', 'label' => 'Grants', 'query' => ['type' => 'grant'], 'value' => Opportunity::query()->open()->where('type', 'grant')->count()],
            ['icon' => 'fa-calendar-days', 'tone' => 'blue', 'label' => 'Events and training', 'query' => ['type' => 'event'], 'value' => Opportunity::query()->open()->where('type', 'event')->count()],
        ]);
    }

    public static function directory(): array
    {
        return self::remember('directory', function () {
            $listed = Startup::query()->active()->whereIn('uuid', DataSharingConsent::query()->where('purpose', 'nsp_directory')->whereNull('revoked_at')->select('startup_uuid'));

            return [
                ['icon' => 'fa-rocket', 'tone' => 'orange', 'label' => 'Listed startups', 'value' => (clone $listed)->count()],
                ['icon' => 'fa-industry', 'tone' => 'green', 'label' => 'Sectors', 'value' => (clone $listed)->join('startup_profiles', 'startup_profiles.startup_uuid', '=', 'startups.uuid')->whereNotNull('startup_profiles.sector')->distinct()->count('startup_profiles.sector')],
                ['icon' => 'fa-earth-africa', 'tone' => 'blue', 'label' => 'Countries', 'value' => (clone $listed)->distinct()->count('country')],
                ['icon' => 'fa-star', 'tone' => 'purple', 'label' => 'Joined this year', 'value' => (clone $listed)->where('startups.created_at', '>=', now()->startOfYear())->count()],
            ];
        });
    }

    public static function marketplace(): array
    {
        return self::remember('marketplace', fn () => [
            ['icon' => 'fa-store', 'tone' => 'orange', 'label' => 'Live listings', 'value' => Listing::query()->live()->count()],
            ['icon' => 'fa-box-open', 'tone' => 'green', 'label' => 'Offers', 'query' => ['kind' => 'offer'], 'value' => Listing::query()->live()->where('kind', 'offer')->count()],
            ['icon' => 'fa-cart-shopping', 'tone' => 'blue', 'label' => 'Requests to buy', 'query' => ['kind' => 'request'], 'value' => Listing::query()->live()->where('kind', 'request')->count()],
            ['icon' => 'fa-handshake', 'tone' => 'purple', 'label' => 'Partnerships', 'query' => ['kind' => 'partnership'], 'value' => Listing::query()->live()->where('kind', 'partnership')->count()],
        ]);
    }

    public static function jobs(): array
    {
        return self::remember('jobs', fn () => [
            ['icon' => 'fa-briefcase', 'tone' => 'orange', 'label' => 'Open roles', 'value' => Vacancy::query()->acceptingApplications()->count()],
            ['icon' => 'fa-building', 'tone' => 'blue', 'label' => 'Startups hiring', 'value' => Vacancy::query()->acceptingApplications()->distinct()->count('startup_uuid')],
            ['icon' => 'fa-user-graduate', 'tone' => 'green', 'label' => 'Internships', 'query' => ['type' => 'internship'], 'value' => Vacancy::query()->acceptingApplications()->where('type', 'internship')->count()],
            ['icon' => 'fa-clock', 'tone' => 'purple', 'label' => 'Full-time jobs', 'query' => ['type' => 'full_time'], 'value' => Vacancy::query()->acceptingApplications()->where('type', 'full_time')->count()],
        ]);
    }

    public static function knowledge(): array
    {
        return self::remember('knowledge', fn () => [
            ['icon' => 'fa-book-open', 'tone' => 'orange', 'label' => 'Resources', 'value' => KnowledgeResource::query()->live()->visibleTo(null)->count()],
            ['icon' => 'fa-folder-tree', 'tone' => 'blue', 'label' => 'Categories', 'value' => KnowledgeCategory::query()->count()],
            ['icon' => 'fa-file-circle-plus', 'tone' => 'green', 'label' => 'Added in 30 days', 'value' => KnowledgeResource::query()->live()->visibleTo(null)->where('published_at', '>=', now()->subDays(30))->count()],
        ]);
    }

    public static function challenges(): array
    {
        return self::remember('challenges', fn () => [
            ['icon' => 'fa-lightbulb', 'tone' => 'orange', 'label' => 'Published challenges', 'value' => Challenge::query()->where('status', 'published')->count()],
            ['icon' => 'fa-file-signature', 'tone' => 'blue', 'label' => 'Proposals sent', 'value' => ChallengeSolutionSubmission::query()->count()],
            ['icon' => 'fa-calendar-plus', 'tone' => 'green', 'label' => 'New in 30 days', 'value' => Challenge::query()->where('status', 'published')->where('updated_at', '>=', now()->subDays(30))->count()],
        ]);
    }
}
