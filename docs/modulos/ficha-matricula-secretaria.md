# Módulo: Ficha de matrícula (Secretaría)

## Propósito

Impresión en lote (PDF / ZIP) de la ficha de matrícula por curso desde el Menú de Secretaría.

## Modalidades / variantes

Activación por tenant en `config/tenants/{slug}.php` → `secretaria.ficha_matricula`:

| `implementacion` | PDF | Datos |
|------------------|-----|-------|
| `sanfranciscoasis` | `FichaMatriculaConAceptacionTcpdf` (incluye Destinatario de Facturación: `respAdmiNom` / `respAdmiDni` en INFORMACIÓN ADICIONAL, antes de observaciones; una página) | `FichaMatriculaDatos` |
| `iess` | `FichaMatriculaIessTcpdf` (layout legacy IESS: AEC en primario, autorización de imágenes en secundario, `grupsang` a la derecha del nombre) | `FichaMatriculaDatos` |
| `montecristo` | `FichaMatriculaSolicitudMontecristoTcpdf` | `FichaMatriculaMontecristoDatos` |
| `sanjose` | `FichaMatriculaSanJoseTcpdf` | `FichaMatriculaMontecristoDatos`. Misma ficha que autogestión: hueco 30×40 mm a la derecha; la foto entra ahí sin deformar y el marco negro rodea la imagen (no el hueco). |

`niveles_deshabilitados`: oculta ítem y PDF en esos IDs de `niveles` (opcional). IESS: todos los niveles.

## Actores y permisos

Menú de Secretaría (`layouts/app`). Rutas bajo `auth`; datos filtrados por `schoolCtx()`.

## Flujo principal

1. Listados → Ficha de Matrícula → elegir cursos → marcar alumnos.
2. PDF único o ZIP por alumno (`listados.ficha-matricula.pdf` / `.zip`).

## Archivos clave

- `app/Livewire/Listados/FichaMatriculaSecretaria.php`
- `app/Support/Alumnos/FichaMatriculaSecretariaPdf.php` / `…Zip.php`
- `app/Support/Alumnos/FichaMatriculaIessTcpdf.php`
- `app/Support/Alumnos/FichaMatriculaSanJoseTcpdf.php`
- Helpers: `tenantSecretariaFichaMatriculaHabilitada()`, `…Implementacion()`, `…Etiqueta()`

## Qué no hacer

- No ramificar por `tenantSlug() === 'iess'` en Blade; usar `implementacion` desde config.
- No calcular datos fuera de `FichaMatriculaDatos` / `FichaMatriculaMontecristoDatos`.
