{{-- Background video for a page section (decorative, muted, looping). Paused for people who prefer reduced motion (nsp.js). --}}
@if (! empty($bg['video']))
    <video class="section-bg-video" autoplay muted loop playsinline preload="metadata" aria-hidden="true" tabindex="-1" data-bg-video>
        <source src="{{ $bg['video'] }}" type="{{ $bg['type'] }}">
    </video>
@endif
