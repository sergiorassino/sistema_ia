# Módulo: Listado de familias

## Propósito

Listar **familias** con estudiantes matriculados en el ciclo lectivo activo, con apellido, nombre, DNI, curso y sección de cada hijo. Exportar el listado filtrado a PDF (A4 **vertical**, TCPDF) y Excel. No incluye la familia legacy «sin asignar» (`familias.id` = 1).

## Modalidades / variantes

Ninguna por tenant. En **Administración** hay filtro de nivel pedagógico; en Secretaría el alcance es el nivel de sesión.

## Actores y permisos

Menú de Secretaría y Menú de Administración → Estudiantes → **Listado de familias**. Consulta disponible para el personal con sesión (igual que los demás listados de estudiantes). No hay permiso `permisos_ia` propio.

## Tablas y campos críticos

| Tabla | Campos |
|-------|--------|
| `familias` | `apellido`, `responsable`, `dniResp` (si existe), `email` |
| `legajos` | `idFamilias`, `apellido`, `nombre`, `dni` |
| `matricula` | ciclo (`idTerlec`) y nivel del contexto |
| `cursos` | `c` (curso), `s` (sección); si ambos vacíos, se usa `cursec` como curso |

## Flujo principal

1. Búsqueda por apellido de familia, responsable, email, DNI del responsable, o apellido/nombre/DNI del estudiante.
2. En Administración, filtro opcional de nivel.
3. Orden: familias por apellido y responsable (collation española); hijos por apellido y nombre.
4. En pantalla y PDF, la familia se muestra **una sola vez** y cada hijo usa su renglón. En el PDF, curso y sección van centrados; el texto de cada celda se centra en vertical (un hijo no deja un hueco abajo si el responsable ocupa dos líneas). En Excel, una fila por hijo **con los datos de familia repetidos** (sin celdas combinadas). El export incluye **todos** los registros que coinciden, no solo la página. URLs con `{ref}` opaco.

## Fuente de verdad

Matrícula del ciclo activo (`schoolCtx()->idTerlec`) y `legajos.idFamilias`. Curso/sección del registro de `cursos` de esa matrícula.

## Archivos clave

- `app/Livewire/Listados/ListadoFamiliasIndex.php`
- `app/Support/Listados/ListadoFamiliasConsulta.php`
- `app/Support/Listados/ListadoFamiliasExport.php`
- PDF: `ListadoFamiliasPdfController` + `ListadoFamiliasTcpdf`
- Excel: `ListadoFamiliasExcelController`
- Vista: `resources/views/listados/livewire/listados/familias-index.blade.php`

## Qué no hacer / reglas de negocio

- No listar `familias.id` = 1.
- No mostrar hijos sin matrícula en el ciclo activo.
- En Secretaría no ampliar el listado a otros niveles.
- URLs de PDF/Excel con `{ref}` opaco, sin IDs de familia ni DNI.

## Checklist al modificar

- [ ] ¿El orden alfabético usa `OrdenAlfabeticoEstudiante`?
- [ ] ¿PDF nuevo sigue en TCPDF (Arial), no DomPDF?
- [ ] ¿Export incluye todos los filtros (búsqueda y nivel)?
- [ ] ¿Administración filtra por nivel pedagógico y Secretaría por sesión?
