/**
 * Service Worker - Notificaciones Push (autogestión)
 */
const CACHE_NAME = 'autogestion-push-v1';
const NOTIFICATION_ICON_PATH = '/img/favicon-se-light.svg';

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll([
                './',
                './manifest.json',
                './js/pwa-register.js',
                NOTIFICATION_ICON_PATH,
            ]).catch(function () {});
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k !== CACHE_NAME; }).map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.pathname.indexOf('/notificaciones-push/api/') !== -1) {
        return;
    }
    event.respondWith(
        fetch(event.request).catch(function () {
            return caches.match(event.request);
        })
    );
});

self.addEventListener('push', function (event) {
    let data = { title: 'Notificación', body: '', url: './' };
    if (event.data) {
        try {
            data = Object.assign(data, event.data.json());
        } catch (e) {
            data.body = event.data.text();
        }
    }
    const opts = {
        body: data.body,
        icon: NOTIFICATION_ICON_PATH,
        tag: data.tag || 'autogestion',
        data: { url: data.url || './' },
    };
    event.waitUntil(self.registration.showNotification(data.title, opts));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url ? event.notification.data.url : './';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            for (let i = 0; i < list.length; i++) {
                if (list[i].url.indexOf(self.registration.scope) !== -1) {
                    list[i].focus();
                    list[i].navigate(url);
                    return;
                }
            }
            if (clients.openWindow) {
                clients.openWindow(url);
            }
        })
    );
});

