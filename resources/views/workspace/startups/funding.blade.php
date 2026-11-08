@extends('layouts.public', ['pageTitle' => 'Funding · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>Funding · {{ $startup->display_name }}</h1>
        @include('workspace.startups.nav')
        @include('partials.flash')
        <p class="hint">NSP helps you meet investors that Hive has verified. It does not promise or arrange funding. Investors only find you while you have an open funding request <strong>and</strong> “{{ config('nsp.registry.consent_purposes.investor_listing.label') }}” is turned on, and your documents are only shared with investors you choose.</p>

        @php($canEdit = $membership->canEdit())

        {{-- 1. Funding request --}}
        <section class="panel" aria-labelledby="request-heading">
            <h2 id="request-heading">Funding request
                <span class="badge badge-{{ $open ? 'on' : 'off' }}">{{ $open ? 'Open' : 'None open' }}</span></h2>
            @if ($open && ! $consent)
                <div class="alert alert-warning" role="status">Investors cannot see this request yet.
                    @if ($membership->canManageConsent()) <a href="{{ route('workspace.startups.consents', $startup) }}">Turn on “{{ config('nsp.registry.consent_purposes.investor_listing.label') }}”</a>.@else Ask an owner or founder to turn on investor sharing.@endif</div>
            @elseif ($open)
                <p class="meta">Verified investors can find this request. They see your name, sector, location, growth stage and what you write here.</p>
            @endif
            @if ($canEdit)
                <form method="post" action="{{ route('workspace.funding.request', $startup) }}">
                    @csrf @method('PUT')
                    <div class="grid">
                        @include('partials.field', ['name' => 'amount', 'label' => 'Amount you are raising', 'type' => 'number', 'required' => true, 'value' => $open?->amount, 'attrs' => 'min="1" step="1" inputmode="numeric"'])
                        @include('partials.field', ['name' => 'currency', 'label' => 'Currency', 'type' => 'select', 'required' => true, 'value' => $open?->currency ?? 'USD', 'options' => array_combine(\App\Models\Funding\InvestorProfile::CURRENCIES, \App\Models\Funding\InvestorProfile::CURRENCIES)])
                    </div>
                    @include('partials.checks', ['name' => 'instruments', 'label' => 'Types of funding you would consider', 'options' => \App\Models\Funding\InvestorProfile::INSTRUMENTS, 'selected' => $open?->instruments ?? []])
                    @include('partials.field', ['name' => 'pitch', 'label' => 'Your pitch for investors', 'type' => 'textarea', 'rows' => 5, 'required' => true, 'value' => $open?->pitch, 'hint' => 'What you do, your traction and why now. Investors see this text. Do not include personal phone numbers or bank details.'])
                    @include('partials.field', ['name' => 'use_of_funds', 'label' => 'How you will use the money', 'type' => 'textarea', 'rows' => 3, 'value' => $open?->use_of_funds])
                    <button type="submit" class="btn btn-primary">{{ $open ? 'Save changes' : 'Open funding request' }}</button>
                </form>
                @if ($open)
                    <form method="post" action="{{ route('workspace.funding.close', $startup) }}" data-confirm="Close this funding request? Investors will no longer find it." class="inline-form">
                        @csrf <button type="submit" class="btn btn-secondary">Close request</button>
                    </form>
                @endif
            @elseif ($open)
                <p><strong>{{ $open->amountLabel() }}</strong> · {{ collect($open->instruments)->map(fn ($i) => \App\Models\Funding\InvestorProfile::INSTRUMENTS[$i] ?? $i)->join(', ') }}</p>
                <p>{{ $open->pitch }}</p>
            @else
                <p class="empty">No open funding request. Owners, founders and admins can open one.</p>
            @endif
            @if ($past->isNotEmpty())
                <p class="meta">Earlier requests: @foreach ($past as $request){{ $request->amountLabel() }} (closed {{ $request->closed_at?->format('j M Y') }})@if (! $loop->last), @endif @endforeach</p>
            @endif
        </section>

        {{-- 2. Introductions --}}
        <section class="panel" aria-labelledby="intro-heading">
            <h2 id="intro-heading">Introductions</h2>
            @forelse ($introductions as $introduction)
                @php($investorProfile = $profiles[$introduction->investor_user_id] ?? null)
                <div class="card">
                    <p><strong>{{ $introduction->investor?->name ?? 'Investor' }}</strong>@if ($investorProfile?->organisation) · {{ $investorProfile->organisation }}@endif
                        <span class="badge badge-{{ $introduction->status === 'accepted' ? 'on' : ($introduction->status === 'requested' ? 'draft' : 'off') }}">{{ \App\Models\Funding\Introduction::STATUSES[$introduction->status] }}</span></p>
                    <p class="meta">{{ $introduction->requested_by === 'founder' ? 'You asked' : 'The investor asked' }} on {{ $introduction->created_at->format('j M Y') }}</p>
                    @if ($introduction->message)<p>{{ $introduction->message }}</p>@endif
                    @if ($introduction->status === 'accepted' && $introduction->investor)
                        <p>Contact: <a href="mailto:{{ $introduction->investor->email }}">{{ $introduction->investor->email }}</a>@if ($investorProfile?->website) · <a href="{{ $investorProfile->website }}" rel="noopener nofollow" target="_blank">{{ $investorProfile->website }}</a>@endif</p>
                    @endif
                    @if ($introduction->status === 'requested' && $introduction->requested_by === 'investor' && $canEdit)
                        <form method="post" action="{{ route('workspace.funding.introductions.decide', [$startup, $introduction]) }}" class="inline-form">
                            @csrf
                            <button type="submit" name="decision" value="accept" class="btn btn-primary btn-sm">Accept and share contacts<span class="visually-hidden"> with {{ $introduction->investor?->name }}</span></button>
                            <button type="submit" name="decision" value="decline" class="btn btn-secondary btn-sm">Decline<span class="visually-hidden"> {{ $introduction->investor?->name }}</span></button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="empty">No introductions yet.</p>
            @endforelse
        </section>

        {{-- 3. Matching investors --}}
        @if ($open)
            <section class="panel" aria-labelledby="match-heading">
                <h2 id="match-heading">Verified investors</h2>
                <p class="hint">Sorted by how well each investor’s stated focus fits your request. The reasons show why.</p>
                @php($contacted = $introductions->whereIn('status', ['requested', 'accepted'])->pluck('investor_user_id')->all())
                @forelse ($matches as $match)
                    @php($investor = $match['investor'])
                    <div class="card">
                        <h3 class="h4">{{ $investor->user->name }}@if ($investor->organisation) · {{ $investor->organisation }}@endif</h3>
                        <p class="meta">{{ \App\Models\Funding\InvestorProfile::TYPES[$investor->investor_type] ?? $investor->investor_type }} · Ticket {{ $investor->ticketLabel() }}</p>
                        @if ($investor->thesis)<p>{{ $investor->thesis }}</p>@endif
                        <p>@if ($match['fits'])<span class="badge badge-on">Good fit</span>@else<span class="badge">Partial fit</span>@endif
                            @foreach ($match['reasons'] as $reason)<span class="tag">{{ $reason }}</span>@endforeach
                            @if ($match['misses'])<span class="meta">Outside their focus: {{ implode(', ', $match['misses']) }}</span>@endif</p>
                        @if ($canEdit && ! in_array($investor->user_id, $contacted, true))
                            <form method="post" action="{{ route('workspace.funding.introductions.store', $startup) }}">
                                @csrf
                                <input type="hidden" name="investor_user_id" value="{{ $investor->user_id }}">
                                @include('partials.field', ['name' => 'message', 'id' => 'msg-'.$investor->id, 'label' => 'Message to '.$investor->user->name, 'type' => 'textarea', 'rows' => 2, 'required' => true])
                                <button type="submit" class="btn btn-primary btn-sm">Request introduction</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="empty">No verified investors are listed yet.</p>
                @endforelse
            </section>
        @endif

        {{-- 4. Data room --}}
        <section class="panel" aria-labelledby="room-heading">
            <h2 id="room-heading">Data room</h2>
            <p class="hint">Private documents. Your team can always open them. An investor can open them only after you connect and share access, and only until the date you choose. Every download is logged below.</p>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Data room documents">
                <table>
                    <thead><tr><th scope="col">Document</th><th scope="col">Type</th><th scope="col">Size</th><th scope="col">Added</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    @forelse ($files as $file)
                        <tr>
                            <td><a href="{{ route('dataroom.download', $file) }}">{{ $file->title }}</a><br><span class="meta">{{ $file->original_name }}</span></td>
                            <td>{{ \App\Models\Funding\DataRoomFile::CATEGORIES[$file->category] ?? $file->category }}</td>
                            <td>{{ number_format($file->size / 1024, 0) }} KB</td>
                            <td>{{ $file->created_at->format('j M Y') }} by {{ $file->uploader?->name ?? '—' }}</td>
                            <td>@if ($canEdit)
                                <form method="post" action="{{ route('workspace.funding.files.destroy', [$startup, $file]) }}" data-confirm="Delete “{{ $file->title }}”?">
                                    @csrf @method('DELETE') <button type="submit" class="btn btn-secondary btn-sm">Delete<span class="visually-hidden"> {{ $file->title }}</span></button>
                                </form>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No documents yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($canEdit)
                <h3>Add a document</h3>
                <form method="post" action="{{ route('workspace.funding.files.store', $startup) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="grid">
                        @include('partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'maxlength' => 150])
                        @include('partials.field', ['name' => 'category', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => \App\Models\Funding\DataRoomFile::CATEGORIES])
                    </div>
                    @include('partials.field', ['name' => 'file', 'label' => 'File', 'type' => 'file', 'required' => true, 'attrs' => 'accept=".pdf,.pptx,.xlsx,.xls,.docx,.png,.jpg,.jpeg"', 'hint' => 'PDF, PowerPoint, Excel, Word or images (PNG, JPEG). Up to 20 MB.'])
                    <button type="submit" class="btn btn-primary">Upload</button>
                </form>

                <h3>Who can open your data room</h3>
                @forelse ($grants as $grant)
                    <div>{{ $grant->investor?->name ?? 'Investor' }} until {{ $grant->expires_at->format('j M Y') }}
                        <form method="post" action="{{ route('workspace.funding.grants.destroy', [$startup, $grant]) }}" class="inline-form" data-confirm="Remove access for {{ $grant->investor?->name }}?">
                            @csrf @method('DELETE') <button type="submit" class="btn btn-secondary btn-sm">Remove access<span class="visually-hidden"> for {{ $grant->investor?->name }}</span></button>
                        </form></div>
                @empty
                    <p class="empty">No investor has access.</p>
                @endforelse
                @if ($connected->isNotEmpty())
                    <form method="post" action="{{ route('workspace.funding.grants.store', $startup) }}">
                        @csrf
                        <div class="grid">
                            @include('partials.field', ['name' => 'investor_user_id', 'label' => 'Share with a connected investor', 'type' => 'select', 'required' => true, 'options' => $connected->pluck('name', 'id')->all()])
                            @include('partials.field', ['name' => 'expires_at', 'label' => 'Access ends on', 'type' => 'date', 'value' => now()->addDays(30)->toDateString(), 'hint' => 'Up to 180 days from today.'])
                        </div>
                        <button type="submit" class="btn btn-primary">Share data room</button>
                    </form>
                @else
                    <p class="hint">You can share documents once an investor introduction is accepted.</p>
                @endif
            @endif

            <h3>Download log</h3>
            @forelse ($accessLog as $entry)
                <p class="meta">{{ $entry->accessed_at->format('j M Y H:i') }} · {{ $entry->user?->name ?? 'Former user' }} ({{ $entry->access_type === 'investor' ? 'investor' : 'team' }}) · {{ $entry->file?->title ?? 'Deleted document' }}</p>
            @empty
                <p class="empty">No downloads yet.</p>
            @endforelse
        </section>
    </div>
@endsection
