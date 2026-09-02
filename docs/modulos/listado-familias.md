# Módulo: Listado de familias

## Propósito

Listar **familias** con estudiantes matriculados en el ciclo lectivo activo, con apellido, nombre, DNI y curso de cada hijo (`4A (P)`). Exportar el listado filtrado a PDF (A4 **vertical**, TCPDF) y Excel. No incluye la familia legacy «sin asignar» (`familias.id` = 1).

Quien tiene permiso de **gestionar familias** también puede editar en la grilla **Familia**, **Responsable**, **DNI responsable** y **Email**, sin abrir el legajo de cada estudiante.

## Modalidades / variantes

Ninguna por tenant. El listado es **el mismo** al entrar por Inicial, Primario, Secundario o Administración: familias con hijos matriculados en el **ciclo lectivo activo**, de **todos los niveles pedagógicos**. Hay un filtro opcional de nivel (por defecto «Todos»).

## Actores y permisos

Menú de Secretaría y Menú de Administración → Estudiantes → **Listado de familias**.

- Consulta: personal con sesión (igual que los demás listados de estudiantes).
- Edición en grilla: permiso IA orden 46 (`LEGAJOS_FAMILIAS_GESTION`). Sin ese permiso las columnas se muestran de solo lectura.

## Tablas y campos críticos

| Tabla | Campos |
|-------|--------|
| `familias` | `apellido`, `responsable`, `dniResp` (si existe), `email` |
| `legajos` | `idFamilias`, `apellido`, `nombre`, `dni` |
| `matricula` | ciclo (`idTerlec`); no se filtra por el nivel de sesión |
| `cursos` | `c` (curso), `s` (sección); si ambos vacíos, se usa `cursec` como curso |

## Flujo principal

1. Búsqueda por apellido de familia, responsable, email, DNI del responsable, o apellido/nombre/DNI del estudiante.
2. Filtro opcional de nivel (por defecto todos).
3. Orden: familias por apellido y responsable (collation española); hijos por apellido y nombre.
4. En pantalla y PDF, la familia se muestra **una sola vez** (campos de familia centrados en vertical si hay varios hijos) y cada hijo usa su renglón. El curso se muestra compacto (`4A (P)` = curso+sección y nivel: I inicial, P primario, S secundario). La letra de nivel se toma del **curso** (nombre de `niveles.nivel`, no `abrev` ni el `idNivel` de la matrícula, que suele copiar el nivel de sesión). En Excel, una fila por hijo **con los datos de familia repetidos** (sin celdas combinadas). El export incluye **todos** los registros que coinciden, no solo la página. URLs con `{ref}` opaco.
5. Con permiso de gestión: al salir de Familia / Responsable / DNI / Email se valida y se guarda esa familia. Si el valor no cambió, no hay escritura. Un apellido nuevo no reordena la página hasta buscar o paginar. El DNI del responsable se muestra con separador de miles (`30.111.222`) y se persiste solo con dígitos.

## Fuente de verdad

Matrícula del ciclo activo (`schoolCtx()->idTerlec`) y `legajos.idFamilias`, **sin** recortar al `idNivel` de sesión. Curso/sección del registro de `cursos` de esa matrícula. Los cuatro campos editables se persisten en `familias` (el DNI del responsable solo si existe `familias.dniResp`).

## Archivos clave

- `app/Livewire/Listados/ListadoFamiliasIndex.php`
- `app/Support/Listados/ListadoFamiliasConsulta.php`
- `app/Support/Listados/ListadoFamiliasEdicion.php`
- `app/Support/Listados/ListadoFamiliasExport.php`
- PDF: `ListadoFamiliasPdfController` + `ListadoFamiliasTcpdf`
- Excel: `ListadoFamiliasExcelController`
- Vista: `resources/views/listados/livewire/listados/familias-index.blade.php`
- Celdas editables: `resources/views/listados/livewire/listados/partials/familia-celdas.blade.php`

## Qué no hacer / reglas de negocio

- No listar `familias.id` = 1.
- No mostrar hijos sin matrícula en el ciclo activo.
- No limitar el listado al nivel de sesión (Inicial / Primario / Secundario): el alcance es el ciclo, todos los niveles pedagógicos.
- URLs de PDF/Excel con `{ref}` opaco, sin IDs de familia ni DNI.
- No guardar una familia que no esté en el alcance del listado (ciclo activo; si hay filtro de nivel, ese nivel).
- No mostrar éxito si la columna no existe o el valor no quedó persistido.

## Checklist al modificar

- [ ] ¿El orden alfabético usa `OrdenAlfabeticoEstudiante`?
- [ ] ¿PDF nuevo sigue en TCPDF (Arial), no DomPDF?
- [ ] ¿Export incluye todos los filtros (búsqueda y nivel)?
- [ ] ¿Administración y Secretaría listan todos los niveles del ciclo (salvo filtro opcional)?
- [ ] ¿La edición en grilla exige permiso 46 y revalida alcance?
- [ ] ¿El guardado usa `PersistenciaColumnas` (sin falso éxito)?
