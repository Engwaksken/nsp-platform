@extends('layouts.public', ['pageTitle' => 'Edit vacancy · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <p><a href="{{ route('workspace.talent.vacancy', [$startup, $vacancy]) }}">← Applications for this role</a></p>
        <h1>{{ $vacancy->title }}</h1>
        @include('partials.flash')
        <p>Status: <span class="badge badge-{{ $vacancy->status === 'open' ? 'on' : ($vacancy->status === 'draft' ? 'draft' : 'off') }}">{{ \App\Models\Talent\Vacancy::STATUSES[$vacancy->status] }}</span>
            @if ($vacancy->status === 'open') · <a href="{{ route('jobs.show', $vacancy) }}">View on the jobs board</a>@endif</p>
        @if ($vacancy->status === 'removed')
            <div class="alert alert-warning" role="status">Hive took this vacancy down: {{ $vacancy->moderation_note }}. Contact Hive if you think this is a mistake.</div>
        @else
            <form method="post" action="{{ route('workspace.talent.status', [$startup, $vacancy]) }}" class="inline-form">
                @csrf
                @if ($vacancy->status !== 'open')<button type="submit" name="status" value="open" class="btn btn-primary">Publish</button>@endif
                @if ($vacancy->status === 'open')<button type="submit" name="status" value="closed" class="btn btn-secondary">Close applications</button>
                    <button type="submit" name="status" value="filled" class="btn btn-secondary">Mark as filled</button>@endif
                @if (in_array($vacancy->status, ['open', 'closed'], true))<button type="submit" name="status" value="draft" class="btn btn-secondary">Back to draft</button>@endif
            </form>
            <form method="post" action="{{ route('workspace.talent.update', [$startup, $vacancy]) }}">
                @csrf @method('PUT')
                @include('workspace.talent._form')
                <button type="submit" class="btn btn-primary">Save changes</button>
            </form>
        @endif
    </div>
@endsection
