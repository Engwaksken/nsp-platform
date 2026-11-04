<?php

namespace Tests\Feature\Performance;

use App\Models\Communication\UserNotification;
use App\Models\Ecosystem\Opportunity;
use App\Models\Experts\ExpertProfile;
use App\Models\Funding\FundingRequest;
use App\Models\Funding\InvestorProfile;
use App\Models\Marketplace\Listing;
use App\Models\Talent\Vacancy;
use App\Models\User;
use App\Registry\RegistryService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PERF-708 guard: list pages must not run one query per row (N+1). Each page is loaded
 * with a few rows and then with many; the number of queries must stay (almost) the same.
 */
class QueryCountTest extends TestCase
{
    private int $made = 0;

    private User $viewer;

    private function startups(int $count): void
    {
        $registry = app(RegistryService::class);
        for ($i = 0; $i < $count; $i++) {
            $n = ++$this->made;
            $owner = User::factory()->withRole('startup_user')->create();
            $startup = $registry->createStartup($owner, [
                'legal_name' => "Startup {$n} Ltd", 'display_name' => "Startup {$n}", 'registration_number' => "UG-P{$n}",
                'country' => 'UG', 'year_founded' => 2022, 'description' => 'We build things.', 'website' => "https://s{$n}.test",
            ], ['sector' => 'Agriculture & Agritech']);
            $registry->grantConsent($startup, $owner, 'nsp_directory');
            $registry->grantConsent($startup, $owner, 'investor_listing');
            FundingRequest::query()->create(['startup_uuid' => $startup->uuid, 'amount' => 50000, 'currency' => 'USD', 'instruments' => ['equity'], 'pitch' => 'p', 'status' => 'open', 'created_by' => $owner->id]);

            $listing = Listing::query()->create(['startup_uuid' => $startup->uuid, 'kind' => 'offer', 'category' => 'energy', 'title' => "Dryer {$n}", 'summary' => 'x', 'description' => 'y', 'created_by' => $owner->id]);
            $listing->forceFill(['status' => 'published', 'published_at' => now()])->save();
            $vacancy = Vacancy::query()->create(['startup_uuid' => $startup->uuid, 'title' => "Role {$n}", 'type' => 'full_time', 'description' => 'z', 'created_by' => $owner->id]);
            $vacancy->forceFill(['status' => 'open', 'published_at' => now()])->save();

            $opportunity = new Opportunity(['type' => 'grant', 'title' => "Grant {$n}", 'summary' => 's', 'deadline_at' => now()->addDays(10), 'status' => 'published']);
            $opportunity->published_at = now();
            $opportunity->save();

            $expert = User::factory()->withRole('startup_user')->create();
            $profile = new ExpertProfile(['headline' => "Expert {$n}", 'bio' => 'b', 'kind' => 'both', 'expertise' => ['finance'], 'sectors' => ['Agriculture & Agritech'], 'max_active' => 3, 'is_listed' => true]);
            $profile->user_id = $expert->id;
            $profile->status = 'verified';
            $profile->save();

            UserNotification::query()->create(['user_id' => $this->viewer->id, 'type' => 'talent', 'title' => "Note {$n}", 'url' => url('/')]);
        }
    }

    private function queries(string $url, ?User $as): int
    {
        $as ? $this->actingAs($as) : auth()->logout();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_list_pages_do_not_grow_queries_with_rows(): void
    {
        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->viewer = User::factory()->withRole('startup_user')->create();
        $investor = new InvestorProfile(['investor_type' => 'vc', 'bio' => 'b', 'currency' => 'USD', 'sectors' => ['Agriculture & Agritech'], 'stages' => ['Seed'], 'instruments' => ['equity'], 'is_listed' => true]);
        $investor->user_id = $this->viewer->id;
        $investor->status = 'verified';
        $investor->save();

        $founder = User::factory()->withRole('startup_user')->create();
        $mine = app(RegistryService::class)->createStartup($founder, [
            'legal_name' => 'Mine Ltd', 'display_name' => 'Mine', 'registration_number' => 'UG-MINE', 'country' => 'UG',
            'year_founded' => 2022, 'description' => null, 'website' => null,
        ], ['sector' => 'Agriculture & Agritech']);
        FundingRequest::query()->create(['startup_uuid' => $mine->uuid, 'amount' => 20000, 'currency' => 'USD', 'instruments' => ['equity'], 'pitch' => 'p', 'status' => 'open', 'created_by' => $founder->id]);

        $pages = [
            ['/startups', null], ['/marketplace', null], ['/jobs', null], ['/opportunities', null],
            ['/admin/startups', $manager], ['/admin/experts?status=verified', $manager], ['/admin/vacancies', $manager],
            ['/admin/insights', $manager], ['/admin', $manager], ['/admin/engagements', $manager], ['/admin/investors?status=verified', $manager],
            ["/workspace/startups/{$mine->uuid}/experts", $founder], ["/workspace/startups/{$mine->uuid}/overview", $founder],
            ["/workspace/startups/{$mine->uuid}/funding", $founder], ["/workspace/startups/{$mine->uuid}/opportunities", $founder],
            ['/investors?show=all', 'viewer'], ['/notifications', 'viewer'],

        ];
        $user = fn ($who) => $who === 'viewer' ? $this->viewer : $who;

        $this->startups(3);
        $few = [];
        foreach ($pages as [$url, $who]) {
            $few[$url] = $this->queries($url, $user($who));
        }
        $this->startups(12);
        $problems = [];
        foreach ($pages as [$url, $who]) {
            $many = $this->queries($url, $user($who));
            if ($many > $few[$url] + 2) {
                $problems[] = "{$url}: {$few[$url]} queries with 3 rows, {$many} with 15";
            }
        }
        $this->assertSame([], $problems, 'Queries grow with rows (N+1).');
    }
}
