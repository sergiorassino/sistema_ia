{{-- Favicon de solapa + iconos PWA: mismo partial en login y menú (patrón SILAVET). --}}
@php
    $pwaPortal = \App\Support\Pwa\PwaIdentity::portalDesdeContexto($guestPortal ?? null);
    $seAppTitle = \App\Support\Pwa\PwaIdentity::nombreCortoApp($pwaPortal);
@endphp
<link rel="icon" href="{{ seAssetVersioned('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ seAssetVersioned('img/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ seAssetVersioned('img/icon-se-192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ seAssetVersioned('img/icon-se-512.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ seAssetVersioned('img/apple-touch-icon-se.png') }}">
<link rel="manifest" href="{{ route('pwa.manifest', ['portal' => $pwaPortal]) }}">
<meta name="theme-color" content="#40848D">
<meta name="msapplication-TileColor" content="#40848D">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $seAppTitle }}">
<meta name="application-name" content="{{ $seAppTitle }}">
