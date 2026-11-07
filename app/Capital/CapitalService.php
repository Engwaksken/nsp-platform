<?php

namespace App\Capital;

use App\Communication\Notifier;
use App\Funding\FundingService;
use App\Mail\FundingNotice;
use App\Models\Capital\DiligenceItem;
use App\Models\Capital\DocumentRequest;
use App\Models\Capital\FundingProfile;
use App\Models\Capital\Interaction;
use App\Models\Capital\MatchFeedback;
use App\Models\Capital\PipelineEvent;
use App\Models\Capital\PipelineItem;
use App\Models\Capital\ShareGrant;
use App\Models\Capital\WatchlistEntry;
use App\Models\Funding\DataRoomFile;
use App\Models\Funding\DataRoomGrant;
use App\Models\Funding\FundingRequest;
use App\Models\Funding\Introduction;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * M08 Capital Exchange, built on the Phase 5b funding module.
 *
 * Rules:
 * - founders decide what each investor sees (share scopes), default nothing;
 *   scopes expire and can be revoked, and both take effect immediately;
 * - data-room downloads still need an active data-room grant (FundingService);
 * - diligence and document requests exist only inside an accepted introduction;
 * - every pipeline transition is stored append-only and audited;
 * - matching and readiness are discovery aids: no eligibility decision, no
 *   finance approval, no automated investment decision, no fund transfers.
 */
class CapitalService
{
    public function __construct(private readonly FundingService $funding, private readonly VentureSignals $signals) {}

    public static function scopes(): array
    {
        return config('capital.share_scopes', []);
    }

    // ----- Activity --------------------------------------------------------

    /** Record an interaction. With $oncePerDay, repeated views on the same day are counted once. */
    public function record(string $startupUuid, int $investorUserId, string $type, ?User $actor, array $meta = [], bool $visibleToStartup = true, bool $oncePerDay = false): ?Interaction
    {
        if ($oncePerDay && Interaction::query()->where('startup_uuid', $startupUuid)->where('investor_user_id', $investorUserId)
            ->where('type', $type)->where('created_at', '>=', now()->startOfDay())->exists()) {
            return null;
        }

        return Interaction::query()->create([
            'startup_uuid' => $startupUuid, 'investor_user_id' => $investorUserId, 'actor_user_id' => $actor?->id,
            'type' => $type, 'visible_to_startup' => $visibleToStartup, 'meta' => $meta ?: null,
        ]);
    }

    // ----- Funding profile -------------------------------------------------

    public function profile(Startup $startup): ?FundingProfile
    {
        return FundingProfile::query()->where('startup_uuid', $startup->uuid)->first();
    }

    public function saveProfile(Startup $startup, User $user, array $data): FundingProfile
    {
        $this->editorOrFail($startup, $user);
        $profile = FundingProfile::query()->firstOrNew(['startup_uuid' => $startup->uuid]);
        $profile->fill($data + ['updated_by' => $user->id])->save();
        Audit::log('capital.profile_saved', $startup, ['round_stage' => $profile->round_stage, 'instruments' => $profile->instruments_sought]);

        return $profile;
    }

    // ----- Granular sharing ------------------------------------------------

    public function activeShare(Startup $startup, User $investor): ?ShareGrant
    {
        return ShareGrant::query()->active()->where('startup_uuid', $startup->uuid)->where('investor_user_id', $investor->id)->latest('id')->first();
    }

    /** @return list<string> scopes the investor holds right now */
    public function scopesFor(Startup $startup, User $investor): array
    {
        if (! $this->funding->investorProfile($investor)) {
            return [];
        }

        return array_values($this->activeShare($startup, $investor)?->scopes ?? []);
    }

