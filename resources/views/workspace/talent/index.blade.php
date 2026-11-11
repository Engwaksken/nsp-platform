@extends('layouts.workspace', ['pageTitle' => 'Talent · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section">
        <h1>Talent · {{ $startup->display_name }}</h1>
        @include('partials.flash')
        <p class="hint">Open vacancies appear on the public <a href="{{ route('jobs.index') }}">jobs board</a> with your startup’s name, sector and country. Applications and CVs are visible to your owners, founders and admins only.
            @if ($membership->canEdit()) <a href="{{ route('workspace.talent.search', $startup) }}">Find candidates</a>@endif</p>

        @include('partials.stats', ['label' => 'Talent at a glance', 'stats' => [
            ['icon' => 'fa-briefcase', 'tone' => 'orange', 'label' => 'Open vacancies', 'value' => $vacancies->where('status', 'open')->count()],
            ['icon' => 'fa-pen-to-square', 'tone' => 'grey', 'label' => 'Drafts', 'value' => $vacancies->where('status', 'draft')->count()],
            ['icon' => 'fa-file-lines', 'tone' => 'blue', 'label' => 'Applications', 'value' => (int) $vacancies->sum('applications_count')],
            ['icon' => 'fa-bell', 'tone' => 'red', 'label' => 'New applications', 'value' => (int) $vacancies->sum('new_count')],
        ]])

        <div data-tabs="Talent sections" data-tabs-key="talent">
        <section data-tab="Vacancies" data-tab-icon="fa-briefcase" data-tab-count="{{ $vacancies->count() }}" aria-label="Vacancies">
        <div class="table-wrap" tabindex="0" role="region" aria-label="Vacancies">
            <table>
                <thead><tr><th scope="col">Role</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Applications</th><th scope="col">Closes</th></tr></thead>
                <tbody>
                @forelse ($vacancies as $item)
                    <tr>
                        <td>@if ($membership->canEdit())<a href="{{ route('workspace.talent.vacancy', [$startup, $item]) }}">{{ $item->title }}</a>@else{{ $item->title }}@endif
                            @if ($item->status === 'removed' && $item->moderation_note)<br><span class="meta">Hive: {{ $item->moderation_note }}</span>@endif</td>
                        <td>{{ \App\Models\Talent\Vacancy::TYPES[$item->type] }}</td>
                        <td><span class="badge badge-{{ $item->status === 'open' ? 'on' : ($item->status === 'draft' ? 'draft' : 'off') }}">{{ \App\Models\Talent\Vacancy::STATUSES[$item->status] }}</span></td>
                        <td>{{ $item->applications_count }}@if ($item->new_count) ({{ $item->new_count }} new)@endif</td>
                        <td>@if ($item->closes_on){{ $item->closes_on->format('j M Y') }}@else<span class="meta">No closing date</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No vacancies yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        </section>

        @if ($membership->canEdit())
            <section class="panel" aria-labelledby="new-heading" data-tab="Post a vacancy" data-tab-icon="fa-plus">
                <h2 id="new-heading">Post a vacancy</h2>
                <form method="post" action="{{ route('workspace.talent.store', $startup) }}">
                    @csrf
                    @include('workspace.talent._form')
                    <button type="submit" class="btn btn-primary">Save as draft</button>
                </form>
            </section>
        @endif
        </div>
    </div>
@endsection
