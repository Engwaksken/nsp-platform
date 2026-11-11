@extends('layouts.app', ['pageTitle' => 'Due diligence · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    @php
        $itemTone = ['requested' => 'draft', 'provided' => 'scheduled', 'accepted' => 'on', 'not_applicable' => 'off'];
        $requestTone = ['requested' => 'draft', 'fulfilled' => 'on', 'declined' => 'off'];
        $categories = config('capital.diligence_categories');
        $toReview = $items->where('status', 'provided')->count();
    @endphp
    <div class="container section">
        <p><a href="{{ route('investor.startup', $startup) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ $startup->display_name }}</a></p>
        <div class="page-head">
            <div>
                <h1>Due diligence · {{ $startup->display_name }}</h1>
                <p>Connected since {{ $connection->decided_at?->format('j M Y') ?? 'recently' }}. The founders see your checklist, its notes and your document requests.</p>
            </div>
            <div class="capital-actions">
                @if ($hasScopes)<a class="btn btn-secondary" href="{{ route('investor.capital.readiness', $startup) }}"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Readiness report</a>@endif
                <a class="btn btn-secondary" href="{{ route('investor.capital.pipeline') }}"><i class="fas fa-table-columns" aria-hidden="true"></i> My pipeline</a>
            </div>
        </div>
        @include('partials.flash')
        @if (! $grant)
            <p class="alert alert-info" role="status">The founders have not shared their data room with you, so you see document titles only.</p>
        @endif

        @include('partials.stats', ['label' => 'Due diligence at a glance', 'stats' => [
            ['icon' => 'fa-list-check', 'tone' => 'purple', 'label' => 'Checklist items', 'value' => $items->count(), 'note' => $items->where('status', 'accepted')->count().' accepted'],
            ['icon' => 'fa-inbox', 'tone' => 'blue', 'label' => 'Provided, to review', 'value' => $toReview],
            ['icon' => 'fa-file-circle-question', 'tone' => 'amber', 'label' => 'Document requests', 'value' => $documentRequests->count(), 'note' => $documentRequests->where('status', 'requested')->count().' waiting'],
        ]])

        <div data-tabs="Due diligence sections" data-tabs-key="investor-diligence">
        <section class="panel" aria-labelledby="checklist-heading" data-tab="Checklist" data-tab-icon="fa-list-check" data-tab-count="{{ $items->count() }}">
            <h2 id="checklist-heading">Checklist</h2>
            <p class="hint">Accept an item the founders provided, ask for it again, or mark it not applicable.</p>
            @if ($items->isEmpty())
                <p class="empty">No checklist items yet. Add some on the "Add items" tab.</p>
            @else
                <x-card-grid label="Checklist items" min="280px" columns="3" remember="investor-diligence">
                    @foreach ($items as $item)
                        <x-collapsible-card id="item-{{ $item->id }}" icon="fa-file-lines" class="tone-purple" :title="$item->title"
                            :badge="$item->statusLabel()" :badge-tone="$itemTone[$item->status] ?? 'off'"
                            :summary="$item->categoryLabel().' · Due '.($item->due_date?->format('j M Y') ?? 'not set')"
                            :attention="$item->status === 'provided'">
                            @if ($item->file)
                                <p>Document:
                                    @if ($grant)<a href="{{ route('dataroom.download', $item->file) }}">{{ $item->file->title }}</a>@else{{ $item->file->title }} <span class="meta">(needs data-room access)</span>@endif</p>
                            @endif
                            @if ($item->notes)<p>{{ $item->notes }}</p>@endif
                            <form method="post" action="{{ route('investor.capital.diligence.update', $item) }}">
                                @csrf @method('PUT')
                                @include('partials.field', ['name' => 'status', 'id' => 'status-'.$item->id, 'label' => 'Status', 'type' => 'select', 'value' => $item->status,
                                    'options' => ($item->status === 'provided' ? ['provided' => 'Provided (waiting for you)'] : []) + ['requested' => 'Requested (ask again)', 'accepted' => 'Accepted', 'not_applicable' => 'Not applicable']])
                                @include('partials.field', ['name' => 'due_date', 'id' => 'due-'.$item->id, 'label' => 'Due date', 'type' => 'date', 'value' => $item->due_date?->toDateString()])
                                @include('partials.field', ['name' => 'notes', 'id' => 'notes-'.$item->id, 'label' => 'Note to the founders', 'type' => 'textarea', 'rows' => 2, 'value' => $item->notes, 'placeholder' => false])
                                <button type="submit" class="btn btn-primary btn-sm">Save<span class="visually-hidden"> {{ $item->title }}</span></button>
                            </form>
                        </x-collapsible-card>
                    @endforeach
                </x-card-grid>
            @endif
        </section>

        <section class="panel" aria-labelledby="add-heading" data-tab="Add items" data-tab-icon="fa-circle-plus">
            <h2 id="add-heading">Add checklist items</h2>
            <x-card-grid label="Ways to add items" min="280px" columns="2" :controls="false">
                <x-collapsible-card id="add-template" icon="fa-clipboard-list" class="tone-green" title="From a template" summary="Starting points you can adjust afterwards." :open="true">
                    <form method="post" action="{{ route('investor.capital.diligence.store', $startup) }}">
                        @csrf
                        @include('partials.field', ['name' => 'template', 'id' => 'template', 'label' => 'Template', 'type' => 'select', 'required' => true, 'options' => $templates])
                        @include('partials.field', ['name' => 'due_date', 'id' => 'template-due', 'label' => 'Due date for every item (optional)', 'type' => 'date', 'attrs' => 'min="'.now()->toDateString().'"'])
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Add template items</button>
                    </form>
                </x-collapsible-card>
                <x-collapsible-card id="add-own" icon="fa-pen" class="tone-blue" title="Your own items" summary="Up to three at a time. Rows without a title are skipped." :open="true" :attention="$errors->has('items')">
                    <form method="post" action="{{ route('investor.capital.diligence.store', $startup) }}">
                        @csrf
                        @error('items')<p class="error">{{ $message }}</p>@enderror
                        @for ($i = 0; $i < 3; $i++)
                            <fieldset class="form-group capital-row">
                                <legend class="label">Item {{ $i + 1 }}</legend>
                                @include('partials.field', ['name' => 'items['.$i.'][title]', 'id' => 'item-title-'.$i, 'label' => 'Title', 'maxlength' => 200])
                                <div class="grid">
                                    @include('partials.field', ['name' => 'items['.$i.'][category]', 'id' => 'item-category-'.$i, 'label' => 'Category', 'type' => 'select', 'options' => $categories])
                                    @include('partials.field', ['name' => 'items['.$i.'][due_date]', 'id' => 'item-due-'.$i, 'label' => 'Due date', 'type' => 'date', 'attrs' => 'min="'.now()->toDateString().'"'])
                                </div>
                            </fieldset>
                        @endfor
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-circle-plus" aria-hidden="true"></i> Add items</button>
                    </form>
                </x-collapsible-card>
            </x-card-grid>
        </section>

        <section class="panel" aria-labelledby="requests-heading" data-tab="Document requests" data-tab-icon="fa-file-circle-question" data-tab-count="{{ $documentRequests->count() }}">
            <h2 id="requests-heading">Document requests</h2>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Document requests">
                <table>
                    <thead><tr><th scope="col">Document</th><th scope="col">Type</th><th scope="col">Asked</th><th scope="col">Answer</th></tr></thead>
                    <tbody>
                    @forelse ($documentRequests as $documentRequest)
                        <tr>
                            <td>{{ $documentRequest->title }}@if ($documentRequest->message)<br><span class="meta">{{ $documentRequest->message }}</span>@endif</td>
                            <td>{{ \App\Models\Funding\DataRoomFile::CATEGORIES[$documentRequest->category] ?? $documentRequest->category }}</td>
                            <td>{{ $documentRequest->created_at->format('j M Y') }}</td>
                            <td><span class="badge badge-{{ $requestTone[$documentRequest->status] ?? 'off' }}">{{ \App\Models\Capital\DocumentRequest::STATUSES[$documentRequest->status] ?? $documentRequest->status }}</span>
                                @if ($documentRequest->status === 'fulfilled' && $documentRequest->file)
                                    <br>@if ($grant)<a href="{{ route('dataroom.download', $documentRequest->file) }}">{{ $documentRequest->file->title }}</a>@else<span class="meta">{{ $documentRequest->file->title }} (needs data-room access)</span>@endif
                                @elseif ($documentRequest->status === 'declined' && $documentRequest->response_note)
                                    <br><span class="meta">{{ $documentRequest->response_note }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No document requests yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <h3>Ask for a document</h3>
            <form method="post" action="{{ route('investor.capital.documents.store', $startup) }}">
                @csrf
                <div class="grid">
                    @include('partials.field', ['name' => 'title', 'id' => 'doc-title', 'label' => 'Document', 'required' => true, 'maxlength' => 200])
                    @include('partials.field', ['name' => 'category', 'id' => 'doc-category', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => \App\Models\Funding\DataRoomFile::CATEGORIES])
                </div>
                @include('partials.field', ['name' => 'message', 'id' => 'doc-message', 'label' => 'Message to the founders (optional)', 'type' => 'textarea', 'rows' => 2])
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane" aria-hidden="true"></i> Request document</button>
            </form>
        </section>
        </div>
    </div>
@endsection
