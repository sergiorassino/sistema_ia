/**
 * PWA: registro del service worker (alcance APP_URL), instalación y suscripción push.
 */
(function () {
    'use strict';

    var scopeMeta = document.querySelector('meta[name="pwa-scope"]');
    var scope = scopeMeta ? scopeMeta.getAttribute('content') : '';
    var swUrlMeta = document.querySelector('meta[name="pwa-sw-url"]');
    var swUrl = swUrlMeta ? swUrlMeta.getAttribute('content') : '';
    var baseMeta = document.querySelector('meta[name="pwa-base"]');
    var basePath = baseMeta ? baseMeta.getAttribute('content') : '';
    var vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
    var vapidKeyRaw = vapidMeta ? vapidMeta.getAttribute('content') : '';
    var vapidKey = (vapidKeyRaw && String(vapidKeyRaw).trim() !== '') ? String(vapidKeyRaw).trim() : null;

    var pushNotificationsRegistration = null;
    var deferredInstallPrompt = null;
    var INSTALL_DISMISS_KEY = 'se-pwa-install-dismissed';
    var INSTALL_DISMISS_MS = 30 * 24 * 60 * 60 * 1000;

    function isIos() {
        return /iPhone|iPad|iPod/i.test(navigator.userAgent)
            || (navigator.platform && /iPad|iPhone|iPod/.test(navigator.platform));
    }

    function isStandalone() {
        try {
            return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
                || (navigator.standalone === true);
        } catch (e) {
            return false;
        }
    }

    function showPush(msg, type) {
        if (typeof window.studentShowPushStatus === 'function') {
            window.studentShowPushStatus(msg, type || 'error');
        }
    }

    function markUnsupported(reason) {
        window.studentPushUnsupportedReason = reason || '';
        showPush(reason || 'Tu navegador no soporta notificaciones push en este contexto.', 'error');
        return Promise.resolve('unsupported');
    }

    window.studentPushUnsupportedReason = '';
    window.studentPushDiagnostics = function () {
        return {
            secureContext: !!window.isSecureContext,
            hasServiceWorker: !!(navigator && navigator.serviceWorker),
            hasPushManager: !!('PushManager' in window),
            hasNotification: !!('Notification' in window),
            permission: ('Notification' in window) ? Notification.permission : 'n/a',
            isIOS: isIos(),
            isStandalone: isStandalone(),
            swUrl: swUrl,
            scope: scope,
            vapidKeyPresent: !!(vapidKey && String(vapidKey).trim() !== ''),
        };
    };

    window.studentRequestPushPermission = function () {
        return Promise.resolve('unsupported');
    };

    function resolveRegForPush() {
        if (pushNotificationsRegistration) {
            return Promise.resolve(pushNotificationsRegistration);
        }
        if (scope && navigator.serviceWorker && navigator.serviceWorker.getRegistration) {
            return navigator.serviceWorker.getRegistration(scope).then(function (r) {
                if (r) {
                    pushNotificationsRegistration = r;
                    return r;
                }
                return navigator.serviceWorker.ready;
            });
        }
        if (navigator.serviceWorker) {
            return navigator.serviceWorker.ready;
        }
        return Promise.reject(new Error('Service Worker no disponible'));
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var arr = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; i++) arr[i] = rawData.charCodeAt(i);
        return arr;
    }

    function sendSubscriptionToServer(subscription, apiBase) {
        var endpoint = (apiBase || '') + 'api/subscribe';
        var payload = subscription.toJSON();
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrf ? csrf.getAttribute('content') : '';

        function doSend() {
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            }).then(function (r) {
                if (!r.ok) {
                    return r.text().then(function (body) {
                        throw new Error(r.status + ' ' + (body || r.statusText));
                    });
                }
                showPush('Notificaciones activadas para este dispositivo.', 'success');
                try {
                    window.dispatchEvent(new CustomEvent('student-push-subscribed'));
                } catch (err) {}
            }).catch(function (e) {
                showPush('Servidor (guardar suscripción): ' + (e.message || String(e)));
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

    function subscribeUser(reg, key, apiBase) {
        reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(key),
        }).then(function (sub) {
            sendSubscriptionToServer(sub, apiBase);
        }).catch(function (e) {
            showPush('Suscripción push: ' + (e.message || String(e)));
        });
    }

    function bindPushHelpers() {
        window.studentRequestPushPermission = function () {
            if (!('Notification' in window)) {
                return markUnsupported('Este navegador/contexto no expone la API Notification.');
            }
            if (!('PushManager' in window)) {
                if (isIos() && !isStandalone()) {
                    return markUnsupported('En iPhone/iPad las notificaciones push requieren abrir la app desde “Agregar a inicio”.');
                }
                return markUnsupported('Este navegador no soporta PushManager (push web) en este contexto.');
            }
            if (!navigator.serviceWorker) {
                return markUnsupported('Service Worker no disponible. Verificá HTTPS y que el navegador permita Service Workers.');
            }
            if (!vapidKey) {
                showPush('Falta VAPID_PUBLIC_KEY en la configuración del servidor.', 'error');
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
                            showPush('Este dispositivo ya tiene notificaciones activadas.', 'success');
                            return 'granted';
                        }
                        subscribeUser(reg, vapidKey, basePath || '');
                        return 'granted';
                    });
                }).catch(function (e) {
                    return markUnsupported('Service Worker no disponible: ' + (e && e.message ? e.message : String(e)));
                });
            }

            if (Notification.permission === 'denied') {
                return Promise.resolve('denied');
            }

            return Notification.requestPermission().then(function (p) {
                if (p === 'granted') {
                    resolveRegForPush().then(function (reg) {
                        if (!reg || !reg.pushManager || !vapidKey) {
                            showPush('Service Worker de notificaciones no disponible.', 'error');
                            return;
                        }
                        subscribeUser(reg, vapidKey, basePath || '');
                    });
                }
                return p;
            });
        };
    }

    function asSameOriginPath(value) {
        if (!value) return '';
        try {
            if (/^https?:\/\//i.test(value)) {
                var u = new URL(value);
                return u.pathname + (String(value).endsWith('/') && !u.pathname.endsWith('/') ? '/' : '');
            }
        } catch (e) {}
        return value;
    }

    function pageWantsInstallHint() {
        var path = window.location.pathname || '';
        return /loginUsuario|loginEstudiante|\/entrar\/?$|notificaciones/i.test(path);
    }

    function installDismissedRecently() {
        try {
            var raw = localStorage.getItem(INSTALL_DISMISS_KEY);
            if (!raw) return false;
            var ts = parseInt(raw, 10);
            if (!ts) return false;
            return (Date.now() - ts) < INSTALL_DISMISS_MS;
        } catch (e) {
            return false;
        }
    }

    function dismissInstall() {
        try {
            localStorage.setItem(INSTALL_DISMISS_KEY, String(Date.now()));
        } catch (e) {}
        var el = document.getElementById('se-pwa-install');
        if (el) el.remove();
    }

    function installInstructionsMessage() {
        if (isIos()) {
            return 'En Safari, tocá Compartir (el cuadrado con flecha) y después Agregar a inicio. Luego abrí el sistema desde ese icono.';
        }
        return 'En el menú del navegador (⋮ o ⋯) elegí Instalar aplicación. En la computadora también puede aparecer un icono de instalar en la barra de direcciones.';
    }

    function tryNativeInstall() {
        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            return deferredInstallPrompt.userChoice.finally(function () {
                deferredInstallPrompt = null;
                dismissInstall();
            });
        }
        if (typeof window.seSwalAviso === 'function') {
            return window.seSwalAviso(installInstructionsMessage(), 'Instalar');
        }
        window.alert(installInstructionsMessage());
        return Promise.resolve();
    }

    window.sePwaTryInstall = tryNativeInstall;

    function revealInlineInstallButtons() {
        if (isStandalone()) return;
        document.documentElement.classList.add('se-pwa-can-install');
    }

    function ensureInstallBanner() {
        if (document.getElementById('se-pwa-install')) {
            return document.getElementById('se-pwa-install');
        }
        var wrap = document.createElement('div');
        wrap.id = 'se-pwa-install';
        wrap.className = 'se-pwa-install';
        wrap.setAttribute('role', 'region');
        wrap.setAttribute('aria-label', 'Instalar aplicación');

        var text = document.createElement('p');
        text.className = 'se-pwa-install__text';
        text.id = 'se-pwa-install-text';

        var actions = document.createElement('div');
        actions.className = 'se-pwa-install__actions';

        var primary = document.createElement('button');
        primary.type = 'button';
        primary.id = 'se-pwa-install-btn';
        primary.className = 'se-pwa-install__btn';
        primary.textContent = 'Instalar';

        var dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'se-pwa-install__dismiss';
        dismiss.textContent = 'Ahora no';
        dismiss.addEventListener('click', dismissInstall);

        actions.appendChild(primary);
        actions.appendChild(dismiss);
        wrap.appendChild(text);
        wrap.appendChild(actions);
        document.body.appendChild(wrap);
        return wrap;
    }

    function showChromeInstallBanner() {
        if (isStandalone() || installDismissedRecently()) return;
        var wrap = ensureInstallBanner();
        var text = document.getElementById('se-pwa-install-text');
        var btn = document.getElementById('se-pwa-install-btn');
        if (!text || !btn) return;
        text.textContent = 'Podés instalar el sistema en este dispositivo. Las notificaciones siguen funcionando si no lo instalás.';
        btn.hidden = false;
        btn.textContent = 'Instalar';
        btn.onclick = function () {
            tryNativeInstall();
        };
        wrap.hidden = false;
    }

    function showIosInstallBanner() {
        if (isStandalone() || installDismissedRecently()) return;
        var wrap = ensureInstallBanner();
        var text = document.getElementById('se-pwa-install-text');
        var btn = document.getElementById('se-pwa-install-btn');
        if (!text || !btn) return;
        text.textContent = 'En iPhone/iPad: Compartir → Agregar a inicio. Las notificaciones solo funcionan al abrir desde ese icono.';
        btn.hidden = true;
        wrap.hidden = false;
    }

    function setupInstallUi() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-se-pwa-install]');
            if (!btn) return;
            e.preventDefault();
            tryNativeInstall();
        });

        if (isStandalone()) return;

        revealInlineInstallButtons();

        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferredInstallPrompt = e;
            showChromeInstallBanner();
        });

        window.addEventListener('appinstalled', function () {
            deferredInstallPrompt = null;
            dismissInstall();
            document.documentElement.classList.remove('se-pwa-can-install');
        });

        if (isIos() && pageWantsInstallHint()) {
            showIosInstallBanner();
        }
    }

    function unregisterLegacyPushSw() {
        if (!navigator.serviceWorker || !navigator.serviceWorker.getRegistrations) {
            return Promise.resolve();
        }
        return navigator.serviceWorker.getRegistrations().then(function (regs) {
            return Promise.all(regs.map(function (reg) {
                var s = reg.scope || '';
                if (s.indexOf('/notificaciones-push/') !== -1) {
                    return reg.unregister();
                }
                return Promise.resolve();
            }));
        });
    }

    function registerSw() {
        if (!('serviceWorker' in navigator)) {
            bindPushHelpers();
            if (typeof window.studentShowPushStatus === 'function') {
                var msg = isIos()
                    ? 'En iPhone/iPad agregá esta página al Inicio (Compartir → Agregar a Inicio) y abrila desde el ícono; las notificaciones no funcionan en Safari en una pestaña normal.'
                    : 'Las notificaciones push requieren HTTPS y un navegador que soporte Service Worker.';
                window.studentShowPushStatus(msg, 'error');
            }
            return;
        }

        if (!window.isSecureContext) {
            bindPushHelpers();
            return;
        }

        if (!swUrl) {
            bindPushHelpers();
            return;
        }

        unregisterLegacyPushSw().finally(function () {
            var swPath = asSameOriginPath(swUrl) || swUrl;
            var scopePath = asSameOriginPath(scope);
            var opts = {};
            if (scopePath && scopePath !== '/') {
                opts.scope = scopePath;
            }
            navigator.serviceWorker.register(swPath, opts).then(function (reg) {
                pushNotificationsRegistration = reg;
                window.dispatchEvent(new CustomEvent('pwa-sw-registered', { detail: reg }));
                bindPushHelpers();
            }).catch(function (err) {
                bindPushHelpers();
                showPush('Service Worker: ' + (err.message || String(err)), 'error');
            });
        });
    }

    bindPushHelpers();
    setupInstallUi();
    registerSw();
})();
