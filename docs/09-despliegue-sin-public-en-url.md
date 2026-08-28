# Despliegue Apache sin `/public` en la URL

## Por qué en local funciona y en producción no

| Entorno | Qué ocurre |
|---------|------------|
| `php artisan serve` | El document root **es** `public/`. No hace falta `.htaccess` en la raíz del proyecto. `APP_URL` suele ser `http://127.0.0.1:8000` (sin subcarpeta). |
| Producción (subcarpeta) | El navegador pide `https://dominio.com/ia/colegio/login`. Apache debe tener el **document root en la carpeta `sistema/`** (padre de `public/`), con el `.htaccess` de la raíz reenviando a `public/`. |

Si el hosting apunta el dominio solo a `sistema/public/`, el `.htaccess` de la raíz **nunca se ejecuta** y seguirás necesitando `/public` en la URL o verás 404 en rutas Laravel.

## Checklist en el servidor

1. **Subir** toda la carpeta `sistema/` (no solo `public/`).
2. **Document root** = carpeta que contiene `artisan`, `app/` y `public/` (la raíz de `sistema/`), **no** `sistema/public/`.
3. **`.htaccess` en la raíz** de `sistema/` (incluido en el repo). Si `RewriteBase` estaba fijado a otra ruta (ej. `/ia/25demayo/` en un dominio en la raíz), quitarlo o igualarlo al path real.
4. **`APP_URL` en `.env`** = URL pública exacta, **con** subcarpeta si aplica:
   - Subcarpeta: `https://dominio.com/ia/25demayo` (sin barra final; sin `/public`).
   - Subdominio en raíz: `https://nssc.ejemplo.com` (sin path).
5. **`php artisan config:clear`** tras cambiar `.env`.
6. **Assets:** `npm run build` en el servidor (o subir `public/build/`). **Borrar** `public/hot` en producción (si existe, Vite apunta a `127.0.0.1:5173`).
7. **Apache:** `mod_rewrite` activo y `AllowOverride All` (o al menos `FileInfo`) en esa ruta.
8. **HTTPS:** con `SESSION_SECURE_COOKIE=true`, la URL debe ser `https://` (coherente con `APP_URL`).

## Síntomas frecuentes

| Síntoma | Causa probable |
|---------|----------------|
| 404 en todas las rutas excepto `/` | Document root en `public/` o falta `.htaccess` en la raíz de `sistema/`. |
| CSS/JS rotos | `public/hot` presente, falta `public/build/`, o `APP_URL` incorrecto (assets fuera de la subcarpeta). |
| Login no persiste | `APP_URL` sin el path de subcarpeta → cookies en `/` en vez de `/ia/colegio`. |
| Livewire 404 en AJAX | `APP_URL` mal; hace falta `URL::forceRootUrl` en `AppServiceProvider` (subcarpeta). |
| Login no ingresa / no carga último nivel-año | JS de Livewire en URL sin subcarpeta (`/vendor/livewire/` en vez de `/ia/.../vendor/livewire/`). Tras desplegar: `php artisan config:clear`. |
| Tras login, **404** en `https://dominio.com/dashboard` (sin `/ia/...`) | `APP_URL` debe incluir la subcarpeta; subir `AppServiceProvider` con `URL::forceRootUrl` y `config:clear`. La URL correcta es `https://dominio.com/ia/25demayo/dashboard`. |
| PDF / enlaces a `/alumnos/...` sin subcarpeta | Usar `se_route_url('nombre.ruta')` (host de la petición + path de `APP_URL`) o `APP_URL` con path + `config:clear`. |
| Tras login de estudiante, el menú vuelve a pedir sesión | 1) Host distinto a `APP_URL` (`127.0.0.1` vs `localhost`, `www`). Usar la misma URL que `APP_URL` o reiniciar `npm run dev:all` (sirve en `localhost`). 2) `se_route_url` / `forceRootUrl` deben usar el host de la petición. 3) No cerrar sesión ante errores de matrícula en el portal. |
| Favicon globo en menú (login bien) | Subir vistas `favicon`/`pwa`, layouts y `app/Support/Pwa/PwaIdentity.php`. Subir `public/favicon.ico` y `public/img/favicon-32.png`. `php artisan view:clear` y `config:clear`. En el menú no debe figurar `rel="manifest"` ni `favicon.ico sizes="any"`. |
| `livewire.js` **403 Forbidden** en `/ia/.../vendor/livewire/` | LiteSpeed/hosting suele bloquear la palabra `vendor` en la URL. Usar la ruta Laravel `/livewire-{hash}/livewire.js` (`AppServiceProvider` + `php artisan config:clear`), no archivos en `public/vendor/livewire/`. |
| Sigue apareciendo `/public` | Enlaces viejos o document root incorrecto. |
| Logo del colegio no se guarda / no hay carpeta `storage/.../ento/logos/{slug}` | Ver sección **Logo institucional** más abajo. |
| `livewire/.../upload-file` **401** en Red (logo / CSV) | Firma de URL: PHP ve `http` y la app firmó `https`. Ver **Subida de archivos Livewire (401)**. |