    public function grantScopes(Startup $startup, User $founder, User $investor, array $scopes, ?string $expiresAt): ShareGrant
    {
        $this->editorOrFail($startup, $founder);
        if (! $this->funding->investorProfile($investor)) {
            throw new RegistryException('You can only share with Hive-verified investors.');
        }
        if (! $this->funding->isConnected($startup, $investor)) {
            throw new RegistryException('Connect with the investor first, then choose what to share.');
        }
        $scopes = array_values(array_intersect(array_keys(self::scopes()), $scopes));
        if ($scopes === []) {
            throw new RegistryException('Choose at least one thing to share, or remove access instead.');
        }

        $grant = DB::transaction(function () use ($startup, $founder, $investor, $scopes, $expiresAt) {
            ShareGrant::query()->active()->where('startup_uuid', $startup->uuid)->where('investor_user_id', $investor->id)
                ->update(['revoked_at' => now(), 'revoked_by' => $founder->id]);

            return ShareGrant::query()->create([
                'startup_uuid' => $startup->uuid, 'investor_user_id' => $investor->id, 'scopes' => $scopes, 'granted_by' => $founder->id,
                'expires_at' => $expiresAt ? Carbon::parse($expiresAt)->endOfDay() : now()->addDays((int) config('capital.share_default_days', 90)),
            ]);
        });
        Audit::log('capital.scopes_granted', $startup, ['investor_user_id' => $investor->id, 'scopes' => $scopes, 'expires_at' => (string) $grant->expires_at]);
        $this->record($startup->uuid, $investor->id, 'scopes_granted', $founder, ['scopes' => $scopes]);
        $this->notify($investor, $startup->display_name.' shared information with you', [
            'A startup you are connected with shared part of its investment readiness information with you on NSP until '.$grant->expires_at->format('j F Y').'.',
        ], route('investor.capital.readiness', $startup));

        return $grant;
    }

    public function revokeScopes(Startup $startup, User $founder, ShareGrant $grant): void
    {
        $this->editorOrFail($startup, $founder);
        if ($grant->startup_uuid !== $startup->uuid) {
            throw new RegistryException('You cannot change this.');
        }
        $grant->update(['revoked_at' => now(), 'revoked_by' => $founder->id]);
        Audit::log('capital.scopes_revoked', $startup, ['investor_user_id' => $grant->investor_user_id]);
        $this->record($startup->uuid, $grant->investor_user_id, 'scopes_revoked', $founder);
    }

    // ----- Watchlist and pipeline (private to the investor) ----------------

    public function watch(User $investor, Startup $startup): void
    {
        if (! $this->funding->investorMaySee($investor, $startup)) {
            throw new RegistryException('This startup is not open to investors right now.');
        }
        $entry = WatchlistEntry::query()->firstOrCreate(['investor_user_id' => $investor->id, 'startup_uuid' => $startup->uuid]);
        if ($entry->wasRecentlyCreated) {
            $this->record($startup->uuid, $investor->id, 'watchlisted', $investor, [], visibleToStartup: false);
        }
    }

    public function unwatch(User $investor, Startup $startup): void
    {
        WatchlistEntry::query()->where('investor_user_id', $investor->id)->where('startup_uuid', $startup->uuid)->delete();
    }

