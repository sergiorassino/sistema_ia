@php
    $pwaPortal = \App\Support\Pwa\PwaIdentity::portalDesdeContexto($guestPortal ?? null);
@endphp
{{-- Metadatos para se-pwa.js (SW, push). El favicon/manifiesto van en favicon.blade.php. --}}
<meta name="pwa-scope" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('') }}">
<meta name="pwa-manifest-scope" content="{{ \App\Support\Pwa\PwaIdentity::scopeAbsoluto($pwaPortal) }}">
<meta name="pwa-install-url" content="{{ \App\Support\Pwa\PwaIdentity::urlLoginPrefijado($pwaPortal) }}">
<meta name="pwa-sw-url" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('sw.js') }}">
<meta name="pwa-base" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('notificaciones-push') }}/">
<meta name="vapid-public-key" content="{{ config('push.vapid.public_key') }}">
