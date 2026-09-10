# Módulo: Copiar cursos, materias y horarios

## Propósito

Copiar la **estructura del año lectivo** (cursos, materias y horarios) desde un año de origen hacia un año de destino, eligiendo uno, varios o todos los niveles pedagógicos. Equivale al proceso ScriptCase `copiarCursosMaterias`.

No copia matrículas, calificaciones, `ppc` ni preceptores. Para asignaciones de profesores y preceptores ver [copiar-asignaciones-prof-precep.md](copiar-asignaciones-prof-precep.md). Para pasar alumnos al año siguiente ver [promover-alumnos.md](promover-alumnos.md).

## Modalidades / variantes

Ninguna. Mismo proceso en todos los tenants.

## Actores y permisos

- Menú de Administración y Menú de Secretaría → **Configuración**, después de Gestión de cursos y materias del año.
- Permiso `permisos_ia` orden **104** (`PermisosIaCatalog::COPIAR_CURSOS_MATERIAS_ANIO`).
- Descripción del catálogo con aviso **NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR**.
- No hay ítem en Menú de Docentes ni Menú de Alumnos.

## Tablas y campos críticos

| Tabla | Qué copia | Unicidad en destino (omite si ya existe) |
|-------|-----------|------------------------------------------|
| `cursos` | `orden`, `idCurPlan`, `idNivel`, `cursec`, `c`, `s`, `idTurnoClase` (si existe); `idTerlec` = destino | `idTerlec` + `idNivel` + `c` + `s` |
| `materias` | `ord`, planes, nombre, abrev, flags de escala/informe; `idCursos` del curso nuevo; `cierre1e`/`cierre2e` en 0 | `idCursos` (destino) + `ord` |
| `horarios26` | Grilla Laravel: docente, materia, curso, día, módulo, turno | `idProfesores` + `idMaterias` + `idDia` + `idHora` |
| `horarios` | Tabla ScriptCase, solo si existe | `idMaterias` + `idDia` + `idHora` |

El curso de destino de cada materia se resuelve por el mismo `c` + `s` + `idNivel` en el año destino (como el sistema anterior).

## Flujo principal

1. Elegir año de origen, año de destino (distintos) y niveles.
2. La pantalla muestra una previsualización (origen / ya existen / a crear).
3. Confirmar (SweetAlert). En transacción: inserta lo que falte; no pisa lo existente.
4. Informe: totales y **cantidad creada por nivel** (cursos, materias y horarios de la grilla `horarios26`).

## Fuente de verdad

- Estructura pedagógica del año: `cursos` y `materias`.
- Grilla de horas cátedra en Laravel: **`horarios26`**. La tabla `horarios` solo se copia por compatibilidad con el sistema anterior.

## Archivos clave

- `app/Support/Configuracion/CopiarCursosMateriasAnio.php`
- `app/Livewire/Administracion/Configuracion/CopiarCursosMateriasIndex.php`
- `resources/views/livewire/administracion/configuracion/copiar-cursos-materias-index.blade.php`
- Ruta `config.copiar-cursos-materias`
- Permiso: `database/sql/permiso_ia_orden_104_copiar_cursos_materias.sql`

## Qué no hacer / reglas de negocio

- No copiar si origen = destino.
- No incluir el nivel Administración (5).
- No duplicar un curso/materia/horario que ya está en el destino.
- No calcular promedios ni tocar calificaciones.
- Si el curso de origen no tiene par en destino (y no se pudo crear), la materia se omite (`sin_curso`).

## Checklist al modificar

- [ ] Permiso 104 en catálogo, `ordenesReservadosAdministrador()`, ruta, Livewire y ambos sidebars.
- [ ] Confirmación con `seSwalConfirmar`; sin `wire:confirm`.
- [ ] Inserts con `PersistenciaColumnas` (sin falso éxito si falta columna con valor).
- [ ] Previsualización alineada con la ejecución (IDs sintéticos en dry-run para materias/horarios de cursos aún no creados).
