<?php

namespace Tests\Feature\Funding;

use App\Models\AuditLog;
use App\Models\Capital\DiligenceItem;
use App\Models\Capital\DocumentRequest;
use App\Models\Capital\Interaction;
use App\Models\Capital\PipelineEvent;
use App\Models\Capital\PipelineItem;
use App\Models\Capital\ShareGrant;
use App\Models\Funding\DataRoomFile;
use App\Models\Funding\DataRoomGrant;
use App\Models\Funding\Introduction;
use App\Models\Funding\InvestorProfile;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryService;
use App\Support\Features;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

/** M08 Capital Exchange (CAP-01): founder sharing controls, investor pipeline, readiness and due diligence. */
class CapitalExchangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        Features::set('capital_exchange', true, null);
    }

    /** @return array{0: User, 1: Startup} */
    private function founderWithStartup(string $name = 'Green Fields'): array
    {
        $user = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($user, [
            'legal_name' => $name.' Ltd', 'display_name' => $name, 'registration_number' => 'UG-'.$name,
            'country' => 'UG', 'year_founded' => 2022, 'description' => null, 'website' => null,
        ], ['sector' => 'Agriculture & Agritech', 'contact_email' => 'hello@'.strtolower(str_replace(' ', '', $name)).'.test']);

        return [$user, $startup->fresh('profile')];
    }

    private function investor(string $status = 'verified'): User
    {
        $user = User::factory()->withRole('startup_user')->create();
        $profile = new InvestorProfile([
            'organisation' => 'Seed Fund', 'investor_type' => 'vc', 'bio' => 'Early-stage fund.', 'currency' => 'USD',
            'ticket_min' => 10000, 'ticket_max' => 100000, 'sectors' => ['Agriculture & Agritech'], 'countries' => ['UG'],
            'instruments' => ['equity'], 'stages' => [], 'is_listed' => true,
        ]);
        $profile->user_id = $user->id;
        $profile->status = $status;
        $profile->save();

        return $user;
    }

    private function raising(User $founder, Startup $startup): void
    {
        $this->actingAs($founder)->put("/workspace/startups/{$startup->uuid}/funding/request", [
            'amount' => 50000, 'currency' => 'USD', 'instruments' => ['equity'], 'pitch' => 'Solar dryers for farmers.',
        ])->assertSessionHasNoErrors();
        app(RegistryService::class)->grantConsent($startup, $founder, 'investor_listing');
    }

    private function connect(User $founder, Startup $startup, User $investor): Introduction
    {
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/interest", ['message' => 'We like solar.'])->assertSessionHasNoErrors();
        $introduction = Introduction::query()->where('investor_user_id', $investor->id)->where('startup_uuid', $startup->uuid)->firstOrFail();
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/introductions/{$introduction->uuid}/decision", ['decision' => 'accept'])->assertSessionHasNoErrors();

        return $introduction->fresh();
    }

    private function member(Startup $startup, string $role): User
    {
        $user = User::factory()->withRole('startup_user')->create();
        StartupMembership::query()->create(['startup_uuid' => $startup->uuid, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);

        return $user;
    }

    private function upload(User $founder, Startup $startup, string $title = 'Management accounts', string $category = 'financials'): DataRoomFile
    {
        $pdf = UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/funding/files", [
            'title' => $title, 'category' => $category, 'file' => $pdf,
        ])->assertSessionHasNoErrors();

        return DataRoomFile::query()->where('title', $title)->latest()->firstOrFail();
    }

    /** A raising startup with a founder, a connected verified investor and a saved funding profile. */
    private function connectedPair(): array
    {
        [$founder, $startup] = $this->founderWithStartup();
        $this->raising($founder, $startup);
        $investor = $this->investor();
        $this->connect($founder, $startup, $investor);
        $this->actingAs($founder)->put("/workspace/startups/{$startup->uuid}/capital/profile", [
            'round_stage' => 'seed', 'instruments_sought' => ['equity'], 'use_of_funds_breakdown' => ['product' => 60, 'hiring' => 40],
            'traction_summary' => 'TRACTION-SECRET 400 farmers', 'financial_summary' => 'FINANCE-SECRET runway 14 months',
        ])->assertSessionHasNoErrors();

        return [$founder, $startup, $investor];
    }

    private function share(User $founder, Startup $startup, User $investor, array $scopes, ?string $expires = null): void
    {
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/capital/shares", array_filter([
            'investor_user_id' => $investor->id, 'scopes' => $scopes, 'expires_at' => $expires,
        ]))->assertSessionHasNoErrors();
    }

    // ----- Feature flag -----------------------------------------------------

    public function test_every_capital_page_is_404_while_the_flag_is_off(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();
        Features::set('capital_exchange', false, null);

        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital")->assertNotFound();
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital/readiness")->assertNotFound();
        $this->actingAs($investor)->get('/investors/pipeline')->assertNotFound();
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/diligence")->assertNotFound();
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/pipeline", ['stage' => 'sourced'])->assertNotFound();
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}")->assertOk()->assertDontSee('Capital Exchange');
        $this->assertSame(0, PipelineItem::query()->count());
    }

    public function test_admin_can_switch_capital_exchange_on_and_it_is_off_by_default(): void
    {
        $this->assertFalse((bool) config('nsp.features.capital_exchange.default'));
        Features::set('capital_exchange', false, null);

        $this->actingAs($this->userWithRole('platform_admin'))->put('/admin/features/capital_exchange', ['enabled' => 1])->assertSessionHasNoErrors();
        $this->assertTrue(Features::enabled('capital_exchange'));
    }

    // ----- Founder pages and roles -----------------------------------------

    public function test_founder_pages_render_and_navigation_links_appear(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();

        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital")->assertOk()
            ->assertSee('Capital Exchange')->assertSee($investor->name)->assertSee('Nothing shared')
            ->assertSee(route('workspace.capital.index', $startup), false);
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital/readiness")->assertOk()
            ->assertSee('TRACTION-SECRET')->assertSee('FINANCE-SECRET')->assertSee('Product and technology');
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital/readiness?print=1")->assertOk()->assertSee('Investment readiness');
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital/activity")->assertOk();
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/funding")->assertOk()
            ->assertSee(route('workspace.capital.index', $startup), false);
        $this->actingAs($investor)->get('/investors')->assertOk()->assertSee(route('investor.capital.pipeline'), false);
    }

    public function test_team_viewers_can_look_but_not_change_and_outsiders_get_404(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();
        $viewer = $this->member($startup, 'viewer');
        $outsider = User::factory()->withRole('startup_user')->create();
        $base = "/workspace/startups/{$startup->uuid}/capital";

        $this->actingAs($viewer)->get($base)->assertOk()->assertDontSee('Save funding profile');
        $this->actingAs($viewer)->put("$base/profile", ['round_stage' => 'seed'])->assertForbidden();
        $this->actingAs($viewer)->post("$base/shares", ['investor_user_id' => $investor->id, 'scopes' => ['traction']])->assertForbidden();
        $this->actingAs($viewer)->post("$base/match-feedback", ['investor_user_id' => $investor->id, 'rating' => 'useful'])->assertForbidden();
        $this->assertSame(0, ShareGrant::query()->count());

        foreach (['', '/readiness', '/activity'] as $page) {
            $this->actingAs($outsider)->get($base.$page)->assertNotFound();
            $this->actingAs($investor)->get($base.$page)->assertNotFound();
        }
        $this->actingAs($outsider)->post("$base/shares", ['investor_user_id' => $outsider->id, 'scopes' => ['traction']])->assertNotFound();

        // A share grant of another startup cannot be revoked through this one.
        $this->share($founder, $startup, $investor, ['traction']);
        [$otherFounder, $other] = $this->founderWithStartup('Blue Water');
        $grant = ShareGrant::query()->firstOrFail();
        $this->actingAs($otherFounder)->delete("/workspace/startups/{$other->uuid}/capital/shares/{$grant->id}")->assertNotFound();
        $this->assertNull($grant->fresh()->revoked_at);
    }

    public function test_sharing_needs_a_connected_verified_investor(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $this->raising($founder, $startup);
        $stranger = $this->investor();

        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/capital/shares", ['investor_user_id' => $stranger->id, 'scopes' => ['traction']])
            ->assertSessionHasErrors('share');
        $this->assertSame(0, ShareGrant::query()->count());
    }

    // ----- Scopes, expiry and revocation -----------------------------------

    public function test_investor_sees_only_the_scopes_granted(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();
        $this->share($founder, $startup, $investor, ['traction']);
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital")->assertOk()->assertSee('Stop sharing');
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}")->assertOk()->assertSee(route('investor.capital.readiness', $startup), false);

        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/readiness")->assertOk()
            ->assertSee('TRACTION-SECRET')->assertDontSee('FINANCE-SECRET')->assertDontSee('Product and technology')
            ->assertSee('Not shared with you.');
        $this->assertDatabaseHas('investor_interactions', ['type' => 'viewed_readiness', 'investor_user_id' => $investor->id]);

        // Changing the choice replaces the earlier one.
        $this->share($founder, $startup, $investor, ['financials']);
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/readiness")->assertOk()
            ->assertDontSee('TRACTION-SECRET')->assertSee('FINANCE-SECRET');
        $this->assertSame(1, ShareGrant::query()->active()->count());
    }

    public function test_revoking_a_share_stops_access_immediately(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();
        $this->share($founder, $startup, $investor, ['traction', 'financials']);
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/readiness")->assertOk();

        $grant = ShareGrant::query()->firstOrFail();
        $this->actingAs($founder)->delete("/workspace/startups/{$startup->uuid}/capital/shares/{$grant->id}")->assertSessionHasNoErrors();

        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/readiness")->assertNotFound();
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/readiness?print=1")->assertNotFound();
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}")->assertOk()->assertDontSee('TRACTION-SECRET');
        $this->assertDatabaseHas('audit_logs', ['action' => 'capital.scopes_revoked']);
    }

    public function test_expired_share_stops_access_without_any_job_running(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();
        $this->share($founder, $startup, $investor, ['traction'], now()->addDays(3)->toDateString());
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/readiness")->assertOk()->assertSee('TRACTION-SECRET');

        $this->travel(4)->days();
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/readiness")->assertNotFound();
    }

    public function test_shares_stop_working_when_the_investor_loses_verification(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();
        $this->share($founder, $startup, $investor, ['traction']);
        InvestorProfile::query()->where('user_id', $investor->id)->update(['status' => 'rejected']);

        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/readiness")->assertNotFound();
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/diligence")->assertNotFound();
        $this->actingAs($investor)->get('/investors/pipeline')->assertRedirect(route('investor.apply'));
    }

    // ----- Investor isolation ----------------------------------------------

    public function test_investor_cannot_reach_unrelated_or_unconnected_startups(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();
        $this->share($founder, $startup, $investor, ['traction']);

        // Listed but not connected: may watch and track, but no readiness or diligence.
        [$founder2, $listed] = $this->founderWithStartup('Blue Water');
        $this->raising($founder2, $listed);
        // Not listed at all (no consent, no request) and not connected: nothing.
        [, $hidden] = $this->founderWithStartup('Quiet Hill');

        $this->actingAs($investor)->get("/investors/startups/{$listed->uuid}/readiness")->assertNotFound();
        $this->actingAs($investor)->get("/investors/startups/{$listed->uuid}/diligence")->assertNotFound();
        $this->actingAs($investor)->post("/investors/startups/{$listed->uuid}/document-requests", ['title' => 'Cap table', 'category' => 'legal'])->assertNotFound();
        $this->actingAs($investor)->post("/investors/startups/{$listed->uuid}/diligence", ['template' => 'equity_basic'])->assertNotFound();
        $this->actingAs($investor)->post("/investors/startups/{$listed->uuid}/watch")->assertSessionHasNoErrors();

        foreach (['readiness', 'diligence'] as $page) {
            $this->actingAs($investor)->get("/investors/startups/{$hidden->uuid}/$page")->assertNotFound();
        }
        $this->actingAs($investor)->post("/investors/startups/{$hidden->uuid}/watch")->assertNotFound();
        $this->actingAs($investor)->post("/investors/startups/{$hidden->uuid}/pipeline", ['stage' => 'sourced'])->assertNotFound();
        $this->actingAs($investor)->post("/investors/startups/{$hidden->uuid}/match-feedback", ['rating' => 'useful'])->assertNotFound();

        $this->actingAs($investor)->get('/investors/pipeline')->assertOk()->assertSee('Blue Water')->assertDontSee('Quiet Hill');

        // A second verified investor with no connection sees nothing of the first pair.
        $other = $this->investor();
        $this->actingAs($other)->get("/investors/startups/{$startup->uuid}/readiness")->assertNotFound();
        $this->actingAs($other)->get("/investors/startups/{$startup->uuid}/diligence")->assertNotFound();

        // Unverified investors and other members get 404 on every investor action.
        $pending = $this->investor('pending');
        $this->actingAs($pending)->get('/investors/pipeline')->assertRedirect(route('investor.apply'));
        $this->actingAs($pending)->post("/investors/startups/{$listed->uuid}/watch")->assertNotFound();
        $this->actingAs($founder)->get("/investors/startups/{$startup->uuid}/readiness")->assertNotFound();
    }

    public function test_connection_without_a_share_shows_diligence_but_not_readiness(): void
    {
        [, $startup, $investor] = $this->connectedPair();

        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/diligence")->assertOk()->assertSee('Due diligence');
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/readiness")->assertNotFound();
        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}")->assertOk()
            ->assertSee('The founders have not shared readiness information with you.')->assertDontSee('TRACTION-SECRET');
    }

    // ----- Pipeline ---------------------------------------------------------

    public function test_pipeline_transitions_are_recorded_audited_and_private(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();

        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/pipeline", ['stage' => 'sourced'])->assertSessionHasNoErrors();
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/pipeline", ['stage' => 'diligence'])->assertSessionHasNoErrors();
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/pipeline", ['stage' => 'diligence'])->assertSessionHasErrors('stage');
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/pipeline", ['stage' => 'bogus'])->assertSessionHasErrors('stage');

        $this->assertSame('diligence', PipelineItem::query()->sole()->stage);
        $events = PipelineEvent::query()->orderBy('id')->get();
        $this->assertSame([[null, 'sourced'], ['sourced', 'diligence']], $events->map(fn ($e) => [$e->from_stage, $e->to_stage])->all());
        $this->assertSame(2, AuditLog::query()->where('action', 'capital.pipeline_transition')->count());

        $this->actingAs($investor)->get('/investors/pipeline')->assertOk()
            ->assertSee('Green Fields')->assertSee('Stage history')->assertSee('Diligence');

        // Private to the investor: not in the founders' activity log.
        $this->assertSame(2, Interaction::query()->where('type', 'pipeline_transition')->count());
        $this->assertSame(0, Interaction::query()->where('type', 'pipeline_transition')->visibleToStartup()->count());
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital/activity")->assertOk()->assertDontSee('Pipeline stage changed');

        // Events are append-only.
        $this->expectException(LogicException::class);
        $events->first()->update(['to_stage' => 'closed']);
    }

    public function test_watch_and_unwatch(): void
    {
        [, $startup, $investor] = $this->connectedPair();

        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/watch")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('investor_watchlist', ['investor_user_id' => $investor->id, 'startup_uuid' => $startup->uuid]);
        $this->actingAs($investor)->get('/investors/pipeline')->assertOk()->assertSee('Green Fields');

        $this->actingAs($investor)->delete("/investors/startups/{$startup->uuid}/watch")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('investor_watchlist', ['investor_user_id' => $investor->id]);
    }

    // ----- Due diligence ----------------------------------------------------

    public function test_diligence_item_lifecycle(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();
        $file = $this->upload($founder, $startup);

        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/diligence", ['template' => 'equity_basic', 'due_date' => now()->addWeek()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/diligence", ['items' => [
            ['title' => 'Board minutes', 'category' => 'corporate'], ['title' => '', 'category' => 'other'],
        ]])->assertSessionHasNoErrors();
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/diligence", ['items' => [['title' => '']]])->assertSessionHasErrors('items');
        $this->assertSame(7, DiligenceItem::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'capital.diligence_requested']);

        $item = DiligenceItem::query()->where('title', 'Board minutes')->firstOrFail();
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital")->assertOk()->assertSee('Board minutes');

        // Investors cannot mark items provided; viewers and outsiders cannot touch them.
        $this->actingAs($investor)->put("/investors/diligence/{$item->id}", ['status' => 'provided'])->assertSessionHasErrors('diligence');
        $viewer = $this->member($startup, 'viewer');
        $this->actingAs($viewer)->put("/workspace/startups/{$startup->uuid}/capital/diligence/{$item->id}", ['status' => 'provided'])->assertForbidden();
        $this->actingAs($this->investor())->put("/investors/diligence/{$item->id}", ['status' => 'accepted'])->assertNotFound();

        // Founder provides, linking a data-room document.
        $this->actingAs($founder)->put("/workspace/startups/{$startup->uuid}/capital/diligence/{$item->id}", [
            'status' => 'provided', 'data_room_file_uuid' => $file->uuid, 'notes' => 'Signed copy attached.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('provided', $item->fresh()->status);
        $this->assertSame($file->uuid, $item->fresh()->data_room_file_uuid);

        // Founders cannot accept their own items.
        $this->actingAs($founder)->put("/workspace/startups/{$startup->uuid}/capital/diligence/{$item->id}", ['status' => 'accepted'])->assertSessionHasErrors('status');

        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/diligence")->assertOk()
            ->assertSee('Signed copy attached.')->assertSee('needs data-room access');
        $this->actingAs($investor)->put("/investors/diligence/{$item->id}", ['status' => 'accepted', 'notes' => 'Thanks.'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $item->fresh()->status);
        $this->assertSame(2, AuditLog::query()->where('action', 'capital.diligence_item_updated')->count());

        // Activity shows the diligence steps to the founders.
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital/activity")->assertOk()
            ->assertSee('Added a checklist item')->assertSee('Updated a checklist item');
    }

    public function test_document_request_lifecycle(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();
        $file = $this->upload($founder, $startup, 'Cap table 2026', 'legal');

        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/document-requests", ['title' => 'Cap table', 'category' => 'legal', 'message' => 'Latest please.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/document-requests", ['title' => 'Bank statements', 'category' => 'financials'])
            ->assertSessionHasNoErrors();
        [$first, $second] = DocumentRequest::query()->orderBy('id')->get()->all();

        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/capital")->assertOk()->assertSee('Latest please.');

        // Viewers cannot answer.
        $viewer = $this->member($startup, 'viewer');
        $this->actingAs($viewer)->post("/workspace/startups/{$startup->uuid}/capital/document-requests/{$first->id}/fulfil", ['data_room_file_uuid' => $file->uuid])->assertForbidden();
        $this->actingAs($viewer)->post("/workspace/startups/{$startup->uuid}/capital/document-requests/{$second->id}/decline")->assertForbidden();

        // Fulfil from the data room and share it for 30 days.
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/capital/document-requests/{$first->id}/fulfil", [
            'data_room_file_uuid' => $file->uuid, 'share_data_room' => 1, 'expires_at' => now()->addDays(30)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame('fulfilled', $first->fresh()->status);
        $this->assertTrue(DataRoomGrant::query()->active()->where('investor_user_id', $investor->id)->exists());
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/capital/document-requests/{$first->id}/fulfil", ['data_room_file_uuid' => $file->uuid])
            ->assertSessionHasErrors('document');

        // Decline the other with a note.
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/capital/document-requests/{$second->id}/decline", ['response_note' => 'Not before term sheet.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('declined', $second->fresh()->status);

        $this->actingAs($investor)->get("/investors/startups/{$startup->uuid}/diligence")->assertOk()
            ->assertSee(route('dataroom.download', $file), false)->assertSee('Not before term sheet.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'capital.document_fulfilled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'capital.document_declined']);

        // A request of another startup cannot be answered through this one.
        [$otherFounder, $other] = $this->founderWithStartup('Blue Water');
        $this->actingAs($otherFounder)->post("/workspace/startups/{$other->uuid}/capital/document-requests/{$second->id}/decline")->assertNotFound();
    }

    public function test_fulfilling_with_an_upload_adds_it_to_the_data_room(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/document-requests", ['title' => 'Pitch deck', 'category' => 'pitch_deck'])->assertSessionHasNoErrors();
        $request = DocumentRequest::query()->sole();

        $pdf = UploadedFile::fake()->createWithContent('deck.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/capital/document-requests/{$request->id}/fulfil", ['file' => $pdf])->assertSessionHasNoErrors();

        $file = DataRoomFile::query()->where('startup_uuid', $startup->uuid)->sole();
        $this->assertSame($file->uuid, $request->fresh()->data_room_file_uuid);
        $this->assertFalse(DataRoomGrant::query()->where('investor_user_id', $investor->id)->exists());
    }

    // ----- Match feedback ----------------------------------------------------

    public function test_match_feedback_from_both_sides(): void
    {
        [$founder, $startup, $investor] = $this->connectedPair();

        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/capital/match-feedback", ['investor_user_id' => $investor->id, 'rating' => 'useful'])
            ->assertSessionHasNoErrors();
        $this->actingAs($investor)->post("/investors/startups/{$startup->uuid}/match-feedback", ['rating' => 'not_useful', 'reason' => 'Too early.'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('investor_match_feedback', ['side' => 'founder', 'rating' => 'useful']);
        $this->assertDatabaseHas('investor_match_feedback', ['side' => 'investor', 'rating' => 'not_useful']);

        // Founders cannot rate users who are not verified investors.
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/capital/match-feedback", ['investor_user_id' => $founder->id, 'rating' => 'useful'])
            ->assertSessionHasErrors('feedback');
    }
}
