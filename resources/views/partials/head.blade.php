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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ $brand['font_family'] }}:wght@400;500;600;700&display=swap">
@endif
<link rel="stylesheet" href="{{ asset('css/palette-hive.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('css/nsp.css') }}?v=3">
<style>
    :root {
        --brand-primary: {{ $brand['primary_color'] }};
        --brand-secondary: {{ $brand['secondary_color'] }};
        --brand-accent: {{ $brand['accent_color'] }};
        --brand-font: {!! e($brandFont) !!};
    }
</style>
<script src="{{ asset('js/nsp.js') }}?v=2" defer></script>
