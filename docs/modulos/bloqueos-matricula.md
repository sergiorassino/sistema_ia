# Módulo: Bloqueos de matrícula

## Propósito

Consultar y editar los bloqueos pedagógico (`bloqmatr`) y administrativo (`bloqadmi`) de alumnos regulares del ciclo y nivel activos, de a uno o de forma masiva sobre el listado filtrado.

## Modalidades / variantes

Ninguna por tenant. El alcance de nivel sigue `SchoolAlcancePedagogico` (Administración ve todos los niveles pedagógicos).

## Actores y permisos

Menú de Secretaría (`layouts/app`). Permiso `permisos_ia` orden **82** (`PermisosMatriculaWeb::BLOQUEOS_MATRICULA`). Rutas bajo `auth`; datos filtrados por `schoolCtx()`.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `matricula` | `bloqmatr`, `bloqadmi` | Tinyint 0/1 por ciclo. No tocar `legajos.bloqmatr` / `bloqadmi` (legacy Scriptcase). |
| `matricula` | `idTerlec`, `idNivel`, `idCondiciones`, `fechaBaja` | Solo regulares (`idCondiciones = 1`) sin baja. |
| `cursos` | filtro opcional `idCursos` | 0 = todos los cursos del nivel, orden alfabético. |
| `ento` | `mensajeBloqPeda`, `mensajeBloqAdmi` | Mensajes por nivel (solapa PARÁMETROS → Bloqueos de Matrícula). |

## Flujo principal

1. Elegir curso o «Todos los cursos».
2. Alternar SÍ/NO por fila (guarda al instante).
3. Acciones masivas: bloquear o desbloquear **pedagógico** o **administrativo** para todos los alumnos del filtro actual (todas las páginas, no solo la visible). Confirmación SweetAlert con cantidad.

## Fuente de verdad

Columnas `matricula.bloqmatr` y `matricula.bloqadmi` del ciclo activo. Lectura en cuotas/SIRO vía `MatriculaBloqueos`.

En autogestión familia, esos flags impiden entrar a **Actualización de Datos Personales** e **Imprimir Ficha de Matrícula**. El texto visible es `ento.mensajeBloqPeda` / `ento.mensajeBloqAdmi` del nivel del alumno (Parametrización → Parámetros).

## Archivos clave

- `app/Livewire/MatriculaWeb/BloqueosMatriculaIndex.php`
- `app/Support/MatriculaWeb/BloqueosMatriculaConsulta.php`
- `app/Support/MatriculaWeb/BloqueosMatriculaService.php`
- `resources/views/livewire/matricula-web/bloqueos-matricula-index.blade.php`
- Mensajes por nivel: `app/Livewire/Parametrizacion/ParametrosSistemaForm.php` (solapa PARÁMETROS)

## Qué no hacer / reglas de negocio

- No actualizar `legajos.bloqmatr` / `bloqadmi`.
- No aplicar el masivo fuera del filtro de curso / `queryBase` (revalidar IDs con `idTerlec` y alcance de nivel).
- Guardado masivo con `PersistenciaColumnas` (sin falso éxito si falta la columna).
- Confirmación con `seSwalConfirmar`; no `wire:confirm` ni `window.confirm`.

## Checklist al modificar

- [ ] ¿Listado y masivo filtrados por `schoolCtx()` / `SchoolAlcancePedagogico`?
- [ ] ¿Solo regulares sin fecha de baja?
- [ ] ¿Curso inválido no cae a «todos» en el masivo sin `validarIdCurso`?
- [ ] ¿Rate-limit en toggle individual y en masivo?
- [ ] ¿Autogestión (ficha + datos personales) usa `MatriculaBloqueos::impideFichaYDatosAutogestion()` y el mensaje de `ento` del nivel?
