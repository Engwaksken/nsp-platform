{{--
    A card that opens and closes: a native <details>, so it works without JavaScript.
    The header (<summary>) always shows the optional icon, the title, an optional badge and an
    optional one-line summary; the slot (details and actions) shows when the card is open.
    Put it inside <x-card-grid> for the 4-per-row layout and the Expand all / Collapse all buttons.

    Props:
      title      Card title (required). Rendered as a heading (see "level").
      id         Optional element id. Needed for the grid's "remember" option and for links to the card.
      level      Heading level of the title (default 3).
      icon       Optional Font Awesome icon name, e.g. "fa-share-nodes".
      badge      Optional badge text; badgeTone picks the colour class: on, off, draft, scheduled, new…
      summary    Optional one-line summary shown under the title, open or closed.
      open       Start open (true) or closed (default false). A state the visitor saved with the
                 grid's "remember" option wins over this.
      attention  Start open whatever was remembered, with a highlighted border: use it when the card
                 holds a validation error or a message about it, or needs the visitor's action.
    Named slots "badge" and "summary" can replace the props when they need markup.

    Example:
      <x-collapsible-card id="purpose-ims" title="Prefill applications" icon="fa-share-nodes"
                          badge="On" badge-tone="on" summary="On since 3 Jun 2026" :attention="$hasError">
          <p>Details…</p>
          <form>…</form>
      </x-collapsible-card>
--}}
@props(['title', 'id' => null, 'level' => 3, 'icon' => null, 'badge' => null, 'badgeTone' => null, 'summary' => null, 'open' => false, 'attention' => false])
@php($level = max(2, min(6, (int) $level)))
<details {{ $attributes->class(['card', 'collapsible-card', 'needs-attention' => $attention]) }} @if ($id) id="{{ $id }}" data-card-id="{{ $id }}" @endif @if ($open || $attention) open @endif @if ($attention) data-card-attention @endif>
    {{-- <summary> may hold phrasing content plus one heading, so the parts are siblings laid out by CSS grid. --}}
    <summary class="collapsible-card-head">
        @if ($icon)<span class="collapsible-card-icon" aria-hidden="true"><i class="fas {{ $icon }}"></i></span>@endif
        <h{{ $level }} class="collapsible-card-title"@if ($id) id="{{ $id }}-title"@endif><span>{{ $title }}</span>
            @if ($badge instanceof \Illuminate\View\ComponentSlot)
                {{ $badge }}
            @elseif (filled($badge))
                <span class="badge{{ $badgeTone ? ' badge-'.$badgeTone : '' }}">{{ $badge }}</span>
            @endif
        </h{{ $level }}>
        @if (filled($summary instanceof \Illuminate\View\ComponentSlot ? trim((string) $summary) : $summary))
            <span class="collapsible-card-summary">{{ $summary }}</span>
        @endif
        <span class="collapsible-card-chevron" aria-hidden="true"></span>
    </summary>
    <div class="collapsible-card-body">
        {{ $slot }}
    </div>
</details>
