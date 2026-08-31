# Módulo: Horarios

## Propósito

Armar, consultar e imprimir la **grilla semanal de horas cátedra** del nivel y ciclo activos (`schoolCtx()`): turnos y reloj del establecimiento, carga por docente/materia, PDF por curso o por profesor, y listado de docentes presentes en una franja.

También alimenta el **parte diario del preceptor** y, si el tenant lo habilita, el **horario de clase** del Menú de Alumnos.

## Fuente de verdad de la grilla (obligatorio)

| Tabla | Sistema | Rol en Laravel |
|-------|---------|----------------|
| **`horarios26`** | Laravel (este módulo) | **Única** tabla de celdas: un renglón = una hora cátedra (docente + materia + curso + día + módulo + turno). |
| **`horarios`** | ScriptCase (sistema anterior) | **No se usa** en configuración, carga, impresión, profesores presentes, horario del alumno ni parte diario. No leerla ni rellenar huecos del PDF con ella. |

Si una celda no está tildada en **Carga de horarios**, no debe aparecer en el impreso. Datos viejos en `horarios` no cuentan.

Compatibilidad **dentro de `horarios26`**: filas sin `idTurnoClase` o con `idHora` 11–30 (bloques mañana/tarde/noche antiguos). Eso no es la tabla `horarios`.

Excepción ajena a este módulo: al **borrar una asignatura** en [materias-anio.md](materias-anio.md) se pueden contar/borrar filas de `horarios` (ScriptCase) si la tabla existe, para no dejar basura del sistema anterior.

## Modalidades / variantes

Comportamiento de Secretaría único para todos los tenants.

| Superficie | Cómo se habilita |
|------------|------------------|
| Menú de Secretaría (grupo HORARIOS) | Siempre; Configuración y Carga exigen permiso 13 |
| Horario de clase (Menú de Alumnos) | `tenant.autogestion.horario_clase.habilitado` (default `false`). Opcional `niveles_habilitados` (p. ej. `[3]` solo secundario). Helper: `tenantAutogestionHorarioClaseHabilitada()` |

## Actores y permisos

Permiso IA orden **13** (`PermisosIaCatalog::HORARIOS`): *Configuración de horarios (turnos, días, reloj) y carga de horas cátedra por docente. No incluye impresión de horarios.*

| Función | Menú | Permiso |
|---------|------|---------|
| Configuración de horarios | Secretaría → HORARIOS | Orden **13** |
| Carga de horarios | Secretaría → HORARIOS | Orden **13** |
| Impresión de horarios | Secretaría → HORARIOS | Cualquier usuario de Secretaría (**sin** 13) |
| Profesores presentes | Secretaría → HORARIOS | Igual que Impresión (**sin** 13) |
| Horario de clase (PDF) | Alumnos | Tenant + matrícula del ciclo de autogestión |

No hay ítems en Menú de Administración ni Menú de Docentes.

Requisitos de negocio para que la grilla tenga sentido:

1. Curso con turno en **ABM → Cursos** (`cursos.idTurnoClase` → `turnos_clase`).
2. Materias del curso en el ciclo (`materias`).
3. Asignación docente × materia en **`ppc`**. Sin `ppc` no se puede marcar la celda.
4. Reloj del turno cargado (textos tipo `07:20-08:00`) para que el PDF muestre hora reloj.

## Tablas y campos críticos

| Tabla | Uso |
|-------|-----|
| **`horarios26`** | Grilla Laravel: `idProfesores`, `idMaterias`, `idCursos`, `idDia` (`lun`/`mar`/…), `idHora` **1–10**, `idTurnoClase` |
| `horarios_config` | Turnos y días activos por `idNivel` |
| `turnos_clase` | Catálogo de jornadas (mañana, tarde, noche, doble jornada, …) |
| `reloj` | Texto de cada módulo (`orden` 1–10) por nivel y `idTurnoClase` |
| `ppc` | Asignación vigente; condición para cargar e interpretar docente en el PDF |
| `cursos` / `materias` / `profesores` | Alcance `schoolCtx()` y etiquetas |

Cada turno tiene **10 módulos** (`HorariosProfesores::HORAS_POR_TURNO`). El impreso lista esas 10 filas; las celdas vacías no se rellenan con otra tabla.

Doble jornada: dos bandas (mañana y tarde), cada una con `idHora` 1–10 y distinto `idTurnoClase`.

## Funciones

### 1. Configuración de horarios

Define la jornada del establecimiento en el nivel de sesión.

1. Marcar **turnos** del catálogo `turnos_clase` (el compuesto «Mañana/Tarde» no se lista aquí: se asigna al curso en ABM).
2. Marcar **días** de clase (lun–vie u otros habilitados).
3. Cargar el **reloj** (1.º a 10.º) por turno: `desde-hasta`.
4. Guardar turnos/días y, aparte, guardar el reloj.

