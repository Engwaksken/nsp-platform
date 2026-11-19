{{-- The site logo (or name) at the top of sign-in, sign-up and other account forms. --}}
<a class="auth-brand" href="{{ route('home') }}">
    @if (! empty($brandLogo))
        <img src="{{ $brandLogo->url() }}" alt="{{ $brandLogo->alt_text ?: $brand['site_name'] }}" decoding="async">
    @else
        <span>{{ $brand['site_name'] ?? config('app.name') }}</span>
    @endif
</a>
