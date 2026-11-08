@extends('layouts.public', ['pageTitle' => $organization->name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>{{ $organization->name }} <span class="badge badge-{{ $organization->isActive() ? 'on' : ($organization->isSuspended() ? 'off' : 'draft') }}">{{ $organization->statusLabel() }}</span></h1>
        <p class="meta">{{ $organization->typeLabel() }} · your role: {{ $membership->roleLabel() }}</p>
        @include('organizations.partials.nav')
        @include('partials.flash')

        @if ($organization->isSuspended())
            <div class="alert alert-warning" role="status">This organization is suspended by the Hive team. Portfolio access is paused. Contact Hive if you think this is a mistake.</div>
        @elseif (!$organization->isActive())
            <div class="alert alert-warning" role="status">The Hive team has not verified this organization yet. You can invite colleagues now; portfolio requests open once it is verified.</div>
        @elseif (!$membership->may('view_portfolio'))
            <p>Your role does not include portfolio access. An organization admin can change your role.</p>
        @else
            <section class="panel" aria-labelledby="portfolio-heading">
                <h2 id="portfolio-heading">Portfolio</h2>
                <p class="hint">Only startups that approved access are listed, with only the items they agreed to share. If a startup revokes access or it expires, it disappears here at once.</p>
                @if ($portfolio->isEmpty())
                    <p class="empty">No startups have shared information with {{ $organization->name }} yet.</p>
                @else
                    <div class="table-wrap" tabindex="0" role="region" aria-label="Portfolio startups">
                        <table>
                            <thead><tr><th scope="col">Startup</th><th scope="col">Purpose</th><th scope="col">Shared items</th><th scope="col">Until</th><th scope="col">Actions</th></tr></thead>
                            <tbody>
                            @foreach ($portfolio as $rel)
                                <tr>
                                    <td><a href="{{ route('organizations.startup', [$organization, $rel->startup]) }}">{{ $rel->startup->display_name }}</a></td>
                                    <td>{{ $rel->purposeLabel() }}</td>
                                    <td><span class="tags">@foreach ($rel->scopeLabels() as $label)<span class="tag">{{ $label }}</span>@endforeach</span></td>
                                    <td>{{ $rel->expires_at?->format('j M Y') ?? 'Until revoked' }}</td>
                                    <td>
                                        @if ($membership->may('request_access'))
                                            <form method="post" action="{{ route('organizations.relationships.withdraw', [$organization, $rel]) }}" data-confirm="Give up access to {{ $rel->startup->display_name }}?">
                                                @csrf
                                                <button type="submit" class="btn btn-secondary btn-sm">End access<span class="visually-hidden"> to {{ $rel->startup->display_name }}</span></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @if ($membership->may('request_access'))
                <section class="panel" aria-labelledby="request-heading">
                    <h2 id="request-heading">Ask a startup for access</h2>
                    <p class="hint">Enter the startup’s Hive ID or its company registration number. The startup’s owners and founders are notified and decide what, if anything, to share.</p>
                    <form method="post" action="{{ route('organizations.requests.store', $organization) }}">
                        @csrf
                        <div class="form-row">
                            @include('partials.field', ['name' => 'startup', 'label' => 'Hive startup ID or registration number', 'required' => true, 'maxlength' => 80])
                            @include('partials.field', ['name' => 'purpose', 'label' => 'Purpose', 'type' => 'select', 'required' => true, 'options' => config('organizations.purposes'), 'value' => 'portfolio'])
                        </div>
                        @include('organizations.partials.scopes', ['idPrefix' => 'request', 'checked' => ['profile_summary']])
                        @include('partials.field', ['name' => 'note', 'label' => 'Message to the startup (optional)', 'type' => 'textarea', 'rows' => 3, 'hint' => 'Visible to the startup’s owners and founders.'])
                        <button type="submit" class="btn btn-primary">Send request</button>
                    </form>
                </section>

                <section class="panel" aria-labelledby="pending-heading">
                    <h2 id="pending-heading">Waiting for the startup</h2>
                    @if ($requests->isEmpty())
                        <p class="empty">No open requests.</p>
                    @else
                        <ul>
                            @foreach ($requests as $rel)
                                <li>{{ $rel->startup?->display_name }} · {{ $rel->purposeLabel() }} · asked {{ $rel->created_at->format('j M Y') }}
                                    <form method="post" action="{{ route('organizations.relationships.withdraw', [$organization, $rel]) }}" class="actions">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">Withdraw<span class="visually-hidden"> request to {{ $rel->startup?->display_name }}</span></button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        @endif
    </div>
@endsection
