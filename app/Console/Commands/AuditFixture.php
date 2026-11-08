<?php

namespace App\Console\Commands;

use App\Communication\Notifier;
use App\Experts\ExpertService;
use App\Funding\FundingService;
use App\Marketplace\MarketplaceService;
use App\Models\Communication\Announcement;
use App\Models\Ecosystem\Opportunity;
use App\Models\Experts\ExpertProfile;
use App\Models\Funding\DataRoomFile;
use App\Models\Funding\FundingRequest;
use App\Models\Funding\Introduction;
use App\Models\Funding\InvestorProfile;
use App\Models\Marketplace\Listing;
use App\Models\Page;
use App\Models\Post;
use App\Models\Registry\Startup;
use App\Models\Talent\TalentProfile;
use App\Models\Talent\Vacancy;
use App\Models\User;
use App\Registry\RegistryService;
use App\Talent\TalentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Accessibility sweep fixture (A11Y-706): creates one realistic record in every module,
 * writes tests/a11y/pages.json with every page to audit, and removes it all with --clean.
 * Local and testing environments only. Every record is marked (emails @a11y.nsp.test,
 * registration numbers A11Y-, titles starting "A11Y") so cleaning touches nothing else.
 */
class AuditFixture extends Command
{
    protected $signature = 'nsp:audit-fixture {--clean : Remove the fixture instead of creating it} {--password= : Password for the audit admin (default: random, printed once)}';

    protected $description = 'Create (or --clean) sample data for the full-site accessibility sweep. Local only.';

    private const DOMAIN = '@a11y.nsp.test';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command only runs in local or testing environments.');

