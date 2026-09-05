# Módulo: Planilla resumen de calificaciones (secundario)

## Propósito

PDF por curso (o varios) con todas las materias en columnas: mejor nota por módulo 1–8, JIS, promedio anual, coloquios y pie de resumen (reprobadas, inasistencias, amonestaciones, ed. física, promedio general y previas).

## Modalidades / variantes

- Solo nivel secundario, carga estándar (no EPQ).
- Impresión de uno o más cursos en un mismo PDF (A4 apaisado, TCPDF).

## Actores y permisos

- Menú de Secretaría, grupo **CALIFICACIONES (Secundario)**.
- Permiso **76** (`PermisosIaCatalog::CALIF_PLANILLA_RESUMEN`).

## Tablas y campos críticos

- Lectura: `calificaciones` (`ic01`…`ic28`, `dic`, `feb`, `calif`), `materias`, `matricula`, inasistencias, previas.
- No escribe en BD.

## Flujo principal

1. UI: selección de cursos.
2. PDF: `PlanillaResumenCalificacionesPdfController` → `PlanillaResumenCalificacionesSecundario::buildSecciones()` → TCPDF.

## Fuente de verdad

- Celdas de módulo: mejor nota del bloque (N/R1/R2); rojo si esa mejor nota es &lt; 7; gris si hubo recuperatorio.
- Promedio anual mostrado: `calificaciones.calif` (no se recalcula).
- **Nº Rep.:** cantidad de materias del curso con **al menos un módulo 1–8** cuya mejor nota es inferior a 7 (mismo criterio que el texto rojo). No usa `calif`. Un módulo recuperado a 7 o más no cuenta. JIS no entra en este recuento. Si el número es mayor a 0, «Nº Rep: N» se imprime en rojo.

## Archivos clave

- Livewire: `app/Livewire/CalificacionesSecundario/PlanillaResumenCalificacionesSecundario.php`
- Datos: `app/Support/PlanillaResumenCalificacionesSecundario.php`
- PDF: `app/Support/CalificacionesSecundario/PlanillaResumenCalificacionesTcpdf.php`
- Controller: `app/Http/Controllers/CalificacionesSecundario/PlanillaResumenCalificacionesPdfController.php`

## Qué no hacer / reglas de negocio

- No contar reprobadas por el promedio anual (`calif`): durante el año ese campo suele estar vacío si hay módulos desaprobados, y el sistema anterior contaba módulos &lt; 7.
- No calcular ni persistir promedios en este PDF.

## Checklist al modificar

- [ ] Nº Rep. coincide con la cantidad de columnas que tienen al menos un módulo en rojo.
- [ ] Módulo aprobado con recuperatorio (fondo gris, nota ≥ 7) no suma al Nº Rep.
- [ ] Prom. Gral. sigue leyendo `calif` y solo se muestra si todas las materias del curso lo tienen.
