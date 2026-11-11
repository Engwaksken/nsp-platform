@extends('layouts.admin')
@section('title', 'Integrations')

@section('content')
    <div class="page-head"><h1>Integrations</h1></div>
    <p class="hint">Connected systems and the events exchanged with them (docs/integration-contract-v1.md). Events never contain private startup figures, scores or reviewer notes.</p>

    <div class="kpis">
        @foreach (['pending' => 'Waiting to send', 'delivered' => 'Delivered', 'dead' => 'Failed (needs attention)'] as $status => $label)
            <div class="kpi"><strong>{{ number_format($counts[$status] ?? 0) }}</strong>{{ $label }}</div>
        @endforeach
    </div>

    <div data-tabs="Integration records" data-tabs-key="admin-integrations">
    <section class="panel" data-tab="Connected systems" data-tab-count="{{ $clients->total() }}" aria-labelledby="clients-heading">
        <h2 id="clients-heading">Connected systems</h2>
        <p class="hint">Create or rotate clients on the server: <code>php artisan nsp:api-client</code>. Secrets are shown only once.</p>
        <form method="get" class="filters" role="search">
            <div class="form-group"><label for="q">Search clients</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Name or client ID"></div>
            <div class="form-group"><label for="client-status">Status</label><select id="client-status" name="client_status"><option value="">All</option><option value="active" @selected(request('client_status') === 'active')>Active</option><option value="disabled" @selected(request('client_status') === 'disabled')>Disabled</option></select></div>
            <div class="actions"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-secondary" href="{{ route('admin.integrations.index') }}">Clear</a></div>
        </form>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Connected systems">
            <table>
                <thead><tr><th scope="col">Client</th><th scope="col">System</th><th scope="col">Scopes</th><th scope="col">Webhook</th><th scope="col">Last used</th><th scope="col">Status</th></tr></thead>
                <tbody>
                @forelse ($clients as $client)
                    <tr>
                        <td>{{ $client->name }}<br><code>{{ $client->client_id }}</code></td>
                        <td>{{ $client->recipient_system }}</td>
                        <td>{{ implode(', ', $client->scopes ?? []) }}</td>
                        <td>@if ($client->webhook_url){{ $client->webhook_url }}@else<span class="meta">None</span>@endif</td>
                        <td>{{ $client->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                        <td><span class="badge badge-{{ $client->is_active ? 'on' : 'off' }}">{{ $client->is_active ? 'Active' : 'Disabled' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No connected systems yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $clients->links() }}
    </section>

    <section class="panel" data-tab="Delivery problems" data-tab-count="{{ $problems->total() }}" aria-labelledby="problems-heading">
        <h2 id="problems-heading">Deliveries with problems</h2>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Deliveries with problems">
            <table>
                <thead><tr><th scope="col">Event</th><th scope="col">To</th><th scope="col">Attempts</th><th scope="col">Last error</th><th scope="col">Next try</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                @forelse ($problems as $delivery)
                    <tr>
                        <td><code>{{ $delivery->event?->type }}</code><br><span class="meta">{{ $delivery->event?->occurred_at?->format('j M Y H:i') }}</span></td>
                        <td>{{ $delivery->client?->name }}</td>
                        <td>{{ $delivery->attempts }}</td>
                        <td>{{ $delivery->last_error }}</td>
                        <td>@if ($delivery->status === 'dead')Stopped @elseif ($delivery->next_attempt_at){{ $delivery->next_attempt_at->diffForHumans() }}@else<span class="meta">Not scheduled</span>@endif</td>
                        <td>
                            <form method="post" action="{{ route('admin.integrations.replay', $delivery) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">Send again</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No problems.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $problems->links() }}
    </section>

    <section class="panel" data-tab="Incoming events" data-tab-count="{{ $inbox->total() }}" aria-labelledby="inbox-heading">
        <h2 id="inbox-heading">Incoming events</h2>
        <nav class="filters" aria-label="Filter incoming events">
            <a class="tag" href="{{ route('admin.integrations.index') }}" @if (request('inbox') !== 'rejected') aria-current="true" @endif>All</a>
            <a class="tag" href="{{ route('admin.integrations.index', ['inbox' => 'rejected']) }}" @if (request('inbox') === 'rejected') aria-current="true" @endif>Rejected</a>
        </nav>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Incoming events">
            <table>
                <thead><tr><th scope="col">Received</th><th scope="col">From</th><th scope="col">Type</th><th scope="col">Startup</th><th scope="col">Result</th></tr></thead>
                <tbody>
                @forelse ($inbox as $event)
                    <tr>
                        <td>{{ $event->received_at?->format('j M Y H:i') }}</td>
                        <td>@if ($event->client){{ $event->client->name }}@else<span class="meta">Unknown</span>@endif</td>
                        <td><code>{{ $event->type }}</code></td>
                        <td>@if ($event->subject_uuid)<a href="{{ route('admin.startups.show', $event->subject_uuid) }}">{{ \Illuminate\Support\Str::limit($event->subject_uuid, 13) }}</a>@else<span class="meta">None</span>@endif</td>
                        <td><span class="badge badge-{{ $event->status === 'processed' ? 'on' : 'off' }}">{{ $event->status }}</span> {{ $event->error }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No events yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $inbox->links() }}
    </section>
    </div>
@endsection
