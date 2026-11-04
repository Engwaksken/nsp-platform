<?php

namespace Tests\Feature\Funding;

use App\Mail\FundingNotice;
use App\Models\Funding\DataRoomAccessLog;
use App\Models\Funding\DataRoomFile;
use App\Models\Funding\DataRoomGrant;
use App\Models\Funding\Introduction;
use App\Models\Funding\InvestorProfile;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FundingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    /** @return array{0: User, 1: Startup} */
    private function founderWithStartup(string $name = 'Green Fields'): array
    {
        $user = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($user, [
            'legal_name' => $name.' Ltd', 'display_name' => $name, 'registration_number' => 'UG-'.$name,
            'country' => 'UG', 'year_founded' => 2022, 'description' => null, 'website' => null,
        ], ['sector' => 'Agriculture & Agritech', 'contact_email' => 'hello@greenfields.test']);

        return [$user, $startup->fresh('profile')];
    }

    private function investor(string $status = 'verified', array $attributes = []): User
    {
        $first = ! User::query()->where('email', 'ivy@fund.test')->exists();
        $user = User::factory()->withRole('startup_user')->create($first ? ['name' => 'Ivy Investor', 'email' => 'ivy@fund.test'] : []);
        $profile = new InvestorProfile(array_merge([
            'organisation' => 'Seed Fund', 'investor_type' => 'vc', 'bio' => 'Early-stage fund.', 'currency' => 'USD',
            'ticket_min' => 10000, 'ticket_max' => 100000, 'sectors' => ['Agriculture & Agritech'], 'countries' => ['UG'],
            'instruments' => ['equity'], 'stages' => [], 'is_listed' => true,
        ], $attributes));
        $profile->user_id = $user->id;
        $profile->status = $status;
        $profile->save();

        return $user;
    }

    /** Open request + investor listing consent. */
    private function raising(User $founder, Startup $startup, bool $consent = true): void
    {
        $this->actingAs($founder)->put("/workspace/startups/{$startup->uuid}/funding/request", [
            'amount' => 50000, 'currency' => 'USD', 'instruments' => ['equity'], 'pitch' => 'Solar dryers for farmers.',
        ])->assertSessionHasNoErrors();
        if ($consent) {
            app(RegistryService::class)->grantConsent($startup, $founder, 'investor_listing');
        }
    }

    private function upload(User $founder, Startup $startup): DataRoomFile
    {
        $pdf = UploadedFile::fake()->createWithContent('deck.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/files", [
            'title' => 'Pitch deck 2026', 'category' => 'pitch_deck', 'file' => $pdf,
        ])->assertSessionHasNoErrors();

        return DataRoomFile::query()->latest()->firstOrFail();
    }

    private function connect(User $founder, Startup $startup, User $investor): Introduction
    {
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/interest", ['message' => 'We like solar.'])->assertSessionHasNoErrors();
        $introduction = Introduction::query()->where('investor_user_id', $investor->id)->firstOrFail();
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/introductions/{$introduction->uuid}/decision", ['decision' => 'accept'])->assertSessionHasNoErrors();

        return $introduction->fresh();
    }

    public function test_investor_without_a_data_room_grant_cannot_access_the_pitch_deck(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $this->raising($founder, $startup);
        $file = $this->upload($founder, $startup);
        $investor = $this->investor();

        // Verified and able to see the listing, but no grant: the file does not exist for them.
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}")->assertOk()->assertSee('Solar dryers')->assertDontSee('Pitch deck 2026');
        $this->actingAs($investor)->get("/data-room/{$file->uuid}")->assertNotFound();

        // Even after connecting, documents need an explicit grant.
        $this->connect($founder, $startup, $investor);
        $this->actingAs($investor)->get("/data-room/{$file->uuid}")->assertNotFound();
        $this->assertSame(0, DataRoomAccessLog::query()->count());
    }

    public function test_granted_investor_can_download_until_revoked_or_expired_and_downloads_are_logged(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $this->raising($founder, $startup);
        $file = $this->upload($founder, $startup);
        $investor = $this->investor();
        $this->connect($founder, $startup, $investor);

        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/grants", [
            'investor_user_id' => $investor->id, 'expires_at' => now()->addDays(10)->toDateString(),
        ])->assertSessionHasNoErrors();
        Mail::assertQueued(FundingNotice::class, fn ($mail) => $mail->hasTo('ivy@fund.test') && str_contains($mail->subjectLine, 'shared its data room'));

        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}")->assertSee('Pitch deck 2026');
        $this->actingAs($investor)->get("/data-room/{$file->uuid}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('data_room_access_logs', ['file_uuid' => $file->uuid, 'user_id' => $investor->id, 'access_type' => 'investor']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'funding.file_downloaded']);
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/funding")->assertSee('Ivy Investor (investor)');

        // Expiry.
        $this->travel(11)->days();
        $this->actingAs($investor)->get("/data-room/{$file->uuid}")->assertNotFound();
        $this->travelBack();

        // Revocation.
        $grant = DataRoomGrant::query()->firstOrFail();
        $this->actingAs($investor)->get("/data-room/{$file->uuid}")->assertOk();
        $this->actingAs($founder)->delete("/workspace/startups/{$startup->uuid}/funding/grants/{$grant->id}")->assertSessionHasNoErrors();
        $this->actingAs($investor)->get("/data-room/{$file->uuid}")->assertNotFound();
    }

    public function test_team_members_can_download_and_outsiders_cannot(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $file = $this->upload($founder, $startup);
        Storage::disk('local')->assertExists($file->path);
        $this->actingAs($founder)->get("/data-room/{$file->uuid}")->assertOk();

        $this->app['auth']->forgetGuards();
        $this->get("/data-room/{$file->uuid}")->assertRedirect('/login');

        $stranger = User::factory()->withRole('startup_user')->create();
        $this->actingAs($stranger)->get("/data-room/{$file->uuid}")->assertNotFound();
        $this->actingAs($stranger)->get("/workspace/startups/{$startup->uuid}/funding")->assertNotFound();
    }

    public function test_uploads_are_checked_by_content_not_name(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        // A real temporary file (fake uploads report a type from the name, which is what we must not trust).
        $path = tempnam(sys_get_temp_dir(), 'nsp');
        file_put_contents($path, "<?php echo 'hi'; ?>
<html><script>alert(1)</script></html>");
        $fake = new UploadedFile($path, 'deck.pdf', 'application/pdf', null, true);
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/files", [
            'title' => 'Not a PDF', 'category' => 'pitch_deck', 'file' => $fake,
        ])->assertSessionHasErrors('file');
        $this->assertSame(0, DataRoomFile::query()->count());
    }

    public function test_only_verified_investors_see_startups_and_only_consented_open_requests(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        [$founder2, $quiet] = $this->founderWithStartup('Quiet Co');
        $this->raising($founder, $startup);
        $this->raising($founder2, $quiet, consent: false);

        $pending = $this->investor('pending');
        $this->actingAs($pending)->get('/investors')->assertRedirect('/investors/profile');
        $this->actingAs($pending)->get("/investors/startups/{$startup->uuid}")->assertNotFound();

        $investor = $this->investor('verified', []);
        $this->actingAs($investor)->get('/investors')->assertOk()->assertSee('Green Fields')->assertDontSee('Quiet Co');
        $this->actingAs($investor)->get("/investors/startups/{$quiet->uuid}")->assertNotFound();
        // The founders' contact details stay hidden until an introduction is accepted.
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}")->assertOk()->assertDontSee('hello@greenfields.test')->assertDontSee($founder->email);

        // Revoking consent hides the startup again.
        app(RegistryService::class)->revokeConsent($startup, $founder, 'investor_listing');
        $this->actingAs($investor)->get('/investors?show=all')->assertDontSee('Green Fields');
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}")->assertNotFound();
    }

    public function test_introductions_reveal_contacts_only_after_acceptance_and_notes_stay_private(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $this->raising($founder, $startup);
        $investor = $this->investor();

        // The founder sees the investor as a match with reasons, but not their email.
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/funding")->assertOk()
            ->assertSee('Ivy Investor')->assertSee('Good fit')->assertSee('Sector: Agriculture &amp; Agritech', false)->assertDontSee('ivy@fund.test');

        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/introductions", [
            'investor_user_id' => $investor->id, 'message' => 'Would love to talk.',
        ])->assertSessionHasNoErrors();
        $introduction = Introduction::query()->firstOrFail();
        Mail::assertQueued(FundingNotice::class, fn ($mail) => $mail->hasTo('ivy@fund.test'));

        // The founder cannot accept their own request; a duplicate is refused.
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/introductions/{$introduction->uuid}/decision", ['decision' => 'accept'])
            ->assertSessionHasErrors('introduction');
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/introductions", [
            'investor_user_id' => $investor->id, 'message' => 'Again.',
        ])->assertSessionHasErrors('introduction');

        $this->actingAs($investor)->post("/investors/introductions/{$introduction->uuid}/decision", ['decision' => 'accept'])->assertSessionHasNoErrors();
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}")->assertSee($founder->email)->assertSee('hello@greenfields.test');
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/funding")->assertSee('ivy@fund.test');

        // Private pipeline notes.
        $this->actingAs($investor)->put("/investors/introductions/{$introduction->uuid}", ['investor_stage' => 'due_diligence', 'investor_notes' => 'Strong unit economics'])->assertSessionHasNoErrors();
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/funding")->assertDontSee('Strong unit economics')->assertDontSee('Due diligence');
        $other = $this->investor('verified', []);
        $this->actingAs($other)->put("/investors/introductions/{$introduction->uuid}", ['investor_notes' => 'hijack'])->assertNotFound();
    }

    public function test_data_room_can_only_be_shared_with_connected_verified_investors(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $this->raising($founder, $startup);
        $investor = $this->investor();
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/grants", ['investor_user_id' => $investor->id])
            ->assertSessionHasErrors('grant');

        $unverified = $this->investor('pending');
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/grants", ['investor_user_id' => $unverified->id])
            ->assertSessionHasErrors('grant');
        $this->assertSame(0, DataRoomGrant::query()->count());
    }

    public function test_team_members_without_edit_rights_cannot_change_funding(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $member = User::factory()->withRole('startup_user')->create();
        StartupMembership::query()->create(['startup_uuid' => $startup->uuid, 'user_id' => $member->id, 'role' => 'viewer', 'status' => 'active']);

        $this->actingAs($member)->get("/workspace/startups/{$startup->uuid}/funding")->assertOk();
        $this->actingAs($member)->put("/workspace/startups/{$startup->uuid}/funding/request", [
            'amount' => 1, 'currency' => 'USD', 'instruments' => ['equity'], 'pitch' => 'x',
        ])->assertForbidden();
    }

    public function test_staff_verify_investors_and_the_role_follows(): void
    {
        $applicant = User::factory()->withRole('startup_user')->create();
        $this->actingAs($applicant)->put('/investors/profile', [
            'investor_type' => 'angel', 'bio' => 'Angel in Kampala.', 'currency' => 'UGX', 'ticket_min' => 5, 'ticket_max' => 1,
        ])->assertSessionHasErrors('ticket_max');
        $this->actingAs($applicant)->put('/investors/profile', [
            'investor_type' => 'angel', 'bio' => 'Angel in Kampala.', 'currency' => 'UGX', 'sectors' => ['Fintech'], 'is_listed' => '1',
        ])->assertSessionHasNoErrors();
        $profile = InvestorProfile::query()->where('user_id', $applicant->id)->firstOrFail();
        $this->assertSame('pending', $profile->status);

        $editor = User::factory()->withRole('content_editor')->create();
        $this->actingAs($editor)->get('/admin/investors')->assertForbidden();

        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->get('/admin/investors')->assertOk()->assertSee('Angel in Kampala.');
        $this->actingAs($manager)->put("/admin/investors/{$profile->id}", ['status' => 'rejected'])->assertSessionHasErrors('verification_note');
        $this->actingAs($manager)->put("/admin/investors/{$profile->id}", ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->assertTrue($applicant->fresh()->hasRole('investor'));
        $this->actingAs($applicant)->get('/investors')->assertOk();

        $this->actingAs($manager)->put("/admin/investors/{$profile->id}", ['status' => 'suspended', 'verification_note' => 'Complaint received'])->assertSessionHasNoErrors();
        $this->assertFalse($applicant->fresh()->hasRole('investor'));
        $this->actingAs($applicant)->get('/investors')->assertRedirect('/investors/profile');
        $this->assertDatabaseHas('audit_logs', ['action' => 'funding.investor_suspended']);
    }

    public function test_closing_the_request_hides_the_startup(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $this->raising($founder, $startup);
        $investor = $this->investor();
        $this->actingAs($investor)->get('/investors?show=all')->assertSee('Green Fields');

        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/request/close")->assertSessionHasNoErrors();
        $this->actingAs($investor)->get('/investors?show=all')->assertDontSee('Green Fields');
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}")->assertNotFound();
    }
}
