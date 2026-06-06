<meta name="pwa-base" content="{{ url('/notificaciones-push') }}/">
<meta name="pwa-scope" content="{{ url('/notificaciones-push') }}/">
<meta name="pwa-sw-url" content="{{ url('/notificaciones-push/sw.js') }}">
<meta name="vapid-public-key" content="{{ config('push.vapid.public_key') }}">

<div class="max-w-2xl space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h1 class="text-lg font-semibold text-gray-900">Notificaciones push</h1>
        <p class="text-sm text-gray-600 mt-1">
            Activá notificaciones para recibir avisos en este dispositivo.
        </p>

        <div id="pushStatus" class="mt-4 text-sm"></div>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button"
                    id="btnEnablePush"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-md"
                    style="background:#0f766e;color:#fff;border:1px solid #0f766e;cursor:pointer">
                Activar notificaciones
            </button>

            <button type="button"
                    id="btnDisablePush"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-md"
                    style="display:none;background:#0f766e;color:#fff;border:1px solid #0f766e;cursor:pointer">
                Desactivar notificaciones
            </button>
        </div>

        <p class="text-xs text-gray-500 mt-4">
            Requiere HTTPS. En iPhone/iPad funciona al abrir desde “Agregar a Inicio”.
        </p>
    </div>
</div>

<script>
    window.studentShowPushStatus = (msg, type) => {
        const el = document.getElementById('pushStatus');
        if (!el) return;
        const cls = type === 'success'
            ? 'text-green-700 bg-green-50 border-green-200'
            : 'text-red-700 bg-red-50 border-red-200';
        el.className = `p-3 rounded-md border ${cls}`;
        el.textContent = msg;
    };

    let alumnoPushSwRegistration = null;

    async function resolvePushServiceWorkerRegistration() {
        if (alumnoPushSwRegistration) {
            return alumnoPushSwRegistration;
        }
        const scopeUrl = document.querySelector('meta[name="pwa-scope"]')?.getAttribute('content')?.trim();
        if (scopeUrl && navigator.serviceWorker?.getRegistration) {
            try {
                const byScope = await navigator.serviceWorker.getRegistration(scopeUrl);
                if (byScope) {
                    return byScope;
                }
            } catch (e) {
                // seguir con fallback
            }
        }
        try {
            return await navigator.serviceWorker.ready;
        } catch (e) {
            return null;
        }
    }

    document.getElementById('btnEnablePush')?.addEventListener('click', async () => {
        const p = await window.studentRequestPushPermission?.();
        if (p === 'denied') window.studentShowPushStatus('Permiso denegado. Habilitalo desde la configuración del navegador.', 'error');
        if (p === 'unsupported') window.studentShowPushStatus('Tu navegador no soporta notificaciones push en este contexto.', 'error');
    });

    const setUiSubscribed = (isSubscribed) => {
        const btnEnable = document.getElementById('btnEnablePush');
        const btnDisable = document.getElementById('btnDisablePush');
        if (!btnEnable || !btnDisable) return;

        if (isSubscribed) {
            btnEnable.style.display = 'none';
            btnDisable.style.display = 'inline-flex';
        } else {
            btnDisable.style.display = 'none';
            btnEnable.style.display = 'inline-flex';
        }
    };

    setUiSubscribed(false);

    document.getElementById('btnDisablePush')?.addEventListener('click', async () => {
        try {
            const reg = await resolvePushServiceWorkerRegistration();
            if (!reg?.pushManager) {
                window.studentShowPushStatus('No se encontró el registro de notificaciones de este sitio.', 'error');
                return;
            }
            const sub = await reg.pushManager.getSubscription();
            if (!sub) {
                window.studentShowPushStatus('Este dispositivo no tiene notificaciones activadas.', 'error');
                setUiSubscribed(false);
                return;
            }
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const r = await fetch(@json(url('/notificaciones-push/api/unsubscribe')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin',
                body: JSON.stringify({ endpoint: sub.endpoint }),
            });
            if (!r.ok) throw new Error(await r.text());
            await sub.unsubscribe();
            window.studentShowPushStatus('Notificaciones desactivadas para este dispositivo.', 'success');
            setUiSubscribed(false);
        } catch (e) {
            window.studentShowPushStatus('No se pudo desactivar. ' + (e?.message || String(e)), 'error');
        }
    });

    window.addEventListener('student-push-subscribed', () => {
        setUiSubscribed(true);
    });

    window.addEventListener('pwa-sw-registered', async (ev) => {
        try {
            alumnoPushSwRegistration = ev?.detail ?? null;
            const reg = alumnoPushSwRegistration || (await resolvePushServiceWorkerRegistration());
            if (!reg?.pushManager) {
                return;
            }
            const sub = await reg.pushManager.getSubscription();
            setUiSubscribed(!!sub);
        } catch (e) {
            // Si no hay SW/Push, dejamos el UI por defecto.
        }
    });
</script>

<script src="{{ url('/notificaciones-push/js/pwa-register.js') }}"></script>
