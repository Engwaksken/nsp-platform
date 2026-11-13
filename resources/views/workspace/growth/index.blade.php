@extends('layouts.workspace', ['pageTitle' => 'Growth · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section">
        <h1>Growth · {{ $startup->display_name }}</h1>
        @include('partials.flash')

        @if (!$framework && !$latest)
            <div class="card"><h2>Coming soon</h2><p>Hive has not published the growth assessment yet. You will be able to assess your startup and build a growth plan here.</p></div>
        @else
            @include('partials.stats', ['label' => 'Growth at a glance', 'stats' => [
                ['icon' => 'fa-gauge-high', 'tone' => 'green', 'label' => 'Growth score', 'value' => $latest ? number_format($latest->displayScore(), 1) : 'None', 'note' => $latest ? 'out of 100' : 'Not assessed yet'],
                ['icon' => 'fa-seedling', 'tone' => 'green', 'label' => 'Stage', 'value' => $latest ? $latest->displayBand() : 'Not assessed'],
                ['icon' => 'fa-list-check', 'tone' => 'blue', 'label' => 'Open actions', 'value' => $actions->where('status', '!=', 'done')->count(), 'note' => $actions->where('status', 'done')->count().' done'],
                ['icon' => 'fa-clock-rotate-left', 'tone' => 'purple', 'label' => 'Assessments', 'value' => $history->count()],
            ]])

            <div data-tabs="Growth sections" data-tabs-key="growth">
            <section class="panel" aria-labelledby="assess-heading" data-tab="Assessment" data-tab-icon="fa-gauge-high">
                <h2 id="assess-heading">Growth assessment</h2>
                @if ($latest)
                    @include('workspace.growth.scores', ['assessment' => $latest])
                    <p><a href="{{ route('workspace.growth.assessment', [$startup, $latest]) }}">See the full results and suggested actions</a>
                        · <a href="{{ route('workspace.growth.report', [$startup, $latest]) }}">Printable report</a></p>
                @else
                    <p>Answer {{ $framework?->questionCount() }} questions about your team, customers, traction, finances and operations. It takes about 15 minutes. You get a score for each area and suggested next steps.</p>
                @endif
                @if ($membership->canEdit() && $framework)
                    @if ($draft)
                        <a class="btn btn-primary" href="{{ route('workspace.growth.assessment', [$startup, $draft]) }}">Continue your assessment</a>
                    @else
                        <form method="post" action="{{ route('workspace.growth.start', $startup) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">{{ $latest ? 'Reassess now' : 'Start the assessment' }}</button>
                        </form>
                    @endif
                @elseif (!$membership->canEdit())
                    <p class="hint">Owners, founders and admins fill in the assessment.</p>
                @endif
            </section>

            @if ($latest && $dimensionTrends)
                <section class="panel" aria-labelledby="dimension-trends-heading" data-tab="Progress by area" data-tab-icon="fa-chart-line">
                    <h2 id="dimension-trends-heading">Dimension-level progress</h2>
                    <p class="hint">Only assessments using the same rubric version are compared. Missing information is not treated as a score change.</p>
                    <div class="table-wrap" tabindex="0" role="region" aria-label="VGI dimension score trends">
                        <table>
                            <caption>Latest VGI dimension scores and change from the prior comparable assessment</caption>
                            <thead><tr><th scope="col">Dimension</th><th scope="col">Latest</th><th scope="col">Previous comparable</th><th scope="col">Change</th></tr></thead>
                            <tbody>@foreach ($dimensionTrends as $trend)
                                <tr><th scope="row">{{ $trend['title'] }}</th>
                                    <td>{{ $trend['score'] === null ? 'Insufficient information' : number_format($trend['score'], 1) }}</td>
                                    <td>@if ($trend['previous'] === null)<span class="meta">None</span>@else{{ number_format($trend['previous'], 1) }}@endif</td>
                                    <td>{{ $trend['change'] === null ? 'Not comparable' : ucfirst($trend['direction']).' ('.($trend['change'] > 0 ? '+' : '').number_format($trend['change'], 1).')' }}</td>
                                </tr>
                            @endforeach</tbody>
                        </table>
                    </div>
                </section>
            @endif

            <section class="panel" aria-labelledby="plan-heading" data-tab="Growth plan" data-tab-icon="fa-list-check" data-tab-count="{{ $actions->where('status', '!=', 'done')->count() }}">
                <h2 id="plan-heading">Growth plan</h2>
                @if ($actions->isEmpty())
                    <p class="empty">No actions yet. Add suggested actions from your assessment results, or add your own below.</p>
                @else
                    <div class="table-wrap" tabindex="0" role="region" aria-label="Growth plan">
                        <table>
                            <thead><tr><th scope="col">Action</th><th scope="col">Owner</th><th scope="col">Due</th><th scope="col">Status</th><th scope="col">Update</th></tr></thead>
                            <tbody>
                            @foreach ($actions as $action)
                                <tr>
                                    <td><strong>{{ $action->title }}</strong>@if ($action->details)<br><span class="meta">{{ $action->details }}</span>@endif
                                        @foreach ($action->updates->take(2) as $update)<br><span class="meta">{{ $update->created_at->format('j M') }} · {{ $update->user?->name }}: {{ $update->note }}</span>@endforeach</td>
                                    <td>@if ($action->owner){{ $action->owner->name }}@else<span class="meta">Unassigned</span>@endif</td>
                                    <td>@if ($action->due_date){{ $action->due_date->format('j M Y') }}@else<span class="meta">No due date</span>@endif @if ($action->due_date?->isPast() && $action->status !== 'done')<br><span class="badge badge-draft">Overdue</span>@endif</td>
                                    <td><span class="badge badge-{{ ['done' => 'on', 'doing' => 'scheduled'][$action->status] ?? 'draft' }}">{{ \App\Models\Growth\GrowthAction::STATUSES[$action->status] }}</span></td>
                                    <td>
                                        <form method="post" action="{{ route('workspace.growth.actions.update', [$startup, $action]) }}">
                                            @csrf @method('PUT')
                                            <label class="visually-hidden" for="status-{{ $action->id }}">Status of {{ $action->title }}</label>
                                            <select id="status-{{ $action->id }}" name="status">
                                                @foreach (\App\Models\Growth\GrowthAction::STATUSES as $value => $label)<option value="{{ $value }}" @selected($action->status === $value)>{{ $label }}</option>@endforeach
                                            </select>
                                            <label class="visually-hidden" for="note-{{ $action->id }}">Progress note</label>
                                            <input id="note-{{ $action->id }}" type="text" name="note" maxlength="2000" placeholder="Progress note (optional)">
                                            <button type="submit" class="btn btn-icon btn-icon-primary" data-tip="Save"><i class="fas fa-floppy-disk" aria-hidden="true"></i><span class="visually-hidden">Save: {{ $action->title }}</span></button>
                                        </form>
                                        @if ($membership->canEdit())
                                            <form method="post" action="{{ route('workspace.growth.actions.destroy', [$startup, $action]) }}" data-confirm="Remove this action from the plan?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-icon btn-icon-danger" data-tip="Remove"><i class="fas fa-trash-can" aria-hidden="true"></i><span class="visually-hidden">Remove {{ $action->title }}</span></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="meta">{{ $actions->where('status', 'done')->count() }} of {{ $actions->count() }} actions done.</p>
                @endif

                @if ($membership->canEdit())
                    <form method="post" action="{{ route('workspace.growth.actions.store', $startup) }}" class="form-row" style="margin-top:1rem">
                        @csrf
                        @include('partials.field', ['name' => 'title', 'label' => 'New action', 'maxlength' => 200, 'required' => true])
                        @include('partials.field', ['name' => 'owner_user_id', 'label' => 'Owner', 'type' => 'select', 'placeholder' => 'No owner',
                            'options' => $members->mapWithKeys(fn ($m) => [$m->user_id => $m->user?->name])->all()])
                        @include('partials.field', ['name' => 'due_date', 'label' => 'Due date', 'type' => 'date'])
                        <div><button type="submit" class="btn btn-primary">Add action</button></div>
                    </form>
                @endif
            </section>

            @if ($history->count() > 1)
                <section class="panel" aria-labelledby="history-heading" data-tab="History" data-tab-icon="fa-clock-rotate-left">
                    <h2 id="history-heading">Progress over time</h2>
                    <table>
                        <thead><tr><th scope="col">Date</th><th scope="col">Score</th><th scope="col">Stage</th><th scope="col">Rubric</th></tr></thead>
                        <tbody>
                        @foreach ($history as $past)
                            <tr>
                                <td><a href="{{ route('workspace.growth.assessment', [$startup, $past]) }}">{{ $past->submitted_at->format('j M Y') }}</a></td>
                                <td>{{ number_format($past->displayScore(), 1) }}</td>
                                <td>{{ $past->displayBand() }}</td>
                                <td>v{{ $past->framework->version }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <p class="hint">Scores from different rubric versions are not directly comparable.</p>
                </section>
            @endif
            </div>
        @endif
    </div>
@endsection
