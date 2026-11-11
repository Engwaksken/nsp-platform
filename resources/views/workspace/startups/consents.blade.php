@extends('layouts.workspace', ['pageTitle' => 'Data sharing · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    @php($icons = ['ims' => 'fa-file-import', 'nsp' => 'fa-address-book', 'investors' => 'fa-sack-dollar', 'hive' => 'fa-chart-pie'])
    @php($tones = ['ims' => 'tone-blue', 'nsp' => 'tone-green', 'investors' => 'tone-amber', 'hive' => 'tone-purple'])
    <div class="container section">
        <h1>Data sharing · {{ $startup->display_name }}</h1>
        @include('partials.flash')
        <p>Choose what Hive may do with your startup’s profile. Nothing is shared until you turn it on, and turning it off stops sharing from that moment.
            @unless ($membership->canManageConsent()) Only owners and founders can change these choices. @endunless</p>

        {{-- Closed by default: the header shows each choice and whether it is on. The card just changed
             (or that failed) opens, because the controller flashes its purpose with the message. --}}
        <x-card-grid label="Sharing choices" remember="consents">
            @foreach (config('nsp.registry.consent_purposes') as $purpose => $definition)
                @php($consent = $active[$purpose])
                <x-collapsible-card
                    id="purpose-{{ $purpose }}"
                    :title="$definition['label']"
                    :icon="$icons[$definition['recipient'] ?? ''] ?? 'fa-share-nodes'"
                    class="{{ $tones[$definition['recipient'] ?? ''] ?? '' }}"
                    :badge="$consent ? 'On' : 'Off'"
                    :badge-tone="$consent ? 'on' : 'off'"
                    :summary="$consent ? 'On since '.$consent->granted_at->format('j M Y') : 'Not shared'"
                    :attention="session('consent_purpose') === $purpose">
                    <p>{{ $definition['text'] }}</p>
                    @if ($consent)
                        <p class="meta">Turned on by {{ $consent->granter?->name ?? 'a team member' }} on {{ $consent->granted_at->format('j M Y') }} (wording version {{ $consent->policy_version }}).</p>
                    @endif
                    @if ($membership->canManageConsent())
                        <form method="post" action="{{ route('workspace.startups.consents.update', [$startup, $purpose]) }}"
                              @if ($consent) data-confirm="Turn off “{{ $definition['label'] }}”? Sharing stops from now on." @endif>
                            @csrf @method('PUT')
                            <input type="hidden" name="grant" value="{{ $consent ? 0 : 1 }}">
                            <button type="submit" class="btn {{ $consent ? 'btn-secondary' : 'btn-primary' }}">{{ $consent ? 'Turn off' : 'Turn on' }}<span class="visually-hidden">: {{ $definition['label'] }}</span></button>
                        </form>
                    @endif
                </x-collapsible-card>
            @endforeach
        </x-card-grid>

        @if ($history->isNotEmpty())
            <section aria-labelledby="history-heading">
                <h2 id="history-heading">History</h2>
                <div class="table-wrap" tabindex="0" role="region" aria-label="Data sharing history">
                    <table>
                        <thead><tr><th scope="col">Sharing choice</th><th scope="col">Turned on</th><th scope="col">Turned off</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                        @foreach ($history as $entry)
                            <tr>
                                <td><strong>{{ config('nsp.registry.consent_purposes.'.$entry->purpose.'.label', $entry->purpose) }}</strong></td>
                                <td>{{ $entry->granted_at->format('j M Y') }}<br><span class="meta">by {{ $entry->granter?->name ?? 'an unknown user' }}</span></td>
                                <td>@if ($entry->revoked_at){{ $entry->revoked_at->format('j M Y') }}<br><span class="meta">by {{ $entry->revoker?->name ?? 'an unknown user' }}</span>@else<span class="meta">Still on</span>@endif</td>
                                <td><span class="badge badge-{{ $entry->revoked_at ? 'off' : 'on' }}">{{ $entry->revoked_at ? 'Ended' : 'Active' }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