    public function transition(User $investor, Startup $startup, string $stage): PipelineItem
    {
        if (! array_key_exists($stage, PipelineItem::stages())) {
            throw new RegistryException('Unknown pipeline stage.');
        }
        if (! $this->funding->investorProfile($investor)) {
            throw new RegistryException('Only verified investors keep a pipeline.');
        }
        $item = PipelineItem::query()->where('investor_user_id', $investor->id)->where('startup_uuid', $startup->uuid)->first();
        if (! $item && ! $this->funding->investorMaySee($investor, $startup)) {
            throw new RegistryException('This startup is not open to investors right now.');
        }
        if ($item?->stage === $stage) {
            throw new RegistryException('The startup is already at this stage.');
        }
        $from = $item?->stage;

        $item = DB::transaction(function () use ($item, $investor, $startup, $stage, $from) {
            $item ??= new PipelineItem(['investor_user_id' => $investor->id, 'startup_uuid' => $startup->uuid]);
            $item->fill(['stage' => $stage, 'stage_changed_at' => now()])->save();
            PipelineEvent::query()->create([
                'pipeline_item_id' => $item->id, 'investor_user_id' => $investor->id, 'startup_uuid' => $startup->uuid,
                'from_stage' => $from, 'to_stage' => $stage, 'actor_user_id' => $investor->id,
            ]);

            return $item;
        });
        Audit::log('capital.pipeline_transition', $item, ['from' => $from, 'to' => $stage, 'startup_uuid' => $startup->uuid]);
        $this->record($startup->uuid, $investor->id, 'pipeline_transition', $investor, ['from' => $from, 'to' => $stage],
            visibleToStartup: (bool) config('capital.share_pipeline_with_startup', false));

        return $item;
    }

    /** @return array<string, int> stage key => number of startups the investor has at that stage (every configured stage present) */
    public function funnel(User $investor): array
    {
        $counts = PipelineItem::query()->where('investor_user_id', $investor->id)
            ->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage');

        return collect(PipelineItem::stages())->map(fn ($label, $key) => (int) ($counts[$key] ?? 0))->all();
    }

    // ----- Due diligence ---------------------------------------------------

    /** The accepted introduction between this startup and investor, or null. */
    public function connection(Startup $startup, User $investor): ?Introduction
    {
        return Introduction::query()->where('startup_uuid', $startup->uuid)->where('investor_user_id', $investor->id)
            ->where('status', 'accepted')->latest('decided_at')->first();
    }

    /** @param list<array{title: string, category: string, due_date?: ?string}> $items */
    public function addDiligenceItems(Introduction $introduction, User $investor, array $items): Collection
    {
        $this->investorSideOrFail($introduction, $investor);
        $created = collect($items)->map(fn ($item) => DiligenceItem::query()->create([
            'introduction_uuid' => $introduction->uuid, 'startup_uuid' => $introduction->startup_uuid, 'investor_user_id' => $investor->id,
            'title' => mb_substr($item['title'], 0, 200), 'category' => $item['category'], 'status' => 'requested',
            'due_date' => $item['due_date'] ?? null, 'created_by' => $investor->id, 'updated_by' => $investor->id,
        ]));
        Audit::log('capital.diligence_requested', $introduction, ['items' => $created->count(), 'titles' => $created->pluck('title')->all()]);
        $this->record($introduction->startup_uuid, $investor->id, 'diligence_item_added', $investor, ['count' => $created->count()]);
        $this->notifyEditors($introduction->startup_uuid, 'An investor added checklist items', [
            'A connected investor added '.$created->count().' due-diligence item(s). Review them on NSP.',
        ]);

        return $created;
    }

    public function applyTemplate(Introduction $introduction, User $investor, string $template, ?string $dueDate): Collection
    {
        $items = config('capital.diligence_templates.'.$template.'.items');
        if (! is_array($items)) {
            throw new RegistryException('Unknown checklist template.');
        }

        return $this->addDiligenceItems($introduction, $investor, array_map(fn ($item) => $item + ['due_date' => $dueDate], $items));
    }

