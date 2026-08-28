{{-- Favicon de solapa + iconos PWA: mismo partial en login y menú (patrón SILAVET). --}}
{{-- se-favicon-partial: silavet-asset-v1 — si no aparece en Ver código fuente del menú, falta view:clear o subir vistas. --}}
@php
    $pwaPortal = \App\Support\Pwa\PwaIdentity::portalDesdeContexto($guestPortal ?? null);
    $seAppTitle = \App\Support\Pwa\PwaIdentity::nombreCortoApp($pwaPortal);
@endphp
<link rel="icon" href="{{ seAssetVersioned('favicon.ico') }}" sizes="any" data-navigate-track="reload">
<link rel="icon" type="image/png" sizes="32x32" href="{{ seAssetVersioned('img/favicon-32.png') }}" data-navigate-track="reload">
<link rel="icon" type="image/png" sizes="192x192" href="{{ seAssetVersioned('img/icon-se-192.png') }}" data-navigate-track="reload">
<link rel="icon" type="image/png" sizes="512x512" href="{{ seAssetVersioned('img/icon-se-512.png') }}" data-navigate-track="reload">
<link rel="apple-touch-icon" sizes="180x180" href="{{ seAssetVersioned('img/apple-touch-icon-se.png') }}" data-navigate-track="reload">
<link rel="manifest" href="{{ route('pwa.manifest', ['portal' => $pwaPortal]) }}" data-navigate-track="reload">
<meta name="theme-color" content="#40848D">
<meta name="msapplication-TileColor" content="#40848D">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $seAppTitle }}">
<meta name="application-name" content="{{ $seAppTitle }}">
