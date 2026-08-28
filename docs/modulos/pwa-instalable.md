# Módulo: PWA instalable

## Propósito

Permitir instalar el sistema como aplicación (icono en el dispositivo), con el **mismo criterio que SILAVET**.

Hay **dos apps** por colegio. Chrome solo deja instalar la segunda si los `scope` **no se solapan**. Por eso cada portal vive bajo un directorio propio (el resto de rutas se reutiliza quitando ese prefijo):

| App | Manifiesto | `id` / `scope` | `start_url` |
|-----|------------|----------------|-------------|
| **Personal** | `manifest-personal.webmanifest` | `…/pwa-personal/` | `…/pwa-personal/entrar` |
| **Estudiante** (portal familias) | `manifest-familias.webmanifest` | `…/pwa-familias/` | `…/pwa-familias/entrar` |

`/entrar` redirige al home si hay sesión, o al login si no. No usar el login como `start_url`: el login limpia la sesión. No usar `url('/')` ni `./`: en Apache con `Options -Indexes` la carpeta del proyecto da 403/404 y Chrome solo ofrece «Crear acceso directo».

Patrón SILAVET (`WebManifestController`): URLs **absolutas** (`url()` / `asset()`), iconos PNG estáticos, `display: standalone`.

No interceptar `beforeinstallprompt` (`preventDefault`): Chrome oculta «Instalar» y deja solo el acceso directo.

## Modalidades / variantes

| Superficie | Qué ocurre |
|------------|------------|
| Chrome / Edge | «Instalar en este dispositivo» dispara el prompt nativo. Si la página no está en el `scope`, primero abre el login prefijado (`/pwa-personal/loginUsuario` o `/pwa-familias/loginEstudiante`). |
| iPhone / iPad | Compartir → Agregar a inicio en cada login. |
| Quien no instala | Push igual que en el navegador (salvo iOS). |

Si ya hay una app instalada con el `scope` viejo (todo el sitio), Chrome **no** ofrece la segunda. Hay que desinstalar esa app e instalar las dos de nuevo.

## Actores y permisos

Cualquier visitante. Suscribir push sigue pidiendo login en `/notificaciones-push/api/*`.

## Tablas y campos críticos

Ninguna tabla nueva.

## Flujo principal

1. Cada login enlaza su manifiesto vía `route('pwa.manifest', ['portal' => …])`.
2. Iconos estáticos en `public/img/` (círculo SE, mismo criterio visual que SILAVET). Regenerar: `php tools/generate-pwa-icons.php`. El manifiesto usa `icon-se-192.png` / `icon-se-512.png` (no `icon-192.png`, que Chrome puede tener cacheado del icono verde). La **solapa del navegador** usa el mismo partial `layouts.partials.favicon` en **login y menú** (patrón SILAVET: `asset()` + `favicon.ico` + PNG 32/192/512 + manifiesto).
3. SW `/sw.js` solo para push (alcance del tenant, no el de cada manifiesto).
4. Al abrir el icono se carga `/pwa-…/entrar` y de ahí el home o el login de ese portal.

## Fuente de verdad

| Dato | Quién escribe |
|------|---------------|
| Manifiesto | `PwaManifestController` (copia el enfoque de SILAVET `WebManifestController`) |
| Prefijo / alcance | `PwaIdentity` + `PwaPortalPrefixRewrite` / `PwaPortalPrefixSession` |
| Iconos | `public/img/icon-se-192.png`, `icon-se-512.png`, `apple-touch-icon-se.png` |
| SW push | `public/sw.js` |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Identidad | `app/Support/Pwa/PwaIdentity.php` |
| Manifiesto | `app/Http/Controllers/Pwa/PwaManifestController.php` |
| Prefijo | `app/Http/Middleware/PwaPortalPrefixRewrite.php`, `PwaPortalPrefixSession.php` |
| Head | `resources/views/layouts/partials/pwa.blade.php` |
| Cliente | `resources/js/se-pwa.js` |

## Qué no hacer / reglas de negocio

1. No `start_url` / `scope` / iconos relativos (`./`).
2. No `preventDefault` en `beforeinstallprompt`.
3. No usar la carpeta raíz del tenant como `scope` ni `start_url` (impediría instalar la segunda app).
4. No unificar las dos apps en un solo `id` ni un solo `scope`.
5. No poner el login como `start_url` (limpia la sesión).
6. No poner el login como `start_url` (limpia la sesión).
7. No enlazar `<link rel="manifest">` en layouts autenticados (`app`, `docente`, `alumno`, etc.): solo en `guest` / login. El menú debe usar solo `favicon-32.png` y `public/favicon.ico`.

## Checklist al modificar

- [ ] Manifiesto con `url()` / `asset()`, `scope` distinto por portal (`/pwa-personal/` vs `/pwa-familias/`), `start_url` = `/entrar` prefijado, iconos `icon-se-192`/`icon-se-512` con `?v=`.
- [ ] Tras tocar Vite: `npm run build`. Subir también `public/img/favicon-32.png`, `public/img/icon-se-*.png`, `apple-touch-icon-se.png`, `public/favicon.ico` y `public/sw.js`.
