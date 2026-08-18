<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="#40848D">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ \App\Support\Pwa\PwaIdentity::nombreCorto() }}">
<link rel="apple-touch-icon" href="{{ route('pwa.icon', ['size' => 180]) }}">
<meta name="pwa-scope" content="{{ \App\Support\Pwa\PwaIdentity::baseUrl() }}">
<meta name="pwa-sw-url" content="{{ asset('sw.js') }}">
<meta name="pwa-base" content="{{ url('/notificaciones-push') }}/">
<meta name="vapid-public-key" content="{{ config('push.vapid.public_key') }}">
