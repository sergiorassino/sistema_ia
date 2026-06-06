/**
 * Registro del Service Worker y suscripción a notificaciones push.
 */
(function () {
    'use strict';

    var base = document.querySelector('meta[name="pwa-base"]');
    var basePath = base ? base.getAttribute('content') : '';
    var scopeMeta = document.querySelector('meta[name="pwa-scope"]');
    var scope = scopeMeta ? scopeMeta.getAttribute('content') : (basePath || './');
    var swUrlMeta = document.querySelector('meta[name="pwa-sw-url"]');
    var swUrl = (swUrlMeta && swUrlMeta.getAttribute('content')) ? swUrlMeta.getAttribute('content') : (basePath || '') + 'sw.js';
    var vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
    var vapidKeyRaw = vapidMeta ? vapidMeta.getAttribute('content') : '';
    var vapidKey = (vapidKeyRaw && String(vapidKeyRaw).trim() !== '') ? String(vapidKeyRaw).trim() : null;

    /** Registration del SW bajo `scope` (p. ej. /notificaciones-push/). No usar solo `navigator.serviceWorker.ready` en páginas fuera de ese scope (p. ej. /alumnos/notificaciones). */
    var pushNotificationsRegistration = null;

    function resolveRegForPush() {
        if (pushNotificationsRegistration) {
            return Promise.resolve(pushNotificationsRegistration);
        }
        if (scope && navigator.serviceWorker.getRegistration) {
            return navigator.serviceWorker.getRegistration(scope).then(function (r) {
                if (r) {
                    pushNotificationsRegistration = r;
                    return r;
                }
                return navigator.serviceWorker.ready;
            });
        }
        return navigator.serviceWorker.ready;
    }

    window.studentPushUnsupportedReason = '';
    window.studentPushDiagnostics = function () {
        var isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.platform && /iPad|iPhone|iPod/.test(navigator.platform));
        var isStandalone = false;
        try {
            isStandalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || (navigator.standalone === true);
        } catch (e) {}

        return {
            secureContext: !!window.isSecureContext,
            hasServiceWorker: !!(navigator && navigator.serviceWorker),
            hasPushManager: !!('PushManager' in window),
            hasNotification: !!('Notification' in window),
            permission: ('Notification' in window) ? Notification.permission : 'n/a',
            isIOS: isIOS,
            isStandalone: isStandalone,
            swUrl: swUrl,
            scope: scope,
            vapidKeyPresent: !!(vapidKey && String(vapidKey).trim() !== ''),
        };
    };

    window.studentRequestPushPermission = function () {
        return Promise.resolve('unsupported');
    };

    if (!('serviceWorker' in navigator)) {
        if (typeof window.studentShowPushStatus === 'function') {
            var isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.platform && /iPad|iPhone|iPod/.test(navigator.platform));
            var msg = isIOS
                ? 'En iPhone/iPad agregá esta página al Inicio (Compartir → Agregar a Inicio) y abrila desde el ícono; las notificaciones no funcionan en Safari en una pestaña normal.'
                : 'Las notificaciones push requieren HTTPS y un navegador que soporte Service Worker. Probá desde una URL que empiece con https://';
            window.studentShowPushStatus(msg, 'error');
        }
        return;
    }

    function show(msg, type) {
        if (typeof window.studentShowPushStatus === 'function') {
            window.studentShowPushStatus(msg, type || 'error');
        }
        console.warn('[PWA]', msg);
    }

    function markUnsupported(reason) {
        window.studentPushUnsupportedReason = reason || '';
        show(reason || 'Tu navegador no soporta notificaciones push en este contexto.', 'error');
        return Promise.resolve('unsupported');
    }

    navigator.serviceWorker.register(swUrl, { scope: scope || './' })
        .then(function (reg) {
            pushNotificationsRegistration = reg;
            window.dispatchEvent(new CustomEvent('pwa-sw-registered', { detail: reg }));
            return checkPushSupport(reg);
        })
        .catch(function (err) {
            show('Service Worker: ' + (err.message || String(err)), 'error');
        });

    function checkPushSupport(reg) {
        if (!('Notification' in window)) {
            return markUnsupported('Este navegador/contexto no expone la API Notification (no se puede pedir permiso).');
        }
        if (Notification.permission === 'denied') {
            show('Permiso de notificaciones denegado. Habilitalo desde la configuración del navegador.', 'error');
            return Promise.resolve();
        }
        if (!('PushManager' in window)) {
            var d = window.studentPushDiagnostics ? window.studentPushDiagnostics() : {};
            if (d && d.isIOS && !d.isStandalone) {
                return markUnsupported('En iPhone/iPad las notificaciones push requieren abrir la app desde “Agregar a inicio”.');
            }
            return markUnsupported('Este navegador no soporta PushManager (push web) en este contexto.');
        }

        if (!vapidKey) {
            show('Falta VAPID_PUBLIC_KEY en la configuración del servidor.', 'error');
            return Promise.resolve();
        }

        return reg.pushManager.getSubscription().then(function (sub) {
            if (sub) {
                sendSubscriptionToServer(sub, basePath);
                return;
            }
            // Importante: no llamar a subscribeUser aquí aunque Notification.permission === 'granted'.
            // Tras "Desactivar", el permiso suele seguir granted pero la suscripción push no existe;
            // re-suscribir al refrescar recrearía el registro en BD sin que el usuario pulse Activar.
        });
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var arr = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; i++) arr[i] = rawData.charCodeAt(i);
        return arr;
    }

    function subscribeUser(reg, vapidKey, basePath) {
        reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey)
        }).then(function (sub) {
            sendSubscriptionToServer(sub, basePath);
        }).catch(function (e) {
            show('Suscripción push: ' + (e.message || String(e)));
        });
    }

    function sendSubscriptionToServer(subscription, basePath) {
        var endpoint = (basePath || '') + 'api/subscribe';
        var payload = subscription.toJSON();
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrf ? csrf.getAttribute('content') : '';

        function doSend() {
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            }).then(function (r) {
                if (!r.ok) {
                    return r.text().then(function (body) {
                        throw new Error(r.status + ' ' + (body || r.statusText));
                    });
                }
                if (typeof window.studentShowPushStatus === 'function') {
                    window.studentShowPushStatus('Notificaciones activadas para este dispositivo.', 'success');
                }
                try {
                    window.dispatchEvent(new CustomEvent('student-push-subscribed'));
                } catch (err) {}
            }).catch(function (e) {
                show('Servidor (guardar suscripción): ' + (e.message || String(e)));
            });
        }

        if (navigator.userAgentData && typeof navigator.userAgentData.getHighEntropyValues === 'function') {
            navigator.userAgentData.getHighEntropyValues(['brands', 'platform', 'mobile']).then(function (hints) {
                payload.client_hints = hints;
                doSend();
            }).catch(function () { doSend(); });
        } else {
            doSend();
        }
    }

    window.studentRequestPushPermission = function () {
        if (!('Notification' in window)) return markUnsupported('Este navegador/contexto no expone la API Notification.');
        if (Notification.permission === 'granted') return Promise.resolve('granted');
        if (Notification.permission === 'denied') return Promise.resolve('denied');
        return Notification.requestPermission().then(function (p) {
            if (p === 'granted') {
                resolveRegForPush().then(function (reg) {
                    if (!reg || !reg.pushManager) {
                        show('Service Worker de notificaciones no disponible.', 'error');
                        return;
                    }
                    if (!('PushManager' in window)) {
                        show('Este navegador no soporta Push (PushManager).', 'error');
                        return;
                    }
                    if (!vapidKey) {
                        show('Falta VAPID_PUBLIC_KEY en la configuración del servidor.', 'error');
                        return;
                    }
                    subscribeUser(reg, vapidKey, basePath || '');
                });
            }
            return p;
        });
    };

    // Si el usuario ya otorgó permiso, el click debe intentar suscribirse (sin depender del auto-check).
    try {
        window.studentRequestPushPermission = (function (prev) {
            return function () {
                if (!('Notification' in window)) return markUnsupported('Este navegador/contexto no expone la API Notification.');
                if (!('PushManager' in window)) {
                    var d = window.studentPushDiagnostics ? window.studentPushDiagnostics() : {};
                    if (d && d.isIOS && !d.isStandalone) {
                        return markUnsupported('En iPhone/iPad las notificaciones push requieren abrir la app desde “Agregar a inicio”.');
                    }
                    return markUnsupported('Este navegador no soporta PushManager (push web) en este contexto.');
                }
                if (!navigator.serviceWorker) {
                    return markUnsupported('Service Worker no disponible. Verificá HTTPS y que el navegador permita Service Workers.');
                }
                if (!vapidKey) {
                    show('Falta VAPID_PUBLIC_KEY en la configuración del servidor.', 'error');
                    return Promise.resolve('unsupported');
                }

                if (Notification.permission === 'granted') {
                    return resolveRegForPush().then(function (reg) {
                        if (!reg || !reg.pushManager) {
                            return markUnsupported('Service Worker de notificaciones no disponible.');
                        }
                        return reg.pushManager.getSubscription().then(function (sub) {
                            if (sub) {
                                sendSubscriptionToServer(sub, basePath || '');
                                show('Este dispositivo ya tiene notificaciones activadas.', 'success');
                                return 'granted';
                            }
                            subscribeUser(reg, vapidKey, basePath || '');
                            return 'granted';
                        });
                    }).catch(function (e) {
                        return markUnsupported('Service Worker no disponible: ' + (e && e.message ? e.message : String(e)));
                    });
                }

                return prev();
            };
        })(window.studentRequestPushPermission);
    } catch (e) {}
})();

