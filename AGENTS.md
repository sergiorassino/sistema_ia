# Instrucciones para asistentes de código (Cursor, Copilot, etc.)

Este archivo está **en el repositorio**: aplica a **todas** las personas y herramientas que trabajen sobre este código, con o sin reglas personales en Cursor.

## Base de datos (obligatorio)

**No ejecutar** desde la herramienta de terminal del asistente nada que **escriba** en la base de datos, **aunque el usuario lo pida**. Incluye, entre otros:

- `php artisan tinker` … con `delete` / `update` / `insert`
- `php artisan migrate*`, `db:*`, `db:seed`, imports, cliente `mysql`
- Scripts PHP one-shot (`php -r`, etc.) que usen Eloquent o `DB::`

**Sí hacer:** entregar **solo SQL** (o el comando Artisan) **en el chat como texto** para que un humano lo revise y ejecute en su cliente; y guardar migraciones/código en archivos **sin** invocarlos para aplicar el cambio en la BD.

**Cierre de tareas (IAs y colaboradores):** si el cambio implica **esquema o datos** (nueva migración, seeder de datos de negocio, script de alineación, `UPDATE`/`DELETE` documentados, etc.), al **final** de la respuesta o del PR debe figurar un bloque **listo para copiar** con:

1. Las sentencias **SQL** equivalentes al `up()` de la migración (u operación de datos), en el orden correcto respecto de FKs si aplica; y  
2. Una **advertencia breve** de alcance (tablas afectadas, irreversibilidad).

Si lo habitual en el entorno es aplicar migraciones con Artisan en lugar de SQL manual, puede indicarse además `php artisan migrate` como alternativa, **sin** ejecutarlo desde la herramienta del asistente.

Detalle y matices: `docs/06-reglas-de-seguridad.md` sección **9**.

## Despliegue a producción (obligatorio al cerrar tareas)

Si el cambio implica **código o vistas** (no solo consulta o documentación), al **final** de la respuesta o del PR debe figurar un bloque **Archivos para producción** con:

1. **Lista de rutas** relativas a `sistema/` (una por línea), solo los archivos que hay que **subir o reemplazar** en el servidor.
2. **Assets compilados**, si aplica: indicar si hay que ejecutar `npm run build` en el servidor o subir `public/build/` (y recordar borrar `public/hot` si existe).
3. **Comandos post-despliegue** opcionales en una línea (por ejemplo `php artisan view:clear`, `php artisan config:clear`), **sin ejecutarlos** desde la herramienta del asistente.
4. Si **no** hubo cambios desplegables, decirlo explícitamente.

Usar el mismo criterio que para SQL: rutas concretas, sin carpetas genéricas salvo que todo un directorio nuevo deba subirse entero. Si además hay SQL, incluir **ambos** bloques (SQL y archivos).

Referencia de despliegue: `docs/09-despliegue-sin-public-en-url.md`.

## Promedio de calificaciones (secundario)

No calcular promedios salvo en **carga manual** (`CargaCalificacionesSecundario`, al guardar `ic01..ic28` → `calif`). El resto del sistema solo **lee** `calificaciones.calif`. Detalle: `docs/05-preferencias-y-convenciones.md` §7.

## PDFs

- **Nuevos:** **TCPDF** (`tecnickcom/tcpdf`), clase en `app/Support/`, controlador `*PdfController`. Fuente **Arial** vía `App\Support\Pdf\TcpdfFuenteArial` (`storage/fonts/arial.ttf`). Párrafos justificados: `TcpdfMultiCellJustificado::escribir()` (no `MultiCell` con `J`). No usar DomPDF ni vistas Blade de layout. Regla: `.cursor/rules/pdf-tcpdf-nuevos.mdc`. Referencia: `InformeInasistenciasTcpdf`, `ActaVolantePreviosTcpdf`.
- **Legacy (DomPDF):** tablas con columnas de distinto ancho: **porcentaje inline en cada `th` y `td`** (`min-width:0; overflow:hidden`), tabla al **100%**, `table-layout: fixed`. Regla: `.cursor/rules/pdf-dompdf-columnas.mdc`; detalle en `docs/05-preferencias-y-convenciones.md` §9.

## Selects de año lectivo (`terlec`)

Todo desplegable de ciclo lectivo: **orden decreciente** (año más reciente primero). Usar `Terlec::paraSelector()` o `Terlec::ordenado()`; en Livewire con re-render frecuente, `livewire:components.terlec-selector`. Regla Cursor: `.cursor/rules/terlec-selector-orden.mdc`.

## Menús de navegación (terminología)

Usar siempre: **Menú de Secretaría** (`layouts/app`), **Menú de Administración** (`layouts/administracion`), **Menú de Alumnos** (`layouts/alumno`), **Menú de Docentes** (`layouts/docente`). En Livewire compartido: `layoutMenuStaff()` o `ProfesorMenuPortal::layoutStaff()`. No mezclar con el grupo sidebar “DOCENTES” de secretaría. Detalle: `docs/08-menus-de-navegacion.md`.

## Diálogos al usuario (SweetAlert2)

Confirmaciones, éxitos, avisos y errores: helpers `seSwal*` en `resources/js/app.js`; eventos Livewire `se-swal-exito` / `se-swal-error`. No usar `wire:confirm` ni `alert`/`confirm` del navegador. Detalle: `docs/05-preferencias-y-convenciones.md` §12 y `.cursor/rules/sweetalert-dialogos-se.mdc`.

## Paginación en listados Livewire

Listados paginados: `WithPagination`, `POR_PAGINA = 50`, `resetPage()` al cambiar filtros, y en Blade `@if ($registros->hasPages())` + `se-matriz-list-footer` + `$registros->links('vendor.pagination.se-compact')`. No usar `links()` sin `se-compact` en módulos nuevos. Detalle: `docs/05-preferencias-y-convenciones.md` §13 y `.cursor/rules/paginacion-listados-se.mdc`.

## URLs sin IDs reveladores

En autogestión, PDFs y descargas por GET: **no** poner IDs de BD, DNI ni legajo en la URL. Usar `App\Support\Security\OpaqueRouteToken` o token en BD (aspirantes). Detalle: `docs/06-reglas-de-seguridad.md` §10 y `.cursor/rules/urls-sin-identificadores.mdc`.

## Persistencia en BD (sin falsos éxitos)

En guardados sobre tablas legacy multi-tenant: validar columnas con `App\Support\Database\PersistenciaColumnas`, capturar `QueryException` y verificar post-guardado. No omitir campos con valor si la columna no existe; no mostrar éxito si falló la persistencia. Detalle: `docs/05-preferencias-y-convenciones.md` §14 y `.cursor/rules/persistencia-bd-sin-falso-exito.mdc`.

## Documentación por módulo

Antes de cambiar comportamiento de un módulo existente, leer (o crear) su ficha en `docs/modulos/` (plantilla e índice en `docs/modulos/README.md`). Ahí viven propósito, modalidades, permisos, tablas, flujos y trampas específicas.

## Resto del baseline

Seguridad, permisos, `schoolCtx`, Blade, etc.: `docs/06-reglas-de-seguridad.md` y las reglas en `.cursor/rules/` (por ejemplo `seguridad-php-mysql-laravel.mdc`, `preferencias-del-proyecto.mdc`).
