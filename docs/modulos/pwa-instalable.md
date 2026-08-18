# Módulo: PWA instalable

## Propósito

Permitir instalar el sistema como aplicación (icono en el dispositivo) **sin exigir instalación** para usar notificaciones push, salvo en iPhone/iPad (requisito de Apple).

Hay **dos apps** por colegio (mismo origen, distinto `id` de manifiesto):

| App | Se instala desde | Al abrir el icono |
|-----|------------------|-------------------|
| **Personal** | `/loginUsuario` | `/` (home de secretaría/docentes, o login de personal si no hay sesión) |
| **Familias** | `/loginEstudiante` | `/alumnos` (autogestión, o login de estudiantes si no hay sesión) |

No hay pantalla para elegir portal. El `start_url` **no** es el login: esas rutas limpian la sesión (`login.limpiar-sesion`) y cerrarían al usuario cada vez que abre la app.

## Modalidades / variantes

| Superficie | Qué ocurre |
|------------|------------|
| Chrome / Edge | En cada login: **Instalar en este dispositivo**. Quedan dos iconos si se instala desde ambas URLs. |
| iPhone / iPad | Compartir → Agregar a inicio **en cada login**. El push solo funciona al abrir desde ese icono. |
| Quien no instala | Push igual que en el navegador (salvo iOS). |

## Actores y permisos

Cualquier visitante. No hay permiso extra. Suscribir push sigue pidiendo login en `/notificaciones-push/api/*`.

## Tablas y campos críticos

Ninguna tabla nueva. Las suscripciones push siguen en el esquema actual (`PushSubscriptionRepository`).

## Flujo principal

1. `loginUsuario` y menús de personal enlazan `manifest-personal.webmanifest`.
2. `loginEstudiante` y menú de alumnos enlazan `manifest-familias.webmanifest`.
3. Mismo SW `/sw.js` (alcance de la subcarpeta del tenant).
4. `/entrar` redirige (compatibilidad); ya no es el inicio de la PWA.

## Fuente de verdad

| Dato | Quién escribe | Quién solo lee |
|------|---------------|----------------|
| Manifiesto | `PwaManifestController` + `PwaIdentity` | Navegador al instalar |
| Service worker | `public/sw.js` | Navegador / push |
| Suscripción push | Usuario en pantalla Notificaciones | FCM / `WebPushService` |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Identidad / portal | `app/Support/Pwa/PwaIdentity.php` |
| Manifiesto | `app/Http/Controllers/Pwa/PwaManifestController.php` |
| Iconos 180/192/512 | `app/Http/Controllers/Pwa/PwaIconController.php` |
| SW | `public/sw.js` + `PwaServiceWorkerController` |
| Cliente | `resources/js/se-pwa.js` |
| Head | `resources/views/layouts/partials/pwa.blade.php` |
| Rutas | `pwa.manifest` `{portal}`, `pwa.icon`, `pwa.sw` |

## Qué no hacer / reglas de negocio

1. No cachear HTML autenticado ni Livewire en el SW.
2. No ampliar el alcance del SW más allá de `APP_URL`.
3. No usar el login como `start_url` (limpia sesión).
4. No unificar las dos apps en un solo `id` de manifiesto.
5. No exigir instalación para el push en Android/escritorio.

## Checklist al modificar

- [ ] `id` distinto (`./app-personal` vs `./app-familias`).
- [ ] `start_url` `./` (personal) y `./alumnos` (familias); iconos relativos.
- [ ] Cada login enlaza su manifiesto.
- [ ] Tras tocar Vite: `npm run build`; subir `public/build/` y `public/sw.js`.
