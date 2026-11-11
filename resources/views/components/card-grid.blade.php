{{--
    A responsive grid of cards: 4 per row on wide screens, then 3, 2 and 1 as space runs out
    (CSS "Card grid and collapsible cards" in public/css/nsp.css).

    Props:
      label     Accessible name for the Expand all / Collapse all group (default "Cards").
      min       Narrowest a card may get before the grid drops a column (CSS length, default 220px).
      columns   Most cards per row (default 4).
      stretch   true: cards in a row share one height (plain link cards). Default false: each card
                keeps its own height, so opening one collapsible card does not stretch its row-mates.
      controls  false hides the Expand all / Collapse all buttons. They are rendered hidden and
                public/js/nsp.js shows them only when JavaScript runs and the grid holds collapsible cards.
      remember  A short key: public/js/nsp.js remembers which cards each visitor opened on this page
                (per path, in localStorage). Cards need an id for this. Omit to not remember.

    Example:
      <x-card-grid label="Sharing choices" remember="consents">
          <x-collapsible-card id="purpose-x" title="…">…</x-collapsible-card>
      </x-card-grid>
--}}
@props(['label' => 'Cards', 'min' => null, 'columns' => null, 'stretch' => false, 'controls' => true, 'remember' => null])
@php
    $style = collect([$min ? '--card-min: '.$min : null, $columns ? '--card-cols: '.(int) $columns : null])->filter()->join('; ');
@endphp
<div {{ $attributes->class(['card-grid-wrap']) }} data-card-grid @if ($remember) data-card-grid-key="{{ $remember }}" @endif>
    @if ($controls)
        <div class="card-grid-toolbar" role="group" aria-label="{{ $label }}" hidden>
            <button type="button" class="btn btn-secondary btn-sm" data-cards-expand><i class="fas fa-angles-down" aria-hidden="true"></i> Expand all</button>
            <button type="button" class="btn btn-secondary btn-sm" data-cards-collapse><i class="fas fa-angles-up" aria-hidden="true"></i> Collapse all</button>
        </div>
    @endif
    <div @class(['card-grid', 'card-grid-stretch' => $stretch]) @if ($style) style="{{ $style }}" @endif>
        {{ $slot }}
    </div>
</div>
