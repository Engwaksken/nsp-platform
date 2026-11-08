{{-- Shared <head> contents. Expects $brand, $brandFont, $brandFavicon from the layout view composer. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ trim(($pageTitle ?? '') !== '' ? $pageTitle . ' | ' . $brand['site_name'] : $brand['site_name']) }}</title>
@if (!empty($metaDescription ?? $brand['meta_description']))
    <meta name="description" content="{{ $metaDescription ?? $brand['meta_description'] }}">
@endif
@isset($canonical)
    <link rel="canonical" href="{{ $canonical }}">
@endisset
<meta property="og:site_name" content="{{ $brand['site_name'] }}">
<meta property="og:title" content="{{ $pageTitle ?? $brand['site_name'] }}">
@isset($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
@endisset
@if ($brandFavicon)
    <link rel="icon" href="{{ $brandFavicon->url() }}">
@endif
@if (in_array($brand['font_family'], ['Poppins', 'Outfit'], true))
    {{-- Brand fonts are self-hosted (public/fonts) so pages make no third-party requests. --}}
    <link rel="preload" href="{{ asset('fonts/'.strtolower($brand['font_family']).'/'.strtolower($brand['font_family']).($brand['font_family'] === 'Outfit' ? '' : '-400').'-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v=1">
@endif
<link rel="stylesheet" href="{{ asset('css/palette-hive.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('css/nsp.css') }}?v=7">
<style>
    :root {
        --brand-primary: {{ $brand['primary_color'] }};
        --brand-secondary: {{ $brand['secondary_color'] }};
        --brand-accent: {{ $brand['accent_color'] }};
        {{-- From the fixed Settings::FONTS list; e() would turn the quotes into &#039; and break the CSS. --}}
        --brand-font: {!! str_replace(['<', '>', '{', '}', ';'], '', $brandFont) !!};
    }
</style>
<script src="{{ asset('js/nsp.js') }}?v=4" defer></script>
