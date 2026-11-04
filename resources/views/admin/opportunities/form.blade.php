@extends('layouts.admin')
@section('title', $opportunity->exists ? 'Edit opportunity' : 'New opportunity')

@section('breadcrumbs')
    <li><a href="{{ route('admin.opportunities.index') }}">Opportunities</a></li>
    <li aria-current="page">{{ $opportunity->exists ? $opportunity->title : 'New' }}</li>
@endsection

@section('content')
    <div class="page-head">
        <h1>{{ $opportunity->exists ? $opportunity->title : 'New opportunity' }}
            @if ($opportunity->exists)<span class="badge badge-{{ ['published' => 'on', 'pending_review' => 'draft', 'draft' => 'draft'][$opportunity->status] ?? 'off' }}">{{ str_replace('_', ' ', $opportunity->status) }}</span>@endif</h1>
        @if ($opportunity->exists && $opportunity->accepts_interest)<a class="btn btn-secondary" href="{{ route('admin.opportunities.interests', $opportunity) }}">Registrations</a>@endif
    </div>
    @if ($opportunity->submitted_by)
        <div class="alert alert-info">Shared by {{ $opportunity->submitter?->name ?? 'a user' }} ({{ $opportunity->submitter?->email }}). Check the details and link before publishing.</div>
    @endif

    <form method="post" action="{{ $opportunity->exists ? route('admin.opportunities.update', $opportunity) : route('admin.opportunities.store') }}">
        @csrf
        @if ($opportunity->exists) @method('PUT') @endif
        @include('site.opportunities.fields', ['staff' => true])
        <div class="actions">
            <button type="submit" class="btn btn-secondary">Save</button>
            @unless ($opportunity->exists)<button type="submit" name="publish" value="1" class="btn btn-primary">Save and publish</button>@endunless
        </div>
    </form>

    @if ($opportunity->exists)
        <form method="post" action="{{ route('admin.opportunities.moderate', $opportunity) }}" class="panel">
            @csrf
            <h2>Status</h2>
            @include('partials.field', ['name' => 'note', 'label' => 'Note (required when rejecting; shown to the submitter)', 'type' => 'textarea', 'rows' => 2, 'value' => $opportunity->moderation_note])
            <div class="actions">
                @if ($opportunity->status !== 'published')<button type="submit" name="decision" value="publish" class="btn btn-primary">Publish</button>@endif
                @if ($opportunity->status === 'pending_review')<button type="submit" name="decision" value="reject" class="btn btn-danger">Reject</button>@endif
                @if ($opportunity->status === 'published')<button type="submit" name="decision" value="unpublish" class="btn btn-secondary">Unpublish</button>@endif
                @if ($opportunity->status !== 'archived')<button type="submit" name="decision" value="archive" class="btn btn-secondary">Archive</button>@endif
            </div>
        </form>
    @endif
@endsection
