# Módulo: Gestión de Inasistencias del Estudiante

## Propósito

Alta, edición y baja de inasistencias de un alumno regular del curso, con recuadro de totales por tipo e informe PDF.

## Modalidades / variantes

El **catálogo de tipos** (`inasistencias_valores`) es por colegio: IDs y conceptos no coinciden entre tenants (p. ej. importación CIDI vs carga manual en EPQ). El recuadro de totales **no** usa IDs fijos.

## Actores y permisos

- Menú de Secretaría → grupo **ASISTENCIA ESTUDIANTES** → **Gestión de Inasistencias del Estudiante**.
- Permiso `permisos_ia` orden **38** (`PermisosIaCatalog::INASISTENCIAS_ESTUDIANTES_GESTION`).
- Informe PDF de secretaría: además `tenantSecretariaInformeInasistenciasHabilitada()`.
- Autogestión familia: mismo PDF vía `InformeInasistenciasController` si el tenant lo habilita.

## Tablas y campos críticos

| Tabla | Uso |
|-------|-----|
| `inasistencias` | Filas por matrícula: `fecha`, `tipo` (FK lógica a `inasistencias_valores.id`), `cantidad`, `just` (J/I), `obs` |
| `inasistencias_valores` | Catálogo de tipos: `concepto`, `cantidad` de referencia, `texto_cidi` (importación), **`mostrarTotal`** |
| `matricula` / `legajos` / `cursos` | Selector de curso y alumno; alcance `schoolCtx()` (nivel + ciclo); solo condiciones regulares |

### `inasistencias_valores.mostrarTotal`

- `1`: el recuadro de totales (pantalla e informe PDF) muestra una tarjeta con la **suma de `inasistencias.cantidad`** de ese tipo.
- `0` (default): ese tipo no aparece en el recuadro (sí puede cargarse y listarse en la grilla).

Los IDs CIDI históricos (clase = 2, tarde 1/4 = 3, tarde 1/2 = 4, EF = 5, retiro = 6) **solo** siguen usándose en TEA y planilla de calificaciones (`InasistenciasResumen::desdeColeccion`), no en este recuadro.

## Flujo principal

1. Elegir curso y alumno (contexto de sesión).
2. Filtrar por fechas y/o tipo si hace falta.
3. Ver listado; el recuadro suma según `mostrarTotal` sobre las filas filtradas.
4. Nueva / editar / borrar inasistencia.
5. Informe PDF (misma lista de totales de catálogo).

## Fuente de verdad

| Dato | Quién escribe | Este módulo |
|------|---------------|-------------|
| Tipos | Catálogo del tenant (`inasistencias_valores`) | Solo lectura |
| `mostrarTotal` | SQL / mantenimiento del catálogo | Solo lectura |
| Inasistencias | Este módulo, toma de asistencia, importación CIDI | Lectura y escritura (gestión) |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Livewire listado | `app/Livewire/Seguimiento/Inasistencias/InasistenciasIndex.php` |
| Vista | `resources/views/livewire/seguimiento/inasistencias/index.blade.php` |
| Formulario | `InasistenciaForm` + `form.blade.php` |
| Totales catálogo | `InasistenciasResumen::totalesCatalogo()` / `InasistenciaValor::tiposParaMostrarTotal()` |
| PDF | `InformeInasistenciasTcpdf` + `InformeInasistenciasPdfController` |
| Migración | `database/migrations/2026_09_03_180000_add_mostrar_total_to_inasistencias_valores.php` |

## Qué no hacer / reglas de negocio

1. No hardcodear IDs de tipo en el recuadro de Gestión ni en el informe PDF de inasistencias del estudiante.
2. No usar `mostrarTotal` para TEA, boletines o planilla de resumen: ahí sigue `InasistenciasResumen::desdeColeccion()` (tipos CIDI).
3. No mostrar éxito de guardado si falla la persistencia (formulario: `PersistenciaColumnas` cuando se toque el catálogo).
4. Tras crear la columna, hay que marcar `mostrarTotal = 1` en **cada colegio** (los IDs no se copian entre tenants).

## Checklist al modificar

- [ ] ¿Queries de matrícula filtradas por `schoolCtx()->idNivel` / `idTerlec`?
- [ ] ¿Totales de la pantalla/PDF leen `mostrarTotal`, no constantes `TIPO_*`?
- [ ] ¿TEA / planilla secundaria intactos (`desdeColeccion`)?
- [ ] ¿PDF TCPDF + Arial (no plantilla Blade nueva)?
- [ ] ¿Columna `mostrarTotal` presente en el tenant antes de probar el recuadro?
