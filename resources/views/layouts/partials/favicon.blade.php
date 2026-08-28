{{-- Favicon de pestaña: solo PNG circular (SE en círculo blanco). /favicon.ico lo sirve public/favicon.ico (regenerado con tools/generate-pwa-icons.php). --}}
@php
    $seFaviconPng = \App\Support\Pwa\PwaIdentity::iconAbsoluto('favicon-32.png');
@endphp
<link rel="icon" type="image/png" sizes="32x32" href="{{ $seFaviconPng }}">
<link rel="shortcut icon" type="image/png" href="{{ $seFaviconPng }}">
