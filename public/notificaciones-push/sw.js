/**
 * SW legado (alcance /notificaciones-push/). Se auto-desregistra: el push vive en /sw.js.
 */
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        self.registration.unregister().then(function () {
            return self.clients.matchAll();
        }).then(function (list) {
            list.forEach(function (client) {
                if (client && typeof client.navigate === 'function') {
                    client.navigate(client.url);
                }
            });
        })
    );
});
