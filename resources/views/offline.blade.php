{{-- Shown by the service worker when a phone has no connection. Kept free of personal data because it is cached. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>You are offline | {{ $brand['site_name'] }}</title>
    <script src="{{ asset('js/theme.js') }}?v=1"></script>
    <link rel="stylesheet" href="{{ asset('css/palette-hive.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/nsp.css') }}?v=28">
</head>
<body>
<main class="container section narrow" style="text-align:center;padding-top:20vh">
    <img src="{{ asset('icons/icon-192.png') }}?v=1" alt="" width="72" height="72" style="border-radius:18px">
    <h1>You are offline</h1>
    <p>{{ $brand['site_name'] }} needs an internet connection. Check your mobile data or Wi-Fi, then try again.</p>
    <p><a class="btn btn-primary" href="{{ route('home', [], false) }}">Try again</a></p>
</main>
</body>
</html>
