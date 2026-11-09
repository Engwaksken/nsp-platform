{{--
    Row of statistics cards with Font Awesome icons.
    @include('partials.stats', ['stats' => [
        ['icon' => 'fa-users', 'label' => 'Team members', 'value' => 4, 'url' => route(...), 'tone' => 'blue', 'note' => '1 invited'],
    ], 'label' => 'Key figures'])
    icon: a Font Awesome solid icon name (fas). tone: orange (default), blue, green, purple, amber, red, grey.
    url and note are optional; numbers are formatted, other values are shown as given.
--}}
@php($stats = array_values(array_filter($stats ?? [])))
@if ($stats)
    <section class="stat-cards" aria-label="{{ $label ?? 'Key figures' }}">
        @foreach ($stats as $stat)
            @php($tag = ! empty($stat['url']) ? 'a' : 'div')
            <{{ $tag }} class="stat-card stat-{{ $stat['tone'] ?? 'orange' }}" @if (! empty($stat['url'])) href="{{ $stat['url'] }}" @endif>
                <span class="stat-icon" aria-hidden="true"><i class="fas {{ $stat['icon'] }}"></i></span>
                <span class="stat-body">
                    <span class="stat-value">{{ is_int($stat['value']) || is_float($stat['value']) ? number_format($stat['value']) : $stat['value'] }}</span>
                    <span class="stat-label">{{ $stat['label'] }}</span>
                    @if (! empty($stat['note']))<span class="stat-note">{{ $stat['note'] }}</span>@endif
                </span>
            </{{ $tag }}>
        @endforeach
    </section>
@endif
