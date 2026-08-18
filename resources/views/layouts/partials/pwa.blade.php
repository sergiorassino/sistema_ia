<link rel="manifest" href="{{ \App\Support\Pwa\PwaIdentity::rootPath('manifest.webmanifest') }}">
<meta name="theme-color" content="#40848D">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ \App\Support\Pwa\PwaIdentity::nombreCorto() }}">
<link rel="apple-touch-icon" href="{{ \App\Support\Pwa\PwaIdentity::rootPath('pwa-icon/180.png') }}">
<meta name="pwa-scope" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('') }}">
<meta name="pwa-sw-url" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('sw.js') }}">
<meta name="pwa-base" content="{{ \App\Support\Pwa\PwaIdentity::rootPath('notificaciones-push') }}/">
<meta name="vapid-public-key" content="{{ config('push.vapid.public_key') }}">
