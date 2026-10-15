# Módulo: Gestión de TEA

## Propósito

Registrar situaciones de reincorporación por inasistencias (TEA) de un alumno regular del curso, y emitir el PDF del registro.

## Modalidades / variantes

El PDF del registro depende del tenant (`tenant.secretaria.tea_registros`). Las tablas de registros son las mismas.

## Actores y permisos

- Menú de Secretaría → grupo **ASISTENCIA ESTUDIANTES** → **Gestión de TEA**.
- Permiso `permisos_ia` orden **85** (`PermisosIaCatalog::TEA_ESTUDIANTES_GESTION`).

## Tablas y campos críticos

| Tabla | Uso |
|-------|-----|
| `reinco2025` | Registros: `idMatricula`, `idReinco_tipo`, `fecha`, `obs` |
| `reinco2025_tipo` | Catálogo de situaciones TEA |
| `matricula` / `legajos` / `cursos` | Selector de curso y alumno; alcance `schoolCtx()` (nivel + ciclo) |

Regular = `matricula.idCondiciones = 1` (`ListadoCursoCondicionFiltro::REGULARES`).

## Flujo principal

1. Elegir curso del nivel y ciclo activos.
2. El desplegable de alumno lista solo matrículas regulares de ese curso, en orden alfabético.
3. Con el alumno elegido se listan sus registros TEA, y se puede alta, edición, baja o PDF.

Si la sesión tenía un alumno que ya no es regular, el selector lo descarta.

## Fuente de verdad

- Condición regular: `ListadoCursoCondicionFiltro`.
- Registros y matrícula en contexto: `App\Support\Tea\ReincoTea`.

## Archivos clave

- `app/Livewire/Seguimiento/Tea/TeaIndex.php`
- `app/Livewire/Seguimiento/Tea/TeaForm.php`
- `resources/views/livewire/seguimiento/tea/index.blade.php`
- `app/Support/Tea/ReincoTea.php`

## Qué no hacer / reglas de negocio

- No listar salidos ni el resto de condiciones en el selector de alumno. Solo regulares (`idCondiciones = 1`).
- No filtrar el curso por condición: el curso muestra todos los del contexto; el filtro es del alumno.

## Checklist al modificar

- [ ] El desplegable de alumno sigue limitado a regulares del curso, nivel y ciclo activos.
- [ ] Una matrícula no regular guardada en sesión no queda seleccionada.