    /**
     * Founders mark items provided (optionally linking a data-room file); investors accept, re-request or mark not applicable.
     * Notes are shared by both sides.
     */
    public function updateDiligenceItem(DiligenceItem $item, User $user, array $data): DiligenceItem
    {
        $side = $this->sideOf($item->startup_uuid, $item->investor_user_id, $user);
        if ($side === null || ! $this->connectionStillOpen($item->introduction_uuid)) {
            throw new RegistryException('You cannot change this item.');
        }
        $status = $data['status'] ?? $item->status;
        $allowed = $side === 'founder' ? ['requested', 'provided'] : ['requested', 'provided', 'accepted', 'not_applicable'];
        if ($status !== $item->status && (! in_array($status, $allowed, true) || ($side === 'investor' && $status === 'provided'))) {
            throw new RegistryException($side === 'founder' ? 'Founders can mark an item as provided. The investor accepts it.' : 'Investors cannot mark an item as provided.');
        }
        $fileUuid = $data['data_room_file_uuid'] ?? $item->data_room_file_uuid;
        if ($side === 'founder' && $fileUuid && ! DataRoomFile::query()->where('uuid', $fileUuid)->where('startup_uuid', $item->startup_uuid)->exists()) {
            throw new RegistryException('Choose a document from your own data room.');
        }
        $from = $item->status;
        $item->status = $status;
        $item->updated_by = $user->id;
        if (array_key_exists('notes', $data)) {
            $item->notes = $data['notes'] !== null ? mb_substr((string) $data['notes'], 0, 3000) : null;
        }
        if ($side === 'founder') {
            $item->data_room_file_uuid = $fileUuid ?: null;
        }
        if ($side === 'investor' && array_key_exists('due_date', $data)) {
            $item->due_date = $data['due_date'];
        }
        if ($item->isDirty('due_date')) {
            $item->reminded_at = null;
        }
        $item->save();
        Audit::log('capital.diligence_item_updated', $item, ['from' => $from, 'to' => $item->status, 'by' => $side]);
        $this->record($item->startup_uuid, $item->investor_user_id, 'diligence_item_updated', $user, ['from' => $from, 'to' => $item->status, 'by' => $side]);

        return $item;
    }

    // ----- Document requests ----------------------------------------------

    public function requestDocument(Introduction $introduction, User $investor, array $data): DocumentRequest
    {
        $this->investorSideOrFail($introduction, $investor);
        $request = DocumentRequest::query()->create([
            'introduction_uuid' => $introduction->uuid, 'startup_uuid' => $introduction->startup_uuid, 'investor_user_id' => $investor->id,
            'title' => mb_substr($data['title'], 0, 200), 'category' => $data['category'], 'message' => isset($data['message']) ? mb_substr($data['message'], 0, 2000) : null,
            'status' => 'requested',
        ]);
        Audit::log('capital.document_requested', $request, ['title' => $request->title, 'category' => $request->category]);
        $this->record($introduction->startup_uuid, $investor->id, 'document_requested', $investor, ['title' => $request->title]);
        $this->notifyEditors($introduction->startup_uuid, 'An investor requested a document', [
            'A connected investor asked for a document. Add it to your data room or decline on NSP.',
        ]);

        return $request;
    }

    /** The founders add the requested document to the data room; optionally share the data room so the investor can open it. */
    public function fulfilDocument(DocumentRequest $request, User $founder, DataRoomFile $file, bool $shareDataRoom, ?string $expiresAt = null): DocumentRequest
    {
        $startup = Startup::query()->findOrFail($request->startup_uuid);
        $this->editorOrFail($startup, $founder);
        if ($request->status !== 'requested' || $file->startup_uuid !== $startup->uuid) {
            throw new RegistryException('This request was already answered.');
        }
        $investor = User::query()->findOrFail($request->investor_user_id);
        if ($shareDataRoom && ! $this->funding->activeGrant($startup, $investor)) {
            $this->funding->grant($startup, $founder, $investor, $expiresAt);
        }
        $request->update(['status' => 'fulfilled', 'data_room_file_uuid' => $file->uuid, 'responded_by' => $founder->id, 'responded_at' => now()]);
        Audit::log('capital.document_fulfilled', $request, ['title' => $file->title, 'shared_data_room' => $shareDataRoom]);
        $this->record($startup->uuid, $investor->id, 'document_fulfilled', $founder, ['title' => $request->title]);
        $this->notify($investor, $startup->display_name.' answered your document request', [
            'The document you asked for was added to the startup data room.'.($this->funding->activeGrant($startup, $investor) ? ' You can open it while your data-room access lasts.' : ' The startup has not shared its data room with you yet.'),
        ], route('investor.capital.diligence', $startup));

        return $request;
    }

