<?php

namespace Tests\Feature\Communication;

use App\Communication\Notifier;
use App\Jobs\SendAnnouncement;
use App\Listeners\MarkEmailDelivered;
use App\Mail\JoinRequestReceived;
use App\Mail\ServiceNotice;
use App\Models\Communication\Announcement;
use App\Models\Communication\EmailDelivery;
use App\Models\Communication\UserNotification;
use App\Models\Experts\ExpertProfile;
use App\Models\User;
use App\Registry\RegistryService;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_notices_go_in_app_and_by_email_with_a_delivery_log_and_unsubscribe(): void
    {
        $user = User::factory()->withRole('startup_user')->create();
        app(Notifier::class)->notify($user, 'talent', 'Update on your application', ['Shortlisted.'], route('talent.dashboard'));

        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'type' => 'talent', 'title' => 'Update on your application', 'read_at' => null]);
        $delivery = EmailDelivery::query()->firstOrFail();
        $this->assertSame(['queued', 'talent', 'ServiceNotice'], [$delivery->status, $delivery->type, $delivery->mailable]);
        Mail::assertQueued(ServiceNotice::class, function (ServiceNotice $mail) use ($user, $delivery) {
            $headers = $mail->headers()->text;

            return $mail->hasTo($user->email) && $mail->deliveryId === $delivery->id
                && str_contains($headers['List-Unsubscribe'], '/notifications/unsubscribe/'.$user->id.'/talent')
                && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
        });

        // The centre shows it with an unread badge; opening marks it read and follows only local links.
        $this->actingAs($user)->get('/workspace')->assertSee('unread');
        $notification = UserNotification::query()->firstOrFail();
        $this->actingAs($user)->get("/notifications/{$notification->id}")->assertRedirect(route('talent.dashboard'));
        $this->assertNotNull($notification->fresh()->read_at);

        $notification->update(['url' => 'https://evil.example/phish']);
        $this->actingAs($user)->get("/notifications/{$notification->id}")->assertRedirect(route('notifications.index'));

        $other = User::factory()->withRole('startup_user')->create();
        $this->actingAs($other)->get("/notifications/{$notification->id}")->assertNotFound();
    }

    public function test_preferences_are_honoured_per_channel(): void
    {
        $user = User::factory()->withRole('startup_user')->create();
        $this->actingAs($user)->put('/notifications/preferences', [
            'email' => ['talent' => '1'], 'in_app' => ['funding' => '1'],
        ])->assertSessionHasNoErrors();

        $notifier = app(Notifier::class);
        $notifier->notify($user, 'talent', 'Talent news', ['x'], url('/'));
        $notifier->notify($user, 'funding', 'Funding news', ['y'], url('/'));
        $notifier->notify($user, 'experts', 'Expert news', ['z'], url('/'));

        Mail::assertQueued(ServiceNotice::class, 1);
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->subjectLine === 'Talent news');
        $this->assertSame(['Funding news'], UserNotification::query()->pluck('title')->all());

        // Announcements are opt-in for email by default.
        $fresh = User::factory()->withRole('startup_user')->create();
        $this->assertSame(['email' => false, 'in_app' => true], $notifier->preferenceFor($fresh, 'announcements'));
    }

    public function test_one_click_unsubscribe_needs_a_valid_signature_and_post(): void
    {
        $user = User::factory()->withRole('startup_user')->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->id, 'type' => 'marketplace']);

        $this->get($url)->assertOk()->assertSee('Stop these emails');
        $this->assertTrue(app(Notifier::class)->preferenceFor($user, 'marketplace')['email'], 'GET alone must not unsubscribe (link scanners).');

        $this->post(str_replace('signature=', 'signature=x', $url))->assertForbidden();
        $this->post($url)->assertOk()->assertSee('no longer get');
        $this->assertSame(['email' => false, 'in_app' => true], app(Notifier::class)->preferenceFor($user, 'marketplace'));

        $this->get(URL::signedRoute('notifications.unsubscribe', ['user' => $user->id, 'type' => 'nonsense']))->assertNotFound();
    }

    public function test_delivery_log_is_updated_when_sent_and_when_retries_fail(): void
    {
        $user = User::factory()->withRole('startup_user')->create();
        app(Notifier::class)->notify($user, 'experts', 'Session planned', ['Tuesday.'], url('/'));
        $delivery = EmailDelivery::query()->firstOrFail();

        $email = (new Email)->from('nsp@example.test')->to($user->email)->subject('x')->text('y');
        $email->getHeaders()->addTextHeader('X-NSP-Delivery', (string) $delivery->id);
        $sent = new SentMessage(new SymfonySentMessage($email, new Envelope(new Address('nsp@example.test'), [new Address($user->email)])));
        (new MarkEmailDelivered)->handle(new MessageSent($sent));
        $this->assertSame('sent', $delivery->fresh()->status);

        $mail = new ServiceNotice('Other', ['x'], url('/'));
        $mail->deliveryId = $delivery->id;
        $mail->failed(new \RuntimeException('SMTP 550 mailbox unavailable'));
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertStringContainsString('550', $delivery->fresh()->error);
        $this->assertSame(3, $mail->tries);

        $mail->unsubscribeUrl = 'https://nsp.test/notifications/unsubscribe/1/experts?signature=abc';
        $this->assertStringContainsString('You can turn off these emails here: https://nsp.test/notifications/unsubscribe/1/experts?signature=abc', $mail->render());
    }

    public function test_announcements_reach_the_chosen_audience_and_email_only_opted_in_people(): void
    {
        $founder = User::factory()->withRole('startup_user')->create();
        app(RegistryService::class)->createStartup($founder, [
            'legal_name' => 'Green Fields Ltd', 'display_name' => 'Green Fields', 'registration_number' => 'UG1',
            'country' => 'UG', 'year_founded' => 2022, 'description' => null, 'website' => null,
        ], ['sector' => 'Agriculture & Agritech']);
        $optedIn = User::factory()->withRole('startup_user')->create();
        app(RegistryService::class)->createStartup($optedIn, [
            'legal_name' => 'Sun Dry Ltd', 'display_name' => 'Sun Dry', 'registration_number' => 'UG2',
            'country' => 'UG', 'year_founded' => 2022, 'description' => null, 'website' => null,
        ], ['sector' => 'Agriculture & Agritech']);
        app(Notifier::class)->setPreference($optedIn, 'announcements', true, true);
        $expert = User::factory()->withRole('startup_user')->create();
        $profile = new ExpertProfile(['headline' => 'x', 'bio' => 'y', 'kind' => 'mentor', 'expertise' => ['finance'], 'max_active' => 3]);
        $profile->user_id = $expert->id;
        $profile->status = 'verified';
        $profile->save();

        $editor = User::factory()->withRole('content_editor')->create();
        $this->actingAs($editor)->get('/admin/communications')->assertForbidden();

        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->get('/admin/communications')->assertOk()->assertSee('Startup team members (2)');
        $this->actingAs($manager)->post('/admin/communications/announcements', [
            'title' => 'Demo day on 5 December', 'body' => 'Apply by Friday.', 'audience' => 'founders',
        ])->assertSessionHasNoErrors();
        $announcement = Announcement::query()->firstOrFail();
        $this->actingAs($manager)->post("/admin/communications/announcements/{$announcement->id}/send")->assertSessionHasNoErrors();

        $this->assertSame(['sent', 2], [$announcement->fresh()->status, $announcement->fresh()->recipients]);
        $this->assertEqualsCanonicalizing([$founder->id, $optedIn->id], UserNotification::query()->where('type', 'announcements')->pluck('user_id')->all());
        Mail::assertQueued(ServiceNotice::class, 1);
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($optedIn->email));
        $this->actingAs($manager)->post("/admin/communications/announcements/{$announcement->id}/send")->assertNotFound();
        $this->assertSame(2, SendAnnouncement::audienceQuery('founders')->count());
    }

    public function test_module_notices_now_land_in_the_centre(): void
    {
        $owner = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($owner, [
            'legal_name' => 'Green Fields Ltd', 'display_name' => 'Green Fields', 'registration_number' => 'UG1',
            'country' => 'UG', 'year_founded' => 2022, 'description' => null, 'website' => null,
        ], ['sector' => 'Agriculture & Agritech']);
        $joiner = User::factory()->withRole('startup_user')->create(['name' => 'Jo Joiner']);
        $this->actingAs($joiner)->post("/workspace/startups/{$startup->uuid}/join", ['message' => 'I run sales.']);

        $this->assertDatabaseHas('user_notifications', ['user_id' => $owner->id, 'type' => 'registry', 'title' => 'Jo Joiner asked to join Green Fields']);
        Mail::assertQueued(JoinRequestReceived::class, fn ($m) => $m->hasTo($owner->email) && $m->unsubscribeUrl !== null);
    }

    public function test_prune_removes_old_notifications_and_log_rows(): void
    {
        $user = User::factory()->withRole('startup_user')->create();
        UserNotification::query()->create(['user_id' => $user->id, 'type' => 'talent', 'title' => 'old read', 'read_at' => now()]);
        UserNotification::query()->create(['user_id' => $user->id, 'type' => 'talent', 'title' => 'old unread']);
        EmailDelivery::query()->create(['user_id' => $user->id, 'type' => 'talent', 'mailable' => 'ServiceNotice', 'subject' => 'x', 'status' => 'sent']);

        $this->travel(100)->days();
        UserNotification::query()->create(['user_id' => $user->id, 'type' => 'talent', 'title' => 'recent']);
        $this->artisan('notifications:prune')->assertSuccessful();
        $this->assertEqualsCanonicalizing(['old unread', 'recent'], UserNotification::query()->pluck('title')->all());

        $this->travel(300)->days();
        $this->artisan('notifications:prune')->assertSuccessful();
        $this->assertSame(['recent'], UserNotification::query()->pluck('title')->all());
        $this->assertSame(0, EmailDelivery::query()->count());
    }
}