Sin reloj, el PDF igual muestra «Nº HORA» pero sin franja horaria.

### 2. Carga de horarios

Marca horas cátedra en `horarios26`.

1. Elegir **docente**.
2. Elegir **curso y materia** (solo asignaciones `ppc` de ese docente).
3. Tildar celdas día × módulo (1–10). Un clic inserta; otro borra.
4. Si el curso es doble jornada, hay **dos grillas** (mañana / tarde).
5. Conflicto: el mismo docente en otro curso, mismo día, módulo y turno → no se guarda y se avisa.

Cátedra compartida: dos docentes de la misma materia pueden tildar la misma celda.

### 3. Impresión de horarios

PDF de la grilla **solo desde `horarios26`** (misma fuente que Carga).

1. Modo **por curso** o **por docente**; uno o varios.
2. Turno opcional (si no, el del curso / los que tengan horas).
3. Curso: una hoja A4 apaisada por turno (DomPDF, vista `pdf.horario-grid`).
4. Docente: grilla con curso + materia por celda.

Rate-limit en las descargas.

### 4. Profesores presentes

Listado operativo (pantalla + PDF TCPDF). Ficha: [profesores-presentes.md](profesores-presentes.md).

Cruza `horarios26` + `reloj` + `ppc`. Un renglón por docente en el día y la franja.

### 5. Horario de clase (autogestión)

Si el tenant lo habilita: PDF del curso de la matrícula del alumno (`HorarioCursoPdfExport`, misma grilla `horarios26`). URL opaca / sesión alumno; sin IDs de curso en la ruta pública.

## Flujo principal (Secretaría)

1. Configurar turnos, días y reloj (permiso 13).
2. Asignar docentes en `ppc` y turno del curso en ABM.
3. Cargar celdas en **Carga de horarios** (permiso 13) → escribe `horarios26`.
4. Imprimir o consultar **Profesores presentes** (sin permiso 13).

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Lógica compartida | `app/Support/HorariosProfesores.php` |
| Configuración | `app/Livewire/Horarios/HorariosConfigIndex.php` |
| Carga | `app/Livewire/Horarios/HorariosCargaIndex.php` |
| Impresión (UI) | `app/Livewire/Horarios/HorariosImpresionIndex.php` |
| PDF curso | `app/Support/Horarios/HorarioCursoPdfExport.php` + `HorarioCursoPdfController` |
| PDF docente | `app/Http/Controllers/Horarios/HorarioProfesorPdfController.php` |
| PDF alumno | `app/Http/Controllers/Alumnos/HorarioClasePdfController.php` |
| Profesores presentes | ver [profesores-presentes.md](profesores-presentes.md) |
| Permiso en Livewire | `app/Livewire/Horarios/Concerns/RequiresPermisoHorariosConfigCarga.php` |
| Rutas | `horarios.config`, `horarios.carga`, `horarios.impresion`, `horarios.pdf.curso`, `horarios.pdf.profesor`, `horarios.profesores-presentes` (+ `.pdf`), `alumnos.horario-clase` |
| Menú | `resources/views/layouts/app.blade.php` (grupo HORARIOS) |
| SQL tabla grilla | `database/sql/horarios26_tabla_idempotente.sql` |

## Qué no hacer / reglas de negocio

1. **No leer ni escribir `horarios`** en este módulo. Grilla, PDF y listados = **`horarios26`**.
2. No rellenar celdas vacías de `horarios26` con el sistema anterior (el PDF mostraría materias que Carga no tiene tildadas).
3. No exigir permiso 13 para Impresión ni Profesores presentes.
4. Filtrar cursos/materias por `schoolCtx()->idNivel` / `idTerlec`; revalidar IDs en los PDF.
5. No marcar celdas sin asignación `ppc`.
6. PDF de curso/docente: DomPDF existente (`pdf.horario-grid`); **Profesores presentes** es PDF nuevo → TCPDF + Arial.
7. Autogestión: no poner IDs de curso/alumno en la URL (`OpaqueRouteToken` / sesión).
8. Confirmaciones y errores: `se-swal-*`, no `alert`/`confirm` nativos.

## Checklist al modificar

- [ ] Lecturas de grilla/PDF/listados van a `horarios26`, no a `horarios`.
- [ ] Carga inserta/borra solo `horarios26` (`idHora` 1–10 + `idTurnoClase`).
- [ ] Permiso 13 solo en Configuración y Carga; Impresión y Profesores presentes abiertos a Secretaría.
- [ ] Filtro `schoolCtx()` en consultas y PDF.
- [ ] Reloj por turno (`relojPorTurnoClase`); doble jornada: ambas bandas.
- [ ] Días con códigos legacy (`lun`, `mar`, …).
- [ ] Conflicto docente en otro curso, mismo módulo/día/turno.
- [ ] Fechas en UI/PDF `d/m/Y`; horas `hh:mm`.
