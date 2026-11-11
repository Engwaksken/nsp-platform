@extends('layouts.workspace', ['pageTitle' => 'Organizations · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@php
    $scopes = (array) config('organizations.scopes', []);
    $purposes = (array) config('organizations.purposes', []);
    $canDecide = $membership->canManageConsent();
@endphp

@section('workspace')
    <div class="page-head">
        <div>
            <h1>Organizations</h1>
            <p class="lead-muted">Incubators, programmes, investors and partners that asked to follow {{ $startup->display_name }}. They only ever see the items you approve, and you can revoke access at any time.</p>
        </div>
    </div>

    @include('partials.flash')
    @foreach (['decision', 'revoke', 'share'] as $key)
        @error($key)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @endforeach

    @unless ($canDecide)
        <p class="alert alert-info">Only the startup's owners and founders can approve, decline, revoke or share. You can see what is shared.</p>
    @endunless

    <section class="panel" aria-labelledby="pending-heading">
        <h2 id="pending-heading">Waiting for your decision <span class="count">{{ $pending->count() }}</span></h2>
        @if ($pending->isEmpty())
            <p class="hint">No organization is waiting for an answer.</p>
        @else
            @foreach ($pending as $request)
                <article class="org-request">
                    <header>
                        <h3>{{ $request->organization?->name ?? 'Organization' }}</h3>
                        <p class="meta">{{ $request->purposeLabel() }} · asked {{ $request->created_at->format('j M Y') }}@if ($request->requester) by {{ $request->requester->name }}@endif</p>
                    </header>
                    @if ($request->note)<blockquote class="org-note">{{ $request->note }}</blockquote>@endif
                    @if ($canDecide)
                        <form method="post" action="{{ route('workspace.organizations.decide', [$startup, $request]) }}">
                            @csrf
                            <fieldset>
                                <legend>What may they see? Untick anything you do not want to share.</legend>
                                @foreach ((array) $request->scopes as $scope)
                                    <label class="check-row">
                                        <input type="checkbox" name="scopes[]" value="{{ $scope }}" checked>
                                        <span><strong>{{ $scopes[$scope]['label'] ?? $scope }}</strong>
                                            @if (!empty($scopes[$scope]['description']))<br><span class="meta">{{ $scopes[$scope]['description'] }}</span>@endif</span>
                                    </label>
                                @endforeach
                            </fieldset>
                            @include('partials.field', ['name' => 'expires_at', 'id' => 'expires-'.$request->uuid, 'label' => 'Access ends on', 'type' => 'date', 'value' => $defaultExpiry, 'hint' => 'Leave empty for no end date.'])
                            <div class="actions">
                                <button type="submit" name="decision" value="approve" class="btn btn-primary">Approve</button>
                                <button type="submit" name="decision" value="decline" class="btn btn-secondary" formnovalidate>Decline</button>
                            </div>
                        </form>
                    @else
                        <p class="meta">Requested: {{ implode(', ', $request->scopeLabels()) }}</p>
                    @endif
                </article>
            @endforeach
        @endif
    </section>

    <section class="panel" aria-labelledby="active-heading">
        <h2 id="active-heading">Who can see your startup <span class="count">{{ $active->count() }}</span></h2>
        @if ($active->isEmpty())
            <p class="hint">No organization has access to your startup's data.</p>
        @else
            <ul class="list-rows">
                @foreach ($active as $grant)
                    <li>
                        <div>
                            <strong>{{ $grant->organization?->name ?? 'Organization' }}</strong>
                            <span class="meta">{{ $grant->purposeLabel() }} · {{ implode(', ', $grant->scopeLabels()) }}</span>
                            <span class="meta">Since {{ ($grant->decided_at ?? $grant->created_at)->format('j M Y') }} · {{ $grant->expires_at ? 'until '.$grant->expires_at->format('j M Y') : 'no end date' }}</span>
                        </div>
                        @if ($canDecide)
                            <form method="post" action="{{ route('workspace.organizations.revoke', [$startup, $grant]) }}" data-confirm="Revoke access for {{ $grant->organization?->name }}? It stops immediately.">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">Revoke</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($canDecide && $organizations->isNotEmpty())
        <section class="panel" aria-labelledby="share-heading">
            <h2 id="share-heading">Share with an organization</h2>
            <p class="hint">Give an organization access without waiting for them to ask, for example a programme you joined.</p>
            <form method="post" action="{{ route('workspace.organizations.share', $startup) }}">
                @csrf
                <div class="form-row">
                    @include('partials.field', ['name' => 'organization', 'label' => 'Organization', 'type' => 'select', 'placeholder' => 'Choose…', 'required' => true,
                        'options' => $organizations->mapWithKeys(fn ($o) => [$o->uuid => $o->name])->all(), 'value' => ''])
                    @include('partials.field', ['name' => 'purpose', 'label' => 'Purpose', 'type' => 'select', 'placeholder' => 'Choose…', 'required' => true, 'options' => $purposes, 'value' => ''])
                    @include('partials.field', ['name' => 'expires_at', 'id' => 'share-expires', 'label' => 'Access ends on', 'type' => 'date', 'value' => $defaultExpiry])
                </div>
                <fieldset>
                    <legend class="required">What may they see?</legend>
                    @foreach ($scopes as $key => $scope)
                        <label class="check-row">
                            <input type="checkbox" name="scopes[]" value="{{ $key }}" @checked(in_array($key, (array) old('scopes', []), true))>
                            <span><strong>{{ $scope['label'] ?? $key }}</strong>
                                @if (!empty($scope['description']))<br><span class="meta">{{ $scope['description'] }}</span>@endif</span>
                        </label>
                    @endforeach
                    @error('scopes')<p class="error" role="alert">{{ $message }}</p>@enderror
                </fieldset>
                @include('partials.field', ['name' => 'note', 'label' => 'Note to the organization (optional)', 'type' => 'textarea', 'rows' => 3, 'value' => ''])
                <button type="submit" class="btn btn-primary">Share</button>
            </form>
        </section>
    @endif

    @if ($history->isNotEmpty())
        <section class="panel" aria-labelledby="history-heading">
            <h2 id="history-heading">History</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th scope="col">Organization</th><th scope="col">Purpose</th><th scope="col">Outcome</th><th scope="col">Date</th></tr></thead>
                    <tbody>
                    @foreach ($history as $item)
                        <tr>
                            <td>{{ $item->organization?->name ?? 'Organization' }}</td>
                            <td>{{ $item->purposeLabel() }}</td>
                            <td><span class="badge badge-off">{{ ucfirst($item->displayStatus()) }}</span></td>
                            <td>{{ ($item->revoked_at ?? $item->decided_at ?? $item->updated_at)->format('j M Y') }}@if ($item->decider) · {{ $item->decider->name }}@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
