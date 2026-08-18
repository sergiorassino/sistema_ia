# Módulo: PWA instalable

## Propósito

Permitir instalar el sistema como aplicación (icono en el dispositivo) **sin exigir instalación** para usar notificaciones push, salvo en iPhone/iPad (requisito de Apple).

## Modalidades / variantes

| Superficie | Qué ocurre |
|------------|------------|
| Chrome / Edge (Android o escritorio) | Banner **Instalar** cuando el navegador lo ofrece. El push funciona en pestaña o en la app. |
| iPhone / iPad | Banner con *Compartir → Agregar a inicio*. El push **solo** funciona al abrir desde ese icono. |
| Quien no instala | Mismo service worker y mismo Activar / Desactivar que en el navegador. |

Hay **una** app por origen/subcarpeta de tenant (`id` del manifiesto = path de `APP_URL`).

## Actores y permisos

Cualquier visitante (login, autogestión, secretaría). No hay permiso extra. Suscribir push sigue pidiendo login en `/notificaciones-push/api/*`.

## Tablas y campos críticos

Ninguna tabla nueva. Las suscripciones push siguen en el esquema actual (`PushSubscriptionRepository`).

## Flujo principal

1. Todas las pantallas con favicon cargan el manifiesto, el SW `/sw.js` (alcance = `APP_URL`) y el script `se-pwa.js`.
2. Al abrir el icono, `GET /entrar` (`pwa.inicio`): si hay sesión, redirige al home del portal; si no, dos botones (personal / familias).
3. Activar notificaciones usa el SW de raíz (`PushManager` de esa registración).
4. Un SW viejo en `/notificaciones-push/` se desregistra solo.

## Fuente de verdad

| Dato | Quién escribe | Quién solo lee |
|------|---------------|----------------|
| Manifiesto (nombre, iconos, start_url) | `PwaManifestController` + `PwaIdentity` | Navegador al instalar |
| Service worker | `public/sw.js` | Navegador / push |
| Suscripción push | Usuario en pantalla Notificaciones | FCM / `WebPushService` |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Manifiesto | `app/Http/Controllers/Pwa/PwaManifestController.php` |
| Iconos 180/192/512 | `app/Http/Controllers/Pwa/PwaIconController.php` |
| start_url | `app/Http/Controllers/Pwa/PwaInicioController.php` |
| SW | `public/sw.js` |
| Cliente | `resources/js/se-pwa.js` |
| Head | `resources/views/layouts/partials/pwa.blade.php` |
| Rutas | `pwa.manifest`, `pwa.icon`, `pwa.inicio` |

## Qué no hacer / reglas de negocio

1. No cachear HTML autenticado ni Livewire en el SW (solo `offline.html` en navegaciones fallidas).
2. No ampliar el alcance del SW más allá de `APP_URL` (multitenant en subcarpetas del mismo dominio).
3. No exigir instalación para el push en Android/escritorio.
4. No volver a registrar un SW solo bajo `/notificaciones-push/`.

## Checklist al modificar

- [ ] Iconos 192 y 512 en el manifiesto; `display: standalone`.
- [ ] `start_url` = `pwa.inicio`; `scope` con barra final de `APP_URL`.
- [ ] Tras cambiar `sw.js` o `se-pwa.js`: `npm run build` si tocó Vite; el SW estático se sube como `public/sw.js`.
- [ ] Push: Activar / Desactivar sigue usando `/notificaciones-push/api/subscribe|unsubscribe`.
