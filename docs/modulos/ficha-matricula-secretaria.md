# Módulo: Ficha de matrícula (Secretaría)

## Propósito

Impresión en lote (PDF / ZIP) de la ficha de matrícula por curso desde el Menú de Secretaría.

## Modalidades / variantes

Activación por tenant en `config/tenants/{slug}.php` → `secretaria.ficha_matricula`:

| `implementacion` | PDF | Datos |
|------------------|-----|-------|
| `sanfranciscoasis` | `FichaMatriculaConAceptacionTcpdf` (incluye Destinatario de Facturación: `respAdmiNom` / `respAdmiDni` en INFORMACIÓN ADICIONAL, antes de observaciones; una página). Misma ficha en autogestión y en Secretaría. En el tenant `sanfranciscoasis` el bloque de personas autorizadas sale siempre de `legajo_autorizados_emergencia`. El logo es el de `ento` del nivel (o el institucional si el del nivel no está en disco). | `FichaMatriculaDatos` |
| `iess` | `FichaMatriculaIessTcpdf` (layout legacy IESS: AEC en primario, autorización de imágenes en secundario, `grupsang` a la derecha del nombre) | `FichaMatriculaDatos` |
| `montecristo` | `FichaMatriculaSolicitudMontecristoTcpdf` | `FichaMatriculaMontecristoDatos` |
| `sanjose` | `FichaMatriculaSanJoseTcpdf` | `FichaMatriculaMontecristoDatos`. Misma ficha que autogestión: hueco 30×40 mm a la derecha; la foto entra ahí sin deformar y el marco negro rodea la imagen (no el hueco). `grupsang` a la derecha del nombre (misma lógica que IESS: `EstudiantesDatosConsulta::valorGrupoSanguineo()`). |
| `caixalsf` | `FichaMatriculaCaixalsfTcpdf` (ficha pedagógica A4: alumno, madre, padre y tutor). Misma variante en autogestión (`autogestion.ficha_matricula.implementacion`). Fechas en `d/m/Y`. | `FichaMatriculaCaixalsfDatos` |

`niveles_deshabilitados`: oculta ítem y PDF en esos IDs de `niveles` (opcional). IESS: todos los niveles.

## Actores y permisos

Menú de Secretaría (`layouts/app`). Rutas bajo `auth`; datos filtrados por `schoolCtx()`.

## Flujo principal

1. Listados → Ficha de Matrícula → elegir ciclo lectivo (`terlec`, año más reciente primero) → elegir cursos de ese ciclo y del nivel de `schoolCtx()` → marcar alumnos.
2. PDF único o ZIP por alumno (`listados.ficha-matricula.pdf` / `.zip`), con `terlec` en la query. El año impreso es el del ciclo elegido, no necesariamente el de la sesión. El nivel sigue siendo el de `schoolCtx()`.

## Autogestión (portal familia)

Misma ficha que Secretaría cuando la implementación coincide.

San Francisco de Asís (`autogestion.ficha_matricula.elegir_anio`): el ítem abre una pantalla con **Año actual** y **Año próximo** (el ciclo de `ento.idTerlecVerNotas` y el siguiente). El PDF sale con `?ciclo=actual` o `?ciclo=proximo`, sin id de `terlec` en la URL. Si el estudiante ya tiene matrícula en ese año, el curso es el de esa matrícula; si no, se usa la matrícula del ciclo de autogestión y el título lleva el año elegido.

## Archivos clave

- `app/Livewire/Listados/FichaMatriculaSecretaria.php`
- `app/Support/Alumnos/FichaMatriculaSecretariaPdf.php` / `…Zip.php`
- `app/Support/Alumnos/FichaMatriculaIessTcpdf.php`
- `app/Support/Alumnos/FichaMatriculaSanJoseTcpdf.php`
- `app/Support/Alumnos/FichaMatriculaCaixalsfTcpdf.php`
- `app/Support/Alumnos/FichaMatriculaCaixalsfDatos.php`
- Helpers: `tenantSecretariaFichaMatriculaHabilitada()`, `…Implementacion()`, `…Etiqueta()`

## Qué no hacer

- No ramificar por `tenantSlug() === 'iess'` en Blade; usar `implementacion` desde config.
- No calcular datos fuera de `FichaMatriculaDatos` / `FichaMatriculaMontecristoDatos` / `FichaMatriculaCaixalsfDatos`.
- No listar cursos del ciclo de sesión si el usuario eligió otro `idTerlec`.
