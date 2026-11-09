@extends('layouts.workspace', ['pageTitle' => 'Data sharing · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section narrow">
        <h1>Data sharing · {{ $startup->display_name }}</h1>
        @include('partials.flash')
        <p>Choose what Hive may do with your startup’s profile. Nothing is shared until you turn it on, and turning it off stops sharing from that moment.
            @unless ($membership->canManageConsent()) Only owners and founders can change these choices. @endunless</p>

        @foreach (config('nsp.registry.consent_purposes') as $purpose => $definition)
            @php($consent = $active[$purpose])
            <section class="panel" aria-labelledby="purpose-{{ $purpose }}">
                <h2 id="purpose-{{ $purpose }}" class="h3">{{ $definition['label'] }}
                    <span class="badge badge-{{ $consent ? 'on' : 'off' }}">{{ $consent ? 'On' : 'Off' }}</span></h2>
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
            </section>
        @endforeach

        @if ($history->isNotEmpty())
            <h2>History</h2>
            <ul>
                @foreach ($history as $entry)
                    <li>{{ config('nsp.registry.consent_purposes.'.$entry->purpose.'.label', $entry->purpose) }}: on {{ $entry->granted_at->format('j M Y') }} by {{ $entry->granter?->name ?? '—' }}
                        @if ($entry->revoked_at), off {{ $entry->revoked_at->format('j M Y') }} by {{ $entry->revoker?->name ?? '—' }}@endif</li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
