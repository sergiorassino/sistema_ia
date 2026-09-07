# Módulo: Seguimiento de gabinete de orientación

## Propósito

Registrar, editar, borrar e imprimir eventos del gabinete de orientación por estudiante. Listar alumnos del ciclo activo (por curso o alfabético), marcar un semáforo (verde / amarillo / rojo) y filtrar por color. El **historial PDF** reúne todos los registros del legajo desde que ingresó a la escuela.

## Modalidades / variantes

- Listado de estudiantes del contexto (`idNivel` / `idTerlec`), condiciones 1–4.
- Vista **agrupada por curso** o **orden alfabético** (todo el nivel).
- Filtro de color: todos, verde, amarillo, rojo o sin marca; aplica a un curso o a todo el colegio.
- Alta del evento: se guarda en la **matrícula del año actual**.
- Listado de eventos e historial PDF: **todas las matrículas del legajo** (todos los ciclos).
- Acta PDF (TCPDF) por registro; historial PDF (TCPDF) del alumno.
- **Compartir** un registro con docentes: genera un hilo de comunicación institucional (`scope` docentes) y envía correo de refuerzo **solo si el canal emisor→docente tiene el medio email**.

## Actores y permisos

- Menú de Secretaría (staff): permiso orden **103** (`permiso:103` / `SEGUIMIENTO_GABINETE`).
- Activar el permiso en Permisos por usuario tras aplicar el SQL/migración de catálogo.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `gabinete` | `idMatricula`, `idTipoSancion`, `fecha`, `motivo`, `solipor`, `asistentes`, `conclusion` (`cantidad` si existe) | Legacy. `idTipoSancion` apunta a `gabinetetipo` (mismo nombre de columna que sanciones). |
| `gabinetetipo` | `id`, `tipo` | Catálogo de tipos de seguimiento (equivalente a lo que en el sistema anterior a veces se llamaba seguimientotipo). |
| `gabinetemarca` | `idLegajos`, `color` | Tabla **nueva**. `color`: 0 sin marca, 1 verde, 2 amarillo, 3 rojo. Una fila por legajo. |
| `matricula` / `legajos` / `cursos` | Alcance por contexto | Selector: condiciones 1–4. |

SQL tablas (colegios sin esquema): `database/sql/gabinete_tablas_idempotente.sql` (`gabinetetipo` + `gabinete` + `gabinetemarca`; CREATE IF NOT EXISTS + columnas faltantes).  
Migración tablas: `database/migrations/2026_09_07_122000_create_gabinete_tables_if_missing.php`.  
Migración marca (solo `gabinetemarca`): `database/migrations/2026_09_07_120000_create_gabinetemarca_table.php`.  
SQL marca: `database/sql/gabinetemarca.sql`.  
Migración permiso: `database/migrations/2026_09_07_121000_add_permiso_ia_orden_103_seguimiento_gabinete.php`.  
SQL permiso: `database/sql/permiso_ia_orden_103_seguimiento_gabinete.sql`.

## Flujo principal

1. Listado de alumnos → agrupar por curso o alfabético; filtrar por color; marcar semáforo en la celda de apellido y nombre.
2. **Seguimiento** → eventos del alumno (todos los ciclos) + **Nuevo** + **Historial** + **Volver**.
3. Alta / edición: tipo (`gabinetetipo`), fecha, solicitud, motivo, asistentes, conclusión. Editar y borrar en listado y en el formulario.
4. **Acta** → PDF TCPDF del registro. **Historial** → PDF TCPDF de todos los registros del `idLegajos`.
5. **Compartir** (grilla del alumno) → modal con docentes (Profesor/a, ATP, DOE), **directivos**, **preceptores** y **gabinete de orientación**. Se puede marcar uno a uno o **todos los docentes de un curso** (`ppc` del ciclo). El remitente es el usuario logueado. Un hilo por rol receptor. Texto: intro fija («Sres. Profesores: compartimos… Gabinete de Orientación.») + datos del registro. Medios: `push` y `email` si el canal los habilita; no se dispara WhatsApp.

## Fuente de verdad

- Evento: fila en `gabinete`.
- Tipo: `gabinetetipo.tipo`.
- Semáforo: `gabinetemarca.color` por `idLegajos` (persiste entre años).
- Historial: `gabinete` INNER JOIN `matricula` WHERE `matricula.idLegajos` = el alumno (sin recortar al ciclo activo).

## Archivos clave

- `app/Livewire/Seguimiento/Gabinete/GabineteIndex.php`
- `app/Livewire/Seguimiento/Gabinete/GabineteAlumnoIndex.php`
- `app/Livewire/Seguimiento/Gabinete/GabineteForm.php`
- `app/Http/Controllers/GabineteActaPdfController.php`
- `app/Http/Controllers/GabineteHistorialPdfController.php`
- `app/Support/Seguimiento/GabineteOrientacion.php`
- `app/Support/Seguimiento/GabineteSemaforo.php`
- `app/Support/Seguimiento/GabineteActaTcpdf.php`
- `app/Support/Seguimiento/GabineteHistorialTcpdf.php`
- `app/Support/Seguimiento/NotificarDocentesGabinete.php`

## Qué no hacer / reglas de negocio

- No usar `sanciones` / `sanciontipo` en este módulo.
- No calcular nada: solo se leen y escriben los campos de `gabinete`.
- No poner IDs en las URLs de PDF: `OpaqueRouteToken` (`PURPOSE_GABINETE_ACTA` / `PURPOSE_GABINETE_HISTORIAL`).
- No omitir en silencio el guardado si falta una columna con valor (`PersistenciaColumnas`).
- No mostrar el módulo si faltan `gabinete` / `gabinetetipo`; si falta `gabinetemarca`, el listado funciona pero sin semáforo (aviso en UI).
- PDFs nuevos: TCPDF + Arial; fechas `d/m/Y`.
- Al compartir: revalidar IDs del nivel (docentes, directivos, preceptores, gabinete); el atajo de curso solo marca `ppc` de un curso del contexto; no forzar email si el canal no lo tiene; rate-limit en el envío.

## Checklist al modificar

- [ ] ¿Queries de listado filtradas por `schoolCtx()` (nivel + ciclo)?
- [ ] ¿Selector de alumnos solo condiciones 1–4?
- [ ] ¿Acta e historial revalidan que el legajo tiene matrícula en el contexto actual?
- [ ] ¿Historial por `idLegajos` (todos los ciclos), alta en la matrícula del año actual?
- [ ] ¿PDF con token opaco y rate-limit?
- [ ] ¿Esquema `gabinete` / `gabinetetipo` / `gabinetemarca` y permiso 103 aplicados en el tenant?
- [ ] ¿Compartir usa canales (`puede_iniciar` + `mediosPermitidos`) y no inventa destinatarios fuera del nivel?
- [ ] ¿La lista incluye directivos, preceptores y gabinete, y el atajo de curso valida el curso del contexto?
