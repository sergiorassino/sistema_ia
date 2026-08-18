# Módulo: PWA instalable

## Propósito

Permitir instalar el sistema como aplicación (icono en el dispositivo), con el **mismo criterio que SILAVET**.

Hay **dos apps** por colegio (mismo origen, distinto `id` = `start_url`):

| App | Manifiesto | start_url (como SILAVET: login HTTP 200) |
|-----|------------|------------------------------------------|
| **Personal** | `manifest-personal.webmanifest` | `url('/loginUsuario')` |
| **Familias** | `manifest-familias.webmanifest` | `url('/loginEstudiante')` |

Patrón SILAVET (`WebManifestController`): URLs **absolutas** (`url()` / `asset()`), iconos PNG estáticos (`img/icon-192.png`, `img/icon-512.png`), `display: standalone`. No usar `url('/')` ni `./` como `start_url`: en Apache con `Options -Indexes` la carpeta del proyecto da 403/404 y Chrome solo ofrece «Crear acceso directo».

No interceptar `beforeinstallprompt` (`preventDefault`): Chrome oculta «Instalar» y deja solo el acceso directo.

## Modalidades / variantes

| Superficie | Qué ocurre |
|------------|------------|
| Chrome / Edge | Icono **Instalar** en la barra de direcciones / menú ⋮ → Instalar. |
| iPhone / iPad | Compartir → Agregar a inicio en cada login. |
| Quien no instala | Push igual que en el navegador (salvo iOS). |

## Actores y permisos

Cualquier visitante. Suscribir push sigue pidiendo login en `/notificaciones-push/api/*`.

## Tablas y campos críticos

Ninguna tabla nueva.

## Flujo principal

1. Cada login enlaza su manifiesto vía `route('pwa.manifest', ['portal' => …])`.
2. Iconos estáticos en `public/img/` (círculo SE, mismo criterio visual que SILAVET). Regenerar: `php tools/generate-pwa-icons.php`.
3. SW `/sw.js` solo para push; SILAVET no necesita SW para instalar.
4. Al abrir el icono se carga el login de ese portal (igual que SILAVET).

## Fuente de verdad

| Dato | Quién escribe |
|------|---------------|
| Manifiesto | `PwaManifestController` (copia el enfoque de SILAVET `WebManifestController`) |
| Iconos | `public/img/icon-192.png`, `icon-512.png`, `apple-touch-icon.png` |
| SW push | `public/sw.js` |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Identidad | `app/Support/Pwa/PwaIdentity.php` |
| Manifiesto | `app/Http/Controllers/Pwa/PwaManifestController.php` |
| Head | `resources/views/layouts/partials/pwa.blade.php` |
| Cliente | `resources/js/se-pwa.js` |

## Qué no hacer / reglas de negocio

1. No `start_url` / `scope` / iconos relativos (`./`).
2. No `preventDefault` en `beforeinstallprompt`.
3. No usar la carpeta raíz del tenant como `start_url`.
4. No unificar las dos apps en un solo `id`.

## Checklist al modificar

- [ ] Manifiesto con `url()` / `asset()`, `start_url` = login, iconos `icon-192`/`icon-512`.
- [ ] Tras tocar Vite: `npm run build`. Subir también `public/img/icon-*.png` y `apple-touch-icon.png`.