## Subida de archivos Livewire (401 en `upload-file`)

Relacionado con el despliegue **sin `/public` en la URL** (`public/index.php` recorta el path de `APP_URL` para el router).

| Petición Livewire | Cómo va | Por qué suele funcionar / fallar |
|-------------------|---------|----------------------------------|
| `…/update` | Sesión + CSRF | Se corrigió con `LivewireDeploymentScripts` (`data-update-uri` con `APP_URL` completo). |
| `…/upload-file` | **URL firmada** | La firma incluye host, **https** y path `/ia/colegio/…`. Si PHP valida otra URL → **401**. |

En **Red**, si `update` es **200** y `upload-file` **401**, no es tamaño de archivo ni login.

Checklist:

1. **`APP_URL`** = URL exacta del navegador, con **`https://`** y subcarpeta, ej. `https://sistesco.site/ia/sanfranciscoasis` (sin barra final).
2. Borrar caché de bootstrap si Artisan falla por Collision: `rm -f bootstrap/cache/packages.php bootstrap/cache/services.php` y luego `php artisan package:discover` y `config:clear`.
3. Subir `app/Http/Middleware/ForceHttpsBehindProxy.php` y `bootstrap/app.php` (HTTPS detrás del proxy + `X-Forwarded-Prefix` para la subcarpeta).
4. Si sigue el 401: hosting debe pasar HTTPS a PHP (`X-Forwarded-Proto: https`). Con **Cloudflare Flexible SSL**, usar **Full**.
5. `APP_URL`, `public/index.php` y la barra del navegador deben usar el **mismo** path `/ia/...`.

## Logo institucional (Parámetros del sistema)

El logo se guarda en `storage/app/public/ento/logos/{TENANT_SLUG}/nivel-{id}/` y en `ento.logo_path` / `ento.logo_original_name`.

Checklist en producción:

1. **Columnas en BD** — deben existir `logo_path` y `logo_original_name` en `ento` (ver SQL en `database/sql/actualizacion_schema_idempotente.sql`).
2. **`TENANT_SLUG`** en `.env` (ej. `montecristo`) **antes** de `php artisan config:cache`.
3. **Permisos de escritura** en `storage/app/public` y `storage/app/livewire-tmp` (usuario del servidor web).
4. **Enlace simbólico:** `php artisan storage:link` (sirve archivos en `{APP_URL}/storage/...`).
5. **Subcarpeta:** `APP_URL` con el path completo; tras cambiar `.env`, `php artisan config:clear`.
6. **Subida Livewire:** elegir archivo, esperar a que desaparezca «Subiendo archivo…», luego **Guardar**. Si falla, revisar en el navegador la petición `livewire/upload` (419 sesión, 413 tamaño, 500 permisos).

## Archivos implicados

- `sistema/.htaccess` — reescritura raíz → `public/`
- `sistema/public/.htaccess` — front controller Laravel
- `sistema/public/index.php` — ajusta `REQUEST_URI` y `SCRIPT_NAME` según el path de `APP_URL`
- `AppServiceProvider` — `session.path` y `asset_url` desde `APP_URL`
- `resources/views/layouts/partials/livewire-scripts.blade.php` — Livewire en subcarpeta

## Plantilla `.env` producción en subcarpeta

```env
APP_URL=https://tu-dominio.com/ia/nombre-carpeta
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
```

No hace falta `ASSET_URL` si `APP_URL` ya incluye el path (se deriva en `AppServiceProvider`).
