@extends('layouts.admin')
@section('title', 'Possible duplicates')

@section('content')
    <div class="page-head">
        <h1>Possible duplicate startups</h1>
        <form method="post" action="{{ route('admin.duplicates.scan') }}">
            @csrf
            <button type="submit" class="btn btn-primary">Scan for duplicates</button>
        </form>
    </div>
    <p class="hint">Pairs are found by matching registration number, website or name. A matching name alone is weak evidence. Nothing is merged without your decision and a written reason, and every merge can be undone.</p>
    <nav class="filters" aria-label="Filter by status">
        @foreach (['pending' => 'To review', 'not_duplicate' => 'Different companies', 'merged' => 'Merged'] as $value => $label)
            <a class="tag" href="{{ route('admin.duplicates.index', ['status' => $value]) }}" @if ($status === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @forelse ($candidates as $candidate)
        <section class="panel" aria-labelledby="pair-{{ $candidate->id }}">
            <h2 id="pair-{{ $candidate->id }}" class="h3">Match score {{ $candidate->score }} · {{ implode(', ', $candidate->evidence) }}</h2>
            <div class="form-row">
                @foreach ([$candidate->startupA, $candidate->startupB] as $startup)
                    <div class="card">
                        <strong><a href="{{ route('admin.startups.show', $startup) }}">{{ $startup->display_name }}</a></strong>
                        <p class="meta">{{ $startup->legal_name }} · {{ $startup->registration_number ? 'reg. '.$startup->registration_number : 'no registration number' }} · {{ $startup->website_domain ?: 'no website' }}<br>
                            Registered {{ $startup->created_at->format('j M Y') }} · {{ $startup->status }}</p>
                    </div>
                @endforeach
            </div>
            @if ($candidate->status === 'pending')
                <form method="post" action="{{ route('admin.duplicates.decide', $candidate) }}">
                    @csrf
                    @include('partials.field', ['name' => 'notes', 'id' => 'notes-'.$candidate->id, 'label' => 'Evidence for your decision', 'type' => 'textarea', 'rows' => 2, 'required' => true,
                        'hint' => 'For example: confirmed with both founders by phone that this is the same company.'])
                    <fieldset class="form-group">
                        <legend class="label">If merging, keep</legend>
                        @foreach ([$candidate->startupA, $candidate->startupB] as $startup)
                            <label class="checkbox"><input type="radio" name="keep" value="{{ $startup->uuid }}" @checked($loop->first)> <span>{{ $startup->display_name }} (the other one is merged into it)</span></label>
                        @endforeach
                    </fieldset>
                    <div class="actions">
                        <button type="submit" name="decision" value="not_duplicate" class="btn btn-secondary">Different companies</button>
                        <button type="submit" name="decision" value="merge" class="btn btn-danger">Merge</button>
                    </div>
                </form>
            @else
                <p class="meta">{{ $candidate->status === 'merged' ? 'Merged' : 'Marked as different' }} by {{ $candidate->decider?->name ?? 'an unknown user' }} on {{ $candidate->decided_at?->format('j M Y') }}: “{{ $candidate->notes }}”</p>
            @endif
        </section>
    @empty
        <p class="empty">Nothing here.</p>
    @endforelse
    {{ $candidates->links() }}

    @if ($merges->isNotEmpty())
        <section class="panel">
            <h2>Recent merges</h2>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Recent merges">
                <table>
                    <thead><tr><th scope="col">Merged</th><th scope="col">Into</th><th scope="col">When / who</th><th scope="col">Reason</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    @foreach ($merges as $merge)
                        <tr>
                            <td>{{ $merge->source?->display_name }}</td>
                            <td>{{ $merge->target?->display_name }}</td>
                            <td>{{ $merge->merged_at->format('j M Y H:i') }} · @if ($merge->merger){{ $merge->merger->name }}@else<span class="meta">Unknown</span>@endif</td>
                            <td>{{ $merge->reason }}</td>
                            <td>
                                @if ($merge->undone_at)
                                    Undone {{ $merge->undone_at->format('j M Y') }}
                                @else
                                    <form method="post" action="{{ route('admin.merges.undo', $merge) }}" data-confirm="Undo this merge? Both startups become active again and their teams are restored.">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">Undo</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
