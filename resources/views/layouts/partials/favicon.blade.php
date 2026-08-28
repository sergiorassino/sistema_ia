{{-- Favicon de pestaña: PNG + ICO del tenant (sin sizes="any": Chrome lo prioriza mal). --}}
@php
    $seFaviconPng = \App\Support\Pwa\PwaIdentity::iconAbsoluto('favicon-32.png');
    $seFaviconIco = \App\Support\Pwa\PwaIdentity::faviconIcoAbsoluto();
@endphp
<link rel="icon" type="image/png" sizes="32x32" href="{{ $seFaviconPng }}">
<link rel="icon" type="image/x-icon" sizes="32x32" href="{{ $seFaviconIco }}">
<link rel="shortcut icon" type="image/png" href="{{ $seFaviconPng }}">
