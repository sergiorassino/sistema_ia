@php
    $pwaPortal = \App\Support\Pwa\PwaIdentity::portalDesdeContexto($guestPortal ?? null);
@endphp
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/icon-192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('img/icon-512.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ route('pwa.manifest', ['portal' => $pwaPortal]) }}">
<meta name="theme-color" content="#40848D">
<meta name="msapplication-TileColor" content="#40848D">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ \App\Support\Pwa\PwaIdentity::nombreCortoApp($pwaPortal) }}">
<meta name="application-name" content="{{ \App\Support\Pwa\PwaIdentity::nombreCortoApp($pwaPortal) }}">
<meta name="pwa-scope" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('') }}">
<meta name="pwa-sw-url" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('sw.js') }}">
<meta name="pwa-base" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('notificaciones-push') }}/">
<meta name="vapid-public-key" content="{{ config('push.vapid.public_key') }}">