    public function declineDocument(DocumentRequest $request, User $founder, ?string $note): DocumentRequest
    {
        $startup = Startup::query()->findOrFail($request->startup_uuid);
        $this->editorOrFail($startup, $founder);
        if ($request->status !== 'requested') {
            throw new RegistryException('This request was already answered.');
        }
        $request->update(['status' => 'declined', 'response_note' => $note ? mb_substr($note, 0, 1000) : null, 'responded_by' => $founder->id, 'responded_at' => now()]);
        Audit::log('capital.document_declined', $request, ['title' => $request->title]);
        $this->record($startup->uuid, $request->investor_user_id, 'document_declined', $founder, ['title' => $request->title]);
        $this->notify(User::query()->find($request->investor_user_id), $startup->display_name.' declined a document request', [
            'The startup declined one of your document requests. See their note on NSP.',
        ], route('investor.capital.diligence', $startup));

        return $request;
    }

    // ----- Matching --------------------------------------------------------

    /**
     * VGI summaries for the startups where this investor holds the vgi_summary scope.
     *
     * @param  iterable<Startup>  $startups
     * @return array<string, array|null> startup uuid => VGI summary (only keys with the scope)
     */
    public function sharedVgiFor(User $investor, iterable $startups): array
    {
        $startups = collect($startups)->keyBy('uuid');
        if ($startups->isEmpty() || ! $this->funding->investorProfile($investor)) {
            return [];
        }
        $grants = ShareGrant::query()->active()->where('investor_user_id', $investor->id)->whereIn('startup_uuid', $startups->keys())->get();
        $result = [];
        foreach ($grants as $grant) {
            if ($grant->allows('vgi_summary') && ($startup = $startups->get($grant->startup_uuid))) {
                $result[$grant->startup_uuid] = $this->signals->vgi($startup);
            }
        }

        return $result;
    }

    public function feedback(Startup $startup, User $investor, string $side, User $by, string $rating, ?string $reason): MatchFeedback
    {
        if (! array_key_exists($rating, MatchFeedback::RATINGS) || ! in_array($side, ['founder', 'investor'], true)) {
            throw new RegistryException('Unknown feedback.');
        }
        $feedback = MatchFeedback::query()->updateOrCreate(
            ['startup_uuid' => $startup->uuid, 'investor_user_id' => $investor->id, 'side' => $side],
            ['given_by' => $by->id, 'rating' => $rating, 'reason' => $reason ? mb_substr($reason, 0, 500) : null],
        );
        Audit::log('capital.match_feedback', $startup, ['investor_user_id' => $investor->id, 'side' => $side, 'rating' => $rating]);
        $this->record($startup->uuid, $investor->id, 'match_feedback', $by, ['side' => $side, 'rating' => $rating], visibleToStartup: $side === 'founder');

        return $feedback;
    }

    // ----- Readiness report ------------------------------------------------

    /**
     * Investment readiness summary. With $scopes = null it is the founder version (everything);
     * otherwise only the given scopes are filled and the rest is null.
     */
    public function readiness(Startup $startup, ?array $scopes = null, bool $dataRoomAccess = true): array
    {
        $all = $scopes === null;
        $may = fn (string $scope) => $all || in_array($scope, $scopes, true);
        $profile = $this->profile($startup);
        $request = $this->funding->openRequest($startup);

        $files = DataRoomFile::query()->where('startup_uuid', $startup->uuid)->get(['uuid', 'title', 'category']);
        $checklist = collect(config('capital.data_room_checklist', []))->mapWithKeys(fn ($category) => [
            $category => ['label' => DataRoomFile::CATEGORIES[$category] ?? $category, 'present' => $files->where('category', $category)->isNotEmpty()],
        ]);
        $completeness = $checklist->isEmpty() ? 0 : (int) round($checklist->where('present', true)->count() / $checklist->count() * 100);

        $diligence = null;
        if ($all) {
            $diligence = DiligenceItem::query()->where('startup_uuid', $startup->uuid)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)->all();
        }

