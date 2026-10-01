# Módulo: Estadística por sexo y curso

## Propósito

Contar **alumnos regulares** del ciclo y nivel de sesión, agrupados por **curso y sección**, con una columna por cada sexo que el colegio tiene en la tabla `sexos`.

Sirve para el relevamiento de matrícula (varones, mujeres u otras categorías definidas por el colegio). No modifica datos.

## Modalidades / variantes

| Vista | Ruta | Salida |
|-------|------|--------|
| Grilla | `estadistica.sexoCurso` | Tabla Año, Nivel, Curso y sección, columnas de `sexos`, Total y fila de suma |
| PDF | `estadistica.sexoCurso.pdf` | El mismo cuadro en A4 vertical (TCPDF) |

El año lectivo y el nivel son los de **contexto de sesión** (`schoolCtx()`). No hay filtros editables.

## Actores y permisos

| Requisito | Detalle |
|-----------|---------|
| Menú | **Menú de Secretaría** (`layouts/app`), grupo sidebar **ESTADÍSTICAS** |
| Permiso | `PermisosIaCatalog::ESTADISTICA_SEXO_CURSO` — **orden 114** |
| Niveles | Inicial, primario, secundario, terciario y adultos. No en sesión Administración |
| Rutas | Middleware `permiso:114` + `EstadisticaSexoCursoDatos::asegurarAcceso()` |

El grupo ESTADÍSTICAS se muestra en cualquier nivel pedagógico (no en Administración). Rendimiento escolar (orden 65) sigue solo en secundario. Estadísticas por edad usa el orden 113.

## Tablas y campos críticos

| Tabla | Uso |
|-------|-----|
| `sexos` | Columnas de la grilla: `id` y nombre `sexo`, ordenadas por `id` |
| `cursos` | Una fila por curso del `idNivel` + `idTerlec` de sesión (`cursec`, `c`, `orden`) |
| `matricula` | Regulares: `idCondiciones = 1`, sin `fechaBaja` (null, `0000-00-00` o vacío) |
| `legajos` | `sexo`: id numérico de `sexos` o texto legacy igual al nombre |
| `terlec` / `niveles` | Año y nombre de nivel en cada fila |

## Fuente de verdad

- Columnas = filas de `sexos` con nombre no vacío. No hay columnas fijas «Varones / Mujeres / Otro».
- Un alumno entra en la columna cuyo `id` coincide con `legajos.sexo`, o cuyo nombre coincide sin distinguir mayúsculas.
- Si el valor no está en el catálogo (vacío, 0 o texto desconocido) suma en **Sin dato**. Esa columna solo aparece cuando hay al menos un alumno así.
- El total de la fila es la suma de las columnas. La última fila es la suma de todos los cursos del nivel y ciclo, incluidos los que tienen 0 alumnos.
- Orden de cursos: nivel, año/sala (`cursos.c` o nombre) y sección (`cursos.orden`), vía `Curso::ordenarColeccion()`.

## Flujo principal

1. Usuario con permiso 114 entra a **Menú de Secretaría → ESTADÍSTICAS → Estadística por Sexo y Curso**.
2. La grilla muestra los cursos del ciclo y nivel activos.
3. **Imprimir PDF** abre el mismo cuadro.

## Archivos clave

- `app/Support/Estadistica/EstadisticaSexoCursoDatos.php`
- `app/Support/Estadistica/EstadisticaSexoCursoTcpdf.php`
- `app/Livewire/Estadistica/PorSexoYCurso.php`
- `app/Http/Controllers/Estadistica/EstadisticaSexoCursoPdfController.php`
- `resources/views/livewire/estadistica/por-sexo-y-curso.blade.php`
- `resources/views/layouts/partials/sidebar-grupo-estadisticas.blade.php`

## Qué no hacer / reglas de negocio

1. No hardcodear Varones, Mujeres u Otro: salen de `sexos`.
2. No incluir alumnos con baja ni condición distinta de regular (`idCondiciones = 1`).
3. No listar cursos de otro nivel o ciclo que el de la sesión.
4. No poner IDs en la URL del PDF.

## Checklist al modificar

- [ ] ¿Las columnas siguen siendo `sexos` ordenadas por `id`?
- [ ] ¿Sigue filtrando regulares sin fecha de baja?
- [ ] ¿Sin dato solo aparece si hay alumnos sin sexo del catálogo?
- [ ] ¿El permiso 114 cubre la grilla y el PDF?
