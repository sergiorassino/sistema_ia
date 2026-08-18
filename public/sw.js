/**
 * Service Worker del sistema (alcance: directorio de esta URL = APP_URL).
 * Push + fallback offline en navegaciones. No cachea HTML autenticado ni Livewire.
 */
const CACHE_NAME = 'se-pwa-v2';
const OFFLINE_URL = 'offline.html';

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll([
                new URL(OFFLINE_URL, self.registration.scope).href,
            ]).catch(function () {});
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) {
                return k !== CACHE_NAME;
            }).map(function (k) {
                return caches.delete(k);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    const req = event.request;
    if (req.method !== 'GET' || req.mode !== 'navigate') {
        return;
    }
    event.respondWith(
        fetch(req).catch(function () {
            return caches.match(new URL(OFFLINE_URL, self.registration.scope)).then(function (cached) {
                return cached || new Response('Sin conexión.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                });
            });
        })
    );
});

self.addEventListener('push', function (event) {
    let data = { title: 'Notificación', body: '', url: self.registration.scope };
    if (event.data) {
        try {
            data = Object.assign(data, event.data.json());
        } catch (e) {
            data.body = event.data.text();
        }
    }
    const icon = new URL('img/icon-se-192.png', self.registration.scope).href;
    const opts = {
        body: data.body || '',
        icon: icon,
        badge: icon,
        tag: data.tag || 'se-sistema',
        data: { url: data.url || self.registration.scope },
    };
    event.waitUntil(self.registration.showNotification(data.title || 'Notificación', opts));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : self.registration.scope;
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            const scope = self.registration.scope;
            for (let i = 0; i < list.length; i++) {
                if (list[i].url.indexOf(scope) !== -1 && typeof list[i].focus === 'function') {
                    list[i].focus();
                    if (typeof list[i].navigate === 'function') {
                        return list[i].navigate(url);
                    }
                    return undefined;
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
            return undefined;
        })
    );
});