        return [
            'request' => $request ? [
                'amount' => $request->amountLabel(),
                'instruments' => collect($request->instruments)->map(fn ($i) => config('capital.instruments.'.$i, $i))->values()->all(),
                'pitch' => $request->pitch,
                'use_of_funds' => $request->use_of_funds,
            ] : null,
            'round_stage' => $profile?->roundLabel(),
            'vgi' => $may('vgi_summary') ? ['data' => $this->signals->vgi($startup)] : null,
            'builder' => $may('builder_progress') ? ['data' => $this->signals->builderProgress($startup)] : null,
            'traction' => $may('traction') ? ['text' => $profile?->traction_summary] : null,
            'financials' => $may('financials') ? [
                'text' => $profile?->financial_summary,
                'breakdown' => collect($profile?->use_of_funds_breakdown ?? [])->filter(fn ($p) => (int) $p > 0)
                    ->mapWithKeys(fn ($p, $key) => [config('capital.use_of_funds_categories.'.$key, $key) => (int) $p])->all(),
                'documents' => $files->whereIn('category', ['financials', 'projections'])->pluck('title')->values()->all(),
            ] : null,
            'data_room' => ($all || $dataRoomAccess) ? ['checklist' => $checklist->all(), 'completeness' => $completeness, 'files' => $files->count()] : null,
            'diligence' => $diligence,
            'generated_at' => now(),
        ];
    }

    // ----- Helpers ---------------------------------------------------------

    /** "founder" for an editor of the startup, "investor" for the investor on the item, else null. */
    public function sideOf(string $startupUuid, int $investorUserId, User $user): ?string
    {
        if ($user->id === $investorUserId && $this->funding->investorProfile($user)) {
            return 'investor';
        }
        $startup = Startup::query()->find($startupUuid);

        return $startup && $startup->status === 'active' && $startup->membershipOf($user)?->canEdit() ? 'founder' : null;
    }

    private function connectionStillOpen(string $introductionUuid): bool
    {
        return Introduction::query()->where('uuid', $introductionUuid)->where('status', 'accepted')->exists();
    }

    private function investorSideOrFail(Introduction $introduction, User $investor): void
    {
        if ($introduction->investor_user_id !== $investor->id || $introduction->status !== 'accepted' || ! $this->funding->investorProfile($investor)) {
            throw new RegistryException('You need an accepted introduction with this startup first.');
        }
    }

    private function editorOrFail(Startup $startup, User $user): StartupMembership
    {
        $membership = $startup->membershipOf($user);
        if (! $membership?->canEdit()) {
            throw new RegistryException('Only owners, founders and admins can change this.');
        }

        return $membership;
    }

    private function notifyEditors(string $startupUuid, string $subject, array $lines): void
    {
        foreach (StartupMembership::query()->with('user')->where('startup_uuid', $startupUuid)->where('status', 'active')->whereIn('role', StartupMembership::EDITOR_ROLES)->get() as $member) {
            $this->notify($member->user, $subject, $lines, route('workspace.capital.index', $startupUuid));
        }
    }

    public function notify(?User $user, string $subject, array $lines, string $url): void
    {
        app(Notifier::class)->notify($user, 'funding', $subject, array_values($lines), $url, new FundingNotice($subject, $lines, $url));
    }

    /** Data-room grant helper for views. */
    public function dataRoomGrant(Startup $startup, User $investor): ?DataRoomGrant
    {
        return $this->funding->activeGrant($startup, $investor);
    }

    public function openRequest(Startup $startup): ?FundingRequest
    {
        return $this->funding->openRequest($startup);
    }
}