            return self::FAILURE;
        }
        $this->clean();
        if ($this->option('clean')) {
            $this->info('Audit fixture removed.');

            return self::SUCCESS;
        }

        $password = $this->option('password') ?: bin2hex(random_bytes(8));
        $pages = $this->build($password);
        $file = base_path('tests/a11y/pages.json');
        file_put_contents($file, json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('Audit fixture created. '.count($pages['public']).' public and '.count($pages['signedIn']).' signed-in pages listed in tests/a11y/pages.json.');
        $this->line('Sign in as admin'.self::DOMAIN.($this->option('password') ? '' : ' with password '.$password));

        return self::SUCCESS;
    }

    private function user(string $name, string $local, ?string $password = null): User
    {
        return User::query()->create(['name' => $name, 'email' => $local.self::DOMAIN, 'password' => bcrypt($password ?? bin2hex(random_bytes(12))), 'is_active' => true]);
    }

    private function build(string $password): array
    {
        $registry = app(RegistryService::class);
        $admin = $this->user('Audit Admin', 'admin', $password);
        $admin->assignRole('super_admin');
        $other = $this->user('Olive Founder', 'olive');
        $other->assignRole('startup_user');
        $expert = $this->user('Mona Mentor', 'mona');
        $candidate = $this->user('Carol Candidate', 'carol');

        $core = fn ($name, $reg) => ['legal_name' => $name.' Ltd', 'display_name' => $name, 'registration_number' => $reg, 'country' => 'UG',
            'year_founded' => 2022, 'description' => 'Solar dryers that cut post-harvest loss.', 'website' => 'https://'.strtolower(str_replace(' ', '-', $name)).'.test'];
        $mine = $registry->createStartup($admin, $core('A11Y Agro', 'A11Y-1'), ['sector' => 'Agriculture & Agritech', 'headquarters_location' => 'Masaka']);
        $theirs = $registry->createStartup($other, $core('A11Y Sun Dry', 'A11Y-2'), ['sector' => 'Energy & Climate', 'headquarters_location' => 'Gulu']);
        foreach ([[$mine, $admin], [$theirs, $other]] as [$startup, $owner]) {
            foreach (['nsp_directory', 'investor_listing'] as $purpose) {
                $registry->grantConsent($startup, $owner, $purpose);
            }
            FundingRequest::query()->create(['startup_uuid' => $startup->uuid, 'amount' => 75000, 'currency' => 'USD', 'instruments' => ['equity'], 'pitch' => 'Solar dryers for 2,000 farmers.', 'use_of_funds' => 'Production.', 'status' => 'open', 'created_by' => $owner->id]);
        }

        // Funding: admin is also a verified investor connected to Sun Dry with data-room access.
        $investor = new InvestorProfile(['organisation' => 'A11Y Capital', 'investor_type' => 'vc', 'bio' => 'Seed fund.', 'thesis' => 'Agritech.', 'currency' => 'USD', 'ticket_min' => 20000, 'ticket_max' => 150000, 'sectors' => ['Agriculture & Agritech'], 'countries' => ['UG'], 'stages' => [], 'instruments' => ['equity'], 'is_listed' => true]);
        $investor->user_id = $admin->id;
        $investor->status = 'verified';
        $investor->save();
        Storage::disk('local')->put('data-room/'.$theirs->uuid.'/deck.pdf', "%PDF-1.4\n%%EOF");
        DataRoomFile::query()->create(['startup_uuid' => $theirs->uuid, 'title' => 'A11Y pitch deck', 'category' => 'pitch_deck', 'disk' => 'local', 'path' => 'data-room/'.$theirs->uuid.'/deck.pdf', 'original_name' => 'deck.pdf', 'mime_type' => 'application/pdf', 'size' => 15, 'uploaded_by' => $other->id]);
        $intro = Introduction::query()->create(['startup_uuid' => $theirs->uuid, 'investor_user_id' => $admin->id, 'requested_by' => 'investor', 'requester_user_id' => $admin->id, 'message' => 'Interested.', 'status' => 'requested']);
        app(FundingService::class)->decide($intro, $other, true);
        app(FundingService::class)->grant($theirs, $other, $admin, now()->addDays(20)->toDateString());

        // Experts: Mona mentors A11Y Agro; admin is also an expert.
        foreach ([[$expert, 'Marketing mentor'], [$admin, 'Finance lead']] as [$person, $headline]) {
            $profile = new ExpertProfile(['headline' => $headline, 'bio' => 'Helps East African startups.', 'kind' => 'both', 'expertise' => ['finance', 'marketing'], 'sectors' => ['Agriculture & Agritech'], 'languages' => ['en'], 'offers_free' => true, 'max_active' => 5, 'is_listed' => true]);
            $profile->user_id = $person->id;
            $profile->status = 'verified';
            $profile->save();
        }
        $engagement = app(ExpertService::class)->request($mine, $admin, $expert, ['type' => 'mentorship', 'topic' => 'A11Y go-to-market plan', 'details' => 'Launch plan.']);
        app(ExpertService::class)->decide($engagement, $expert, true, null);
        app(ExpertService::class)->schedule($engagement, $admin, ['scheduled_at' => now()->addDays(4), 'duration_minutes' => 60, 'mode' => 'online', 'location' => 'https://meet.example.test/a']);

        // Talent: a vacancy at A11Y Agro with Carol's application and an interview.
        foreach ([$candidate, $admin] as $person) {
            $talent = new TalentProfile(['headline' => 'Sales rep', 'summary' => 'Field sales.', 'skills' => ['Sales'], 'work_types' => ['full_time'], 'location' => 'Masaka', 'country' => 'UG', 'open_to_work' => true, 'visibility' => 'startups']);
            $talent->user_id = $person->id;
            $talent->consented_at = now();
            $talent->consent_version = config('nsp.talent.consent_version');
            $talent->save();
        }
        $vacancy = Vacancy::query()->create(['startup_uuid' => $mine->uuid, 'title' => 'A11Y Field Sales Officer', 'type' => 'full_time', 'description' => 'Sell dryers.', 'skills' => ['Sales'], 'location' => 'Masaka', 'created_by' => $admin->id]);
        $vacancy->forceFill(['status' => 'open', 'published_at' => now()])->save();
        $application = app(TalentService::class)->apply($vacancy, $candidate, 'I sold inputs for three years.');
        app(TalentService::class)->scheduleInterview($application, $admin, ['scheduled_at' => now()->addDays(3), 'duration_minutes' => 45, 'mode' => 'in_person', 'location' => 'Hive office']);
        $theirVacancy = Vacancy::query()->create(['startup_uuid' => $theirs->uuid, 'title' => 'A11Y Data Intern', 'type' => 'internship', 'description' => 'Clean data.', 'remote' => true, 'created_by' => $other->id]);
        $theirVacancy->forceFill(['status' => 'open', 'published_at' => now()])->save();
        app(TalentService::class)->apply($theirVacancy, $admin, 'Keen to learn.');

        // Marketplace: a listing each, a conversation, a report.
        $make = function (Startup $startup, User $by, string $kind, string $title) {
            $listing = Listing::query()->create(['startup_uuid' => $startup->uuid, 'kind' => $kind, 'category' => 'energy', 'title' => $title, 'summary' => 'Short summary.', 'description' => "Details.\nMore.", 'price_note' => 'UGX 2.5M', 'location' => 'Masaka', 'created_by' => $by->id]);
            $listing->forceFill(['status' => 'published', 'published_at' => now()])->save();

            return $listing;
        };
        $myListing = $make($mine, $admin, 'offer', 'A11Y solar crop dryer');
        $theirListing = $make($theirs, $other, 'request', 'A11Y cold-chain truck');
        $inquiry = app(MarketplaceService::class)->inquire($myListing, $other, 'Price for 3 dryers', 'Can you deliver to Gulu?', $theirs);
        $myInquiry = app(MarketplaceService::class)->inquire($theirListing, $admin, 'Truck availability', 'We have a truck on Tuesdays.', null);
        $report = app(MarketplaceService::class)->reportInquiry($inquiry, $other, 'not_delivered', 'Waiting.');

        // Opportunities, CMS content, notifications, announcement.
        $opportunity = new Opportunity(['type' => 'grant', 'title' => 'A11Y Seed Grant', 'summary' => 'Up to UGX 20M.', 'deadline_at' => now()->addDays(10), 'status' => 'published', 'accepts_interest' => true]);
        $opportunity->published_at = now();
        $opportunity->save();
        $page = Page::query()->create(['title' => 'A11Y sample page', 'slug' => 'a11y-sample-page', 'blocks' => [], 'status' => 'published', 'published_at' => now()]);
        $post = Post::query()->create(['title' => 'A11Y sample news', 'slug' => 'a11y-sample-news', 'body' => "## Heading\n\nSome **news**.", 'status' => 'published', 'published_at' => now()]);
        app(Notifier::class)->notify($admin, 'talent', 'A11Y update on your application', ['Shortlisted.'], url('/talent'));
        Announcement::query()->create(['title' => 'A11Y draft news', 'body' => 'Coming soon.', 'audience' => 'everyone', 'created_by' => $admin->id]);

        $s = '/workspace/startups/'.$mine->uuid;

        return [
            'public' => ['/', '/news', '/news/'.$post->slug, '/'.$page->slug, '/faqs', '/contact', '/login', '/forgot-password',
                '/opportunities', '/opportunities/'.$opportunity->slug, '/jobs', '/jobs/'.$theirVacancy->slug, '/marketplace', '/marketplace/'.$theirListing->slug,
                '/startups', '/startups/'.$theirs->uuid],
            'signedIn' => [
                '/workspace', '/account/security', '/notifications', '/notifications/preferences',
                '/workspace/startups', '/workspace/startups/new', $s.'/overview', $s, $s.'/edit', $s.'/team', $s.'/sharing', $s.'/history',
                $s.'/growth', $s.'/opportunities', $s.'/funding', $s.'/experts', $s.'/talent', $s.'/talent/vacancies/'.$vacancy->slug,
                $s.'/talent/vacancies/'.$vacancy->slug.'/edit', $s.'/talent/applications/'.$application->uuid, $s.'/talent/candidates', $s.'/marketplace',
                $s.'/marketplace/listings/'.$myListing->slug.'/edit',
                '/opportunities/submit', '/investors', '/investors/profile', '/investors/startups/'.$theirs->uuid,
                '/experts', '/experts/profile', '/engagements/'.$engagement->uuid, '/talent', '/talent/profile',
                '/marketplace/messages', '/marketplace/messages/'.$inquiry->uuid, '/marketplace/messages/'.$myInquiry->uuid,
                '/admin', '/admin/pages', '/admin/pages/create', '/admin/posts', '/admin/posts/create', '/admin/media', '/admin/menus',
                '/admin/content/faqs', '/admin/redirects', '/admin/messages', '/admin/settings', '/admin/users', '/admin/users/create',
                '/admin/features', '/admin/audit', '/admin/system', '/admin/insights', '/admin/communications', '/admin/startups',
                '/admin/startups/'.$mine->uuid, '/admin/duplicates', '/admin/opportunities', '/admin/opportunities/create',
                '/admin/vgi', '/admin/investors?status=verified', '/admin/experts?status=verified', '/admin/engagements',
                '/admin/vacancies', '/admin/marketplace', '/admin/marketplace/reports/'.$report->id,
            ],
        ];
    }

    /** Removes everything the fixture created, identified by its markers only. */
    private function clean(): void
    {
        $users = User::query()->where('email', 'like', '%'.self::DOMAIN)->pluck('id');
        $startups = Startup::query()->where('registration_number', 'like', 'A11Y-%')->pluck('uuid');
        foreach ($startups as $uuid) {
            Storage::disk('local')->deleteDirectory('data-room/'.$uuid);
        }
        $vacancies = DB::table('vacancies')->whereIn('startup_uuid', $startups)->pluck('uuid');
        $applications = DB::table('job_applications')->whereIn('vacancy_uuid', $vacancies)->orWhereIn('applicant_id', $users)->get(['uuid', 'cv_path']);
        foreach ($applications as $application) {
            if ($application->cv_path) {
                Storage::disk('local')->delete($application->cv_path);
            }
        }

        DB::transaction(function () use ($users, $startups) {
            $engagements = DB::table('expert_engagements')->whereIn('startup_uuid', $startups)->orWhereIn('expert_user_id', $users)->pluck('uuid');
            $listings = DB::table('marketplace_listings')->whereIn('startup_uuid', $startups)->pluck('uuid');
            $inquiries = DB::table('marketplace_inquiries')->whereIn('listing_uuid', $listings)->orWhereIn('inquirer_id', $users)->pluck('uuid');
            $vacancies = DB::table('vacancies')->whereIn('startup_uuid', $startups)->pluck('uuid');
            $applications = DB::table('job_applications')->whereIn('vacancy_uuid', $vacancies)->orWhereIn('applicant_id', $users)->pluck('uuid');

            DB::table('marketplace_reports')->whereIn('listing_uuid', $listings)->orWhereIn('inquiry_uuid', $inquiries)->orWhereIn('reporter_id', $users)->delete();
            DB::table('marketplace_messages')->whereIn('inquiry_uuid', $inquiries)->delete();
            DB::table('marketplace_inquiries')->whereIn('uuid', $inquiries)->delete();
            DB::table('session_actions')->whereIn('engagement_uuid', $engagements)->delete();
            DB::table('expert_sessions')->whereIn('engagement_uuid', $engagements)->delete();
            DB::table('expert_reviews')->whereIn('engagement_uuid', $engagements)->delete();
            DB::table('expert_engagements')->whereIn('uuid', $engagements)->delete();
            DB::table('interviews')->whereIn('application_uuid', $applications)->delete();
            DB::table('hiring_pipeline_events')->whereIn('application_uuid', $applications)->delete();
            DB::table('job_applications')->whereIn('uuid', $applications)->delete();
            DB::table('vacancy_invitations')->whereIn('vacancy_uuid', $vacancies)->delete();

            // Every other table keyed by startup, then the startups.
            foreach (Schema::getTables() as $table) {
                $name = $table['name'];
                if ($name !== 'startups' && Schema::hasColumn($name, 'startup_uuid')) {
                    DB::table($name)->whereIn('startup_uuid', $startups)->delete();
                }
            }
            DB::table('startups')->whereIn('uuid', $startups)->delete();

            DB::table('opportunity_bookmarks')->whereIn('opportunity_id', DB::table('opportunities')->where('title', 'like', 'A11Y %')->select('id'))->delete();
            DB::table('opportunity_interests')->whereIn('opportunity_id', DB::table('opportunities')->where('title', 'like', 'A11Y %')->select('id'))->delete();
            DB::table('opportunities')->where('title', 'like', 'A11Y %')->delete();
            DB::table('page_revisions')->whereIn('page_id', DB::table('pages')->where('slug', 'a11y-sample-page')->select('id'))->delete();
            DB::table('pages')->where('slug', 'a11y-sample-page')->delete();
            DB::table('posts')->where('slug', 'a11y-sample-news')->delete();
            DB::table('announcements')->where('title', 'like', 'A11Y %')->delete();
            DB::table('jobs')->where('payload', 'like', '%a11y.nsp.test%')->delete();

            // Every table keyed by user, then the users.
            DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $users)->delete();
            foreach (Schema::getTables() as $table) {
                $name = $table['name'];
                if ($name === 'users') {
                    continue;
                }
                foreach (['user_id', 'investor_user_id', 'expert_user_id', 'applicant_id', 'inquirer_id'] as $column) {
                    if (Schema::hasColumn($name, $column)) {
                        DB::table($name)->whereIn($column, $users)->delete();
                    }
                }
            }
            DB::table('users')->whereIn('id', $users)->delete();
        });
        @unlink(base_path('tests/a11y/pages.json'));
    }
}
