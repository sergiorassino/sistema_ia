# Módulo: Gestión de asignaturas del año

## Propósito

ABM de `materias` del ciclo lectivo activo (`schoolCtx`): alta/edición inline, flags extracurricular / informe, escala de calificación y sincronización desde `matplan`.

## Actores y permisos

- Menú de Secretaría / Administración.
- Permiso: `PermisosConfiguracion::MATERIAS_ANIO` (orden 36 / id catálogo IA 38).

## Tablas y campos críticos

| Campo UI | Columna | Origen del nombre mostrado |
|----------|---------|----------------------------|
| Curso | `materias.idCursos` | `cursos.cursec` (+ `c`/`s`/turno) |
| CPl | `materias.idCurPlan` | `curplan.curPlanCurso` (+ abrev. plan) |
| MPl | `materias.idMatPlan` | `matplan.matPlanMateria` |
| Esc. | `materias.escala` | 1 = Conceptos, 2 = Literales |
| Materia / Abrev | `materias.materia` / `abrev` | Texto propio del año |

Columnas opcionales por tenant: `esInstitucional`, `infoCalif`, `escala`.

## Flujo principal

1. Filtrar por curso del año (`idNivel` + `idTerlec`).
2. Listar / crear / editar filas de `materias`.
3. «Matplan» copia nombre, abreviatura y orden desde la materia modelo.

## Archivos clave

- `app/Livewire/Abm/MateriasAnio/MateriasAnioIndex.php`
- `resources/views/livewire/abm/materias-anio/index.blade.php`
- CSS grilla: `.gf-materias-anio` en `resources/css/app.css`

## Qué no hacer

- No guardar IDs de curso/curplan/matplan fuera del alcance del nivel/ciclo.
- No calcular promedios aquí; solo configuración de materias del año.

## Checklist al modificar

- [ ] Labels de FK usan tablas relacionadas (no solo el ID).
- [ ] Selects de edición conservan el `value` numérico.
- [ ] Grilla ancha: scroll horizontal con `justify-start` (no centrar bajo el sidebar).
