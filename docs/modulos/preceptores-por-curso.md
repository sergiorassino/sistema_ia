# Módulo: Preceptores por curso

## Propósito

Asignar **quién es el preceptor** de cada curso en el **año lectivo** activo. Secretaría elige el personal desde los legajos de `profesores` con rol Preceptor y persiste en la tabla legacy `preceptoresporcurso`.

Sirve a notificaciones de situación áulica, comunicaciones al preceptor del curso y cualquier lectura vía `PreceptoresPorCurso::idsPreceptores()`.

## Modalidades / variantes

Ninguna. Comportamiento único para todos los tenants. El esquema de `preceptoresporcurso` puede variar (`idProfesores` / `idProfesor`, `idNivel` / `idNiveles`); el código detecta columnas.

## Actores y permisos

- Menú de Secretaría / Administración → grupo **DOCENTES / USUARIOS** → **Preceptores por curso**.
- Permiso `permisos_ia` orden **95** (`PermisosIaCatalog::PRECEPTORES_POR_CURSO`).
- No hay ítem en Menú de Docentes ni Menú de Alumnos.

El ciclo y el nivel son los de `schoolCtx()` (selector de contexto). Cada año lectivo tiene sus propios cursos (`cursos.idTerlec`).

## Tablas y campos críticos

| Tabla | Uso |
|-------|-----|
| `preceptoresporcurso` | Escritura: curso + preceptor + ciclo/nivel si existen las columnas |
| `profesores` + `profesortipo` | Origen del preceptor: `IdTipoProf` cuyo `tipo` se normaliza a rol `preceptor` |
| `cursos` | Listado del nivel y ciclo activos |

Columnas típicas de `preceptoresporcurso`: `idCursos`, `idProfesores` (o `idProfesor`), `idTerlec`, `idNivel` (o `idNiveles`).

SQL: `database/sql/preceptoresporcurso_tabla_idempotente.sql` · permiso: `database/sql/permiso_ia_orden_95_preceptores_por_curso.sql` · migración: `database/migrations/2026_08_26_200000_add_permiso_ia_orden_95_preceptores_por_curso.php`.

## Flujo principal

1. Usuario con permiso 95 abre **Preceptores por curso**.
2. Ve los cursos del nivel y ciclo activos; puede filtrar por nombre.
3. En cada fila: elige un preceptor del desplegable (solo rol Preceptor del nivel) y **Asignar**.
4. Puede haber más de un preceptor por curso. **Quitar** pide confirmación (SweetAlert).

## Fuente de verdad

| Dato | Quién escribe | Quién solo lee |
|------|---------------|----------------|
| Asignación curso × preceptor | Livewire `PreceptoresPorCursoIndex` | `PreceptoresPorCurso::idsPreceptores()` (situación áulica, etc.) |
| Legajo / rol | ABM Legajos del docente | Selector de este módulo |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Livewire | `app/Livewire/Abm/PreceptoresPorCurso/PreceptoresPorCursoIndex.php` |
| Vista | `resources/views/livewire/abm/preceptores-por-curso/index.blade.php` |
| Persistencia | `app/Support/PreceptoresPorCurso.php` |
| Ruta | `abm.preceptores-por-curso` |
| Menú | `resources/views/layouts/partials/sidebar-grupo-docentes-usuarios.blade.php` |
| Permiso | `PermisosIaCatalog::PRECEPTORES_POR_CURSO` (95) |

## Qué no hacer / reglas de negocio

1. No listar ni mutar cursos de otro nivel o ciclo que el de `schoolCtx()`.
2. No asignar personal que no tenga rol Preceptor en `profesortipo`.
3. No simular éxito si faltan columnas o la fila no quedó grabada (`PersistenciaColumnas`).
4. No inventar preceptores: solo IDs existentes en `profesores`.
5. Un mismo preceptor puede estar en varios cursos; no duplicar el mismo preceptor en el mismo curso.

## Checklist al modificar

- [ ] Permiso 95 en ruta, Livewire (`mount` y acciones) y sidebar.
- [ ] Filtro de cursos por `schoolCtx()->idNivel` / `idTerlec`.
- [ ] Detección de columnas `idProfesores` / `idProfesor` y `idNivel` / `idNiveles`.
- [ ] Confirmaciones/errores con `se-swal-*` / `seSwalConfirmar` (no `alert` nativo).
- [ ] Selector de preceptores ordenado por apellido; rol vía `CanalesPolicy::normalizarRolProfesor()`.
