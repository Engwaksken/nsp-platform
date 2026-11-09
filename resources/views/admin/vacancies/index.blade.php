@extends('layouts.admin')
@section('title', 'Vacancies')

@section('content')
    <div class="page-head"><h1>Vacancies</h1></div>
    @include('partials.stats', ['label' => 'Summary', 'stats' => \App\Support\AdminStats::vacancies()])
    @include('partials.flash')
    <p class="hint">Startups publish vacancies themselves. Take down anything misleading, discriminatory or unlawful; the startup sees your note. Applications and CVs are not visible here.</p>
    <nav class="tags" aria-label="Filter by status">
        @foreach (\App\Models\Talent\Vacancy::STATUSES as $value => $label)
            <a class="tag" href="{{ route('admin.vacancies.index', ['status' => $value]) }}" @if ($status === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @forelse ($vacancies as $vacancy)
        <section class="panel" aria-labelledby="vac-{{ $vacancy->uuid }}">
            <h2 id="vac-{{ $vacancy->uuid }}" class="h3">{{ $vacancy->title }} · {{ $vacancy->startup?->display_name }}
                <span class="badge">{{ \App\Models\Talent\Vacancy::STATUSES[$vacancy->status] }}</span></h2>
            <p class="meta">{{ \App\Models\Talent\Vacancy::TYPES[$vacancy->type] }} · {{ $vacancy->applications_count }} applications · created {{ $vacancy->created_at->format('j M Y') }}
                @if ($vacancy->status === 'open') · <a href="{{ route('jobs.show', $vacancy) }}">View public page</a>@endif</p>
            <p>{{ \Illuminate\Support\Str::limit($vacancy->description, 400) }}</p>
            @if ($vacancy->moderation_note)<p class="meta">Note: {{ $vacancy->moderation_note }}</p>@endif
            <form method="post" action="{{ route('admin.vacancies.moderate', $vacancy) }}">
                @csrf
                @if ($vacancy->status === 'removed')
                    <button type="submit" name="action" value="restore" class="btn btn-secondary btn-sm">Restore as draft<span class="visually-hidden"> {{ $vacancy->title }}</span></button>
                @else
                    @include('partials.field', ['name' => 'moderation_note', 'id' => 'note-'.$vacancy->uuid, 'label' => 'Reason (shown to the startup)', 'type' => 'textarea', 'rows' => 2])
                    <button type="submit" name="action" value="remove" class="btn btn-secondary btn-sm">Take down<span class="visually-hidden"> {{ $vacancy->title }}</span></button>
                @endif
            </form>
        </section>
    @empty
        <p class="empty">No vacancies with this status.</p>
    @endforelse
    {{ $vacancies->links() }}
@endsection
