@php
    $pwaPortal = \App\Support\Pwa\PwaIdentity::portalDesdeContexto($guestPortal ?? null);
    $incluirManifiestoPwa = $incluirManifiestoPwa ?? true;
@endphp
@if ($incluirManifiestoPwa)
    {{-- Solo login / páginas públicas: manifiesto + metadatos de instalación. --}}
    <link rel="apple-touch-icon" sizes="180x180" href="{{ \App\Support\Pwa\PwaIdentity::iconAbsoluto('apple-touch-icon-se.png') }}">
    <link rel="manifest" href="{{ route('pwa.manifest', ['portal' => $pwaPortal]) }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ \App\Support\Pwa\PwaIdentity::nombreCortoApp($pwaPortal) }}">
    <meta name="application-name" content="{{ \App\Support\Pwa\PwaIdentity::nombreCortoApp($pwaPortal) }}">
@endif
<meta name="theme-color" content="#40848D">
<meta name="msapplication-TileColor" content="#40848D">
<meta name="pwa-scope" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('') }}">
<meta name="pwa-manifest-scope" content="{{ \App\Support\Pwa\PwaIdentity::scopeAbsoluto($pwaPortal) }}">
<meta name="pwa-install-url" content="{{ \App\Support\Pwa\PwaIdentity::urlLoginPrefijado($pwaPortal) }}">
<meta name="pwa-sw-url" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('sw.js') }}">
<meta name="pwa-base" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('notificaciones-push') }}/">
<meta name="vapid-public-key" content="{{ config('push.vapid.public_key') }}">
