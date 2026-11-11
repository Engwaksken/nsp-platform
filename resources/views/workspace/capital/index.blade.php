@extends('layouts.workspace', ['pageTitle' => 'Capital Exchange · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    @php
        $canEdit = $membership->canEdit();
        $scopes = \App\Capital\CapitalService::scopes();
        $itemTone = ['requested' => 'draft', 'provided' => 'scheduled', 'accepted' => 'on', 'not_applicable' => 'off'];
        $requestTone = ['requested' => 'draft', 'fulfilled' => 'on', 'declined' => 'off'];
        $investors = $connections->unique('investor_user_id')->values();
        $openItems = $items->flatten()->where('status', 'requested')->count();
        $waitingRequests = $documentRequests->where('status', 'requested')->count();
        $maxShareDate = now()->addDays((int) config('capital.share_max_days', 365))->toDateString();
        $fileOptions = $files->mapWithKeys(fn ($f) => [$f->uuid => $f->title.' ('.(\App\Models\Funding\DataRoomFile::CATEGORIES[$f->category] ?? $f->category).')'])->all();
    @endphp

    <div class="container section">
        <p><a href="{{ route('workspace.funding', $startup) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Fundraising</a></p>
        <div class="page-head">
            <div>
                <h1>Capital Exchange · {{ $startup->display_name }}</h1>
                <p>Decide what each connected investor sees, answer their due-diligence checklists and document requests, and check how ready you are.</p>
            </div>
            <div class="capital-actions">
                <a class="btn btn-secondary" href="{{ route('workspace.capital.readiness', $startup) }}"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Readiness report</a>
                <a class="btn btn-secondary" href="{{ route('workspace.capital.activity', $startup) }}"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Investor activity</a>
            </div>
        </div>
        @include('partials.flash')
        <p class="hint">Investors see nothing from this page until you share it. Sharing ends on the date you choose or when you remove it, straight away. NSP does not arrange funding or make investment decisions.</p>

        @include('partials.stats', ['label' => 'Capital Exchange at a glance', 'stats' => [
            ['icon' => 'fa-flag', 'tone' => 'amber', 'label' => 'Round', 'value' => $profile?->roundLabel() ?? 'Not set', 'note' => $open ? 'Raising '.$open->amountLabel() : 'No open request'],
            ['icon' => 'fa-handshake', 'tone' => 'green', 'label' => 'Connected investors', 'value' => $investors->count()],
            ['icon' => 'fa-share-nodes', 'tone' => 'blue', 'label' => 'Investors you share with', 'value' => $shares->count()],
            ['icon' => 'fa-list-check', 'tone' => 'purple', 'label' => 'Checklist items to answer', 'value' => $openItems, 'note' => $waitingRequests.' document request(s) waiting'],
        ]])

        <div data-tabs="Capital Exchange sections" data-tabs-key="capital">

        {{-- 1. Funding profile --}}
        <section class="panel" aria-labelledby="profile-heading" data-tab="Funding profile" data-tab-icon="fa-flag">
            <h2 id="profile-heading">Funding profile</h2>
            <p class="hint">Round and instruments help investors find you. The traction and financial summaries stay private until you share them with an investor.</p>
            @if ($canEdit)
                <form method="post" action="{{ route('workspace.capital.profile', $startup) }}">
                    @csrf @method('PUT')
                    @include('partials.field', ['name' => 'round_stage', 'label' => 'Round you are raising', 'type' => 'select', 'placeholder' => 'Not set', 'value' => $profile?->round_stage, 'options' => config('capital.round_stages')])
                    @include('partials.checks', ['name' => 'instruments_sought', 'label' => 'Instruments you are seeking', 'options' => config('capital.instruments'), 'selected' => $profile?->instruments_sought ?? []])
                    <fieldset class="form-group">
                        <legend class="label">Use of funds (percent of the amount raised)</legend>
                        <p class="hint">Leave a category empty if it does not apply. The total cannot be more than 100.</p>
                        @error('use_of_funds_breakdown')<p class="error">{{ $message }}</p>@enderror
                        <div class="capital-split">
                            @foreach (config('capital.use_of_funds_categories') as $key => $label)
                                @include('partials.field', ['name' => 'use_of_funds_breakdown['.$key.']', 'id' => 'uof-'.$key, 'label' => $label, 'type' => 'number', 'placeholder' => false,
                                    'value' => $profile?->use_of_funds_breakdown[$key] ?? null, 'attrs' => 'min="0" max="100" step="1" inputmode="numeric"'])
                            @endforeach
                        </div>
                    </fieldset>
                    @include('partials.field', ['name' => 'traction_summary', 'label' => 'Traction summary', 'type' => 'textarea', 'rows' => 4, 'value' => $profile?->traction_summary, 'hint' => 'Customers, revenue, growth and milestones. Shared only with investors you give the "Traction summary" scope.'])
                    @include('partials.field', ['name' => 'financial_summary', 'label' => 'Financial summary', 'type' => 'textarea', 'rows' => 4, 'value' => $profile?->financial_summary, 'hint' => 'Revenue, costs, runway and projections in brief. Shared only with investors you give the "Financial summary" scope.'])
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk" aria-hidden="true"></i> Save funding profile</button>
                </form>
            @else
                <dl class="detail-grid">
                    <div><dt>Round</dt><dd>{{ $profile?->roundLabel() ?? 'Not set' }}</dd></div>
                    <div><dt>Instruments</dt><dd>{{ collect($profile?->instruments_sought ?? [])->map(fn ($i) => config('capital.instruments.'.$i, $i))->join(', ') ?: 'Not set' }}</dd></div>
                    <div class="detail-wide"><dt>Use of funds</dt><dd>
                        {{ collect($profile?->use_of_funds_breakdown ?? [])->filter()->map(fn ($p, $k) => config('capital.use_of_funds_categories.'.$k, $k).': '.$p.'%')->join(', ') ?: 'Not set' }}</dd></div>
                </dl>
                <p class="meta">Only owners, founders and admins can change the funding profile.</p>
            @endif
        </section>

        {{-- 2. Sharing --}}
        <section class="panel" aria-labelledby="sharing-heading" data-tab="Sharing" data-tab-icon="fa-share-nodes" data-tab-count="{{ $shares->count() }}">
            <h2 id="sharing-heading">What each investor sees</h2>
            <p class="hint">Only investors you are connected with appear here. Nothing is shared by default. Choosing again replaces the earlier choice. Data-room documents are shared separately on the <a href="{{ route('workspace.funding', $startup) }}">Fundraising</a> page.</p>
            @if ($investors->isEmpty())
                <p class="empty">No connected investors yet. Accept an introduction on the Fundraising page first.</p>
            @else
                <x-card-grid label="Investors" min="280px" columns="3" remember="capital-sharing">
                    @foreach ($investors as $connection)
                        @php($share = $shares[$connection->investor_user_id] ?? null)
                        @php($name = $connection->investor?->name ?? 'Investor')
                        <x-collapsible-card id="share-{{ $connection->investor_user_id }}" icon="fa-user-tie" class="tone-blue" :title="$name"
                            :badge="$share ? 'Sharing' : 'Nothing shared'" :badge-tone="$share ? 'on' : 'off'"
                            :summary="$share ? 'Until '.$share->expires_at?->format('j M Y') : 'Connected since '.($connection->decided_at?->format('j M Y') ?? 'recently')"
                            :attention="$errors->has('share') && (string) old('investor_user_id') === (string) $connection->investor_user_id">
                            @if ($share)
                                <p><strong>Shared now:</strong></p>
                                <ul class="tags capital-tags">
                                    @foreach ($share->scopes ?? [] as $scope)<li class="tag">{{ $scopes[$scope]['label'] ?? $scope }}</li>@endforeach
                                </ul>
                                <p class="meta">Ends {{ $share->expires_at?->format('j F Y') ?? 'when you remove it' }}.</p>
                                @if ($canEdit)
                                    <form method="post" action="{{ route('workspace.capital.shares.destroy', [$startup, $share]) }}" data-confirm="Stop sharing with {{ $name }}? It takes effect straight away." class="inline-form">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-ban" aria-hidden="true"></i> Stop sharing<span class="visually-hidden"> with {{ $name }}</span></button>
                                    </form>
                                @endif
                            @else
                                <p class="meta">{{ $name }} sees only your public funding request.</p>
                            @endif
                            @if ($canEdit)
                                <form method="post" action="{{ route('workspace.capital.shares.store', $startup) }}">
                                    @csrf
                                    <input type="hidden" name="investor_user_id" value="{{ $connection->investor_user_id }}">
                                    <fieldset class="form-group">
                                        <legend class="label">{{ $share ? 'Change what you share' : 'Share with '.$name }}</legend>
                                        @foreach ($scopes as $key => $scope)
                                            <label class="checkbox"><input type="checkbox" name="scopes[]" value="{{ $key }}" @checked(in_array($key, $share?->scopes ?? [], true))>
                                                <span>{{ $scope['label'] }}<span class="meta capital-scope-hint">{{ $scope['hint'] }}</span></span></label>
                                        @endforeach
                                    </fieldset>
                                    @include('partials.field', ['name' => 'expires_at', 'id' => 'share-expires-'.$connection->investor_user_id, 'label' => 'Sharing ends on', 'type' => 'date',
                                        'value' => ($share?->expires_at ?? now()->addDays((int) config('capital.share_default_days', 90)))->toDateString(),
                                        'attrs' => 'min="'.now()->addDay()->toDateString().'" max="'.$maxShareDate.'"', 'hint' => 'Up to '.config('capital.share_max_days', 365).' days from today.'])
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-share-nodes" aria-hidden="true"></i> {{ $share ? 'Update sharing' : 'Share' }}</button>
                                </form>
                            @endif
                        </x-collapsible-card>
                    @endforeach
                </x-card-grid>
            @endif
        </section>

        {{-- 3. Due diligence --}}
        <section class="panel" aria-labelledby="diligence-heading" data-tab="Due diligence" data-tab-icon="fa-list-check" data-tab-count="{{ $openItems }}">
            <h2 id="diligence-heading">Due-diligence checklists</h2>
            <p class="hint">Connected investors add checklist items. Mark an item provided, and link a data-room document if you have one. The investor accepts it or asks again. Notes are seen by both sides.</p>
            @if ($items->isEmpty())
                <p class="empty">No investor has added checklist items yet.</p>
            @else
                <x-card-grid label="Checklists" min="300px" columns="2" remember="capital-diligence">
                    @foreach ($items as $investorId => $group)
                        @php($toAnswer = $group->where('status', 'requested')->count())
                        <x-collapsible-card id="diligence-{{ $investorId }}" icon="fa-list-check" class="tone-purple"
                            :title="$group->first()->investor?->name ?? 'Investor'"
                            :badge="$toAnswer ? $toAnswer.' to answer' : 'Up to date'" :badge-tone="$toAnswer ? 'draft' : 'on'"
                            :summary="$group->count().' item(s), '.$group->where('status', 'accepted')->count().' accepted'"
                            :attention="$canEdit && $toAnswer > 0">
                            @foreach ($group as $item)
                                <article class="card capital-item">
                                    <h4 class="capital-item-title">{{ $item->title }} <span class="badge badge-{{ $itemTone[$item->status] ?? 'off' }}">{{ $item->statusLabel() }}</span></h4>
                                    <p class="meta">{{ $item->categoryLabel() }} · Due {{ $item->due_date?->format('j M Y') ?? 'Not set' }}
                                        @if ($item->file) · <a href="{{ route('dataroom.download', $item->file) }}">{{ $item->file->title }}</a>@endif</p>
                                    @if ($item->notes)<p>{{ $item->notes }}</p>@endif
                                    @if ($canEdit && in_array($item->status, ['requested', 'provided'], true))
                                        <form method="post" action="{{ route('workspace.capital.diligence.update', [$startup, $item]) }}">
                                            @csrf @method('PUT')
                                            <div class="grid">
                                                @include('partials.field', ['name' => 'status', 'id' => 'item-status-'.$item->id, 'label' => 'Status', 'type' => 'select', 'value' => $item->status,
                                                    'options' => ['requested' => 'Still to provide', 'provided' => 'Provided']])
                                                @include('partials.field', ['name' => 'data_room_file_uuid', 'id' => 'item-file-'.$item->id, 'label' => 'Data-room document', 'type' => 'select', 'placeholder' => 'No document',
                                                    'value' => $item->data_room_file_uuid, 'options' => $fileOptions])
                                            </div>
                                            @include('partials.field', ['name' => 'notes', 'id' => 'item-notes-'.$item->id, 'label' => 'Note to the investor', 'type' => 'textarea', 'rows' => 2, 'value' => $item->notes, 'placeholder' => false])
                                            <button type="submit" class="btn btn-primary btn-sm">Save<span class="visually-hidden"> {{ $item->title }}</span></button>
                                        </form>
                                    @endif
                                </article>
                            @endforeach
                        </x-collapsible-card>
                    @endforeach
                </x-card-grid>
            @endif
        </section>

        {{-- 4. Document requests --}}
        <section class="panel" aria-labelledby="documents-heading" data-tab="Document requests" data-tab-icon="fa-file-circle-question" data-tab-count="{{ $waitingRequests }}">
            <h2 id="documents-heading">Document requests</h2>
            <p class="hint">Answer with a document already in your data room or upload a new one. The investor can open it only while they have data-room access, which you can give here.</p>
            @if ($documentRequests->isEmpty())
                <p class="empty">No document requests yet.</p>
            @else
                <x-card-grid label="Document requests" min="280px" columns="3" remember="capital-documents">
                    @foreach ($documentRequests as $documentRequest)
                        @php($waiting = $documentRequest->status === 'requested')
                        <x-collapsible-card id="document-{{ $documentRequest->id }}" icon="fa-file-circle-question" class="tone-amber" :title="$documentRequest->title"
                            :badge="\App\Models\Capital\DocumentRequest::STATUSES[$documentRequest->status] ?? $documentRequest->status" :badge-tone="$requestTone[$documentRequest->status] ?? 'off'"
                            :summary="($documentRequest->investor?->name ?? 'Investor').' · '.$documentRequest->created_at->format('j M Y')"
                            :attention="$canEdit && $waiting">
                            <p class="meta">Type: {{ \App\Models\Funding\DataRoomFile::CATEGORIES[$documentRequest->category] ?? $documentRequest->category }}</p>
                            @if ($documentRequest->message)<p>{{ $documentRequest->message }}</p>@endif
                            @if ($documentRequest->status === 'fulfilled')
                                <p>Answered with @if ($documentRequest->file)<a href="{{ route('dataroom.download', $documentRequest->file) }}">{{ $documentRequest->file->title }}</a>@else a document that was later deleted @endif
                                    on {{ $documentRequest->responded_at?->format('j M Y') }}.</p>
                            @elseif ($documentRequest->status === 'declined')
                                <p>Declined on {{ $documentRequest->responded_at?->format('j M Y') }}.@if ($documentRequest->response_note) Note: {{ $documentRequest->response_note }}@endif</p>
                            @elseif ($canEdit)
                                <form method="post" action="{{ route('workspace.capital.documents.fulfil', [$startup, $documentRequest]) }}" enctype="multipart/form-data">
                                    @csrf
                                    @if ($fileOptions)
                                        @include('partials.field', ['name' => 'data_room_file_uuid', 'id' => 'doc-file-'.$documentRequest->id, 'label' => 'Use a data-room document', 'type' => 'select', 'placeholder' => 'Upload a new file instead', 'options' => $fileOptions])
                                    @endif
                                    @include('partials.field', ['name' => 'file', 'id' => 'doc-upload-'.$documentRequest->id, 'label' => $fileOptions ? 'Or upload a new file' : 'Upload the document', 'type' => 'file',
                                        'attrs' => 'accept=".pdf,.pptx,.xlsx,.xls,.docx,.png,.jpg,.jpeg"', 'hint' => 'PDF, PowerPoint, Excel, Word or images. Up to 20 MB. It is added to your data room.'])
                                    <label class="checkbox"><input type="checkbox" name="share_data_room" value="1"> Also give {{ $documentRequest->investor?->name ?? 'the investor' }} data-room access</label>
                                    @include('partials.field', ['name' => 'expires_at', 'id' => 'doc-expires-'.$documentRequest->id, 'label' => 'Data-room access ends on', 'type' => 'date',
                                        'value' => now()->addDays(30)->toDateString(), 'attrs' => 'min="'.now()->addDay()->toDateString().'" max="'.now()->addDays(180)->toDateString().'"', 'hint' => 'Used only if you give access. Up to 180 days.'])
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-file-arrow-up" aria-hidden="true"></i> Send document</button>
                                </form>
                                <form method="post" action="{{ route('workspace.capital.documents.decline', [$startup, $documentRequest]) }}">
                                    @csrf
                                    @include('partials.field', ['name' => 'response_note', 'id' => 'doc-note-'.$documentRequest->id, 'label' => 'Reason for declining (optional)', 'type' => 'textarea', 'rows' => 2, 'placeholder' => false])
                                    <button type="submit" class="btn btn-secondary btn-sm">Decline request</button>
                                </form>
                            @else
                                <p class="meta">Waiting for an owner, founder or admin to answer.</p>
                            @endif
                        </x-collapsible-card>
                    @endforeach
                </x-card-grid>
            @endif
        </section>

        {{-- 5. Match feedback --}}
        <section class="panel" aria-labelledby="feedback-heading" data-tab="Match feedback" data-tab-icon="fa-thumbs-up">
            <h2 id="feedback-heading">Was this investor a good match?</h2>
            <p class="hint">Your answer helps NSP suggest better investors. The investor does not see it.</p>
            @if ($investors->isEmpty())
                <p class="empty">Feedback opens once you are connected with an investor.</p>
            @else
                <div class="table-wrap" tabindex="0" role="region" aria-label="Match feedback">
                    <table>
                        <thead><tr><th scope="col">Investor</th><th scope="col">Your feedback</th>@if ($canEdit)<th scope="col"><span class="visually-hidden">Change</span></th>@endif</tr></thead>
                        <tbody>
                        @foreach ($investors as $connection)
                            @php($given = $feedback[$connection->investor_user_id] ?? null)
                            <tr>
                                <td>{{ $connection->investor?->name ?? 'Investor' }}</td>
                                <td>{{ $given ? \App\Models\Capital\MatchFeedback::RATINGS[$given->rating] : 'Not given' }}@if ($given?->reason)<br><span class="meta">{{ $given->reason }}</span>@endif</td>
                                @if ($canEdit)
                                    <td>
                                        <form method="post" action="{{ route('workspace.capital.feedback', $startup) }}" class="capital-feedback">
                                            @csrf
                                            <input type="hidden" name="investor_user_id" value="{{ $connection->investor_user_id }}">
                                            <label class="visually-hidden" for="reason-{{ $connection->investor_user_id }}">Reason for {{ $connection->investor?->name }}</label>
                                            <input id="reason-{{ $connection->investor_user_id }}" type="text" name="reason" maxlength="500" placeholder="Reason (optional)" value="{{ $given?->reason }}">
                                            <button type="submit" name="rating" value="useful" class="btn btn-secondary btn-sm"><i class="fas fa-thumbs-up" aria-hidden="true"></i> Useful</button>
                                            <button type="submit" name="rating" value="not_useful" class="btn btn-secondary btn-sm"><i class="fas fa-thumbs-down" aria-hidden="true"></i> Not useful</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- 6. Funding opportunities --}}
        @if ($opportunities->isNotEmpty())
            <section class="panel" aria-labelledby="opps-heading" data-tab="Opportunities" data-tab-icon="fa-bullhorn" data-tab-count="{{ $opportunities->count() }}">
                <h2 id="opps-heading">Funding opportunities</h2>
                <p class="hint">Open grants, accelerators and investment calls on NSP. Check each call's own eligibility rules.</p>
                <ul class="capital-list">
                    @foreach ($opportunities as $opportunity)
                        <li>
                            @if (Route::has('opportunities.show'))<a href="{{ route('opportunities.show', $opportunity) }}">{{ $opportunity->title }}</a>@else{{ $opportunity->title }}@endif
                            <span class="meta">{{ $opportunity->organisation }}@if ($opportunity->deadline_at) · Closes {{ $opportunity->deadline_at->format('j M Y') }}@endif</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        </div>
    </div>
@endsection
