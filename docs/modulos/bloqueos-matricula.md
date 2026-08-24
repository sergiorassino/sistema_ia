# Módulo: Bloqueos de matrícula

## Propósito

Consultar y editar los bloqueos pedagógico (`bloqmatr`) y administrativo (`bloqadmi`) de alumnos regulares del ciclo y nivel activos, de a uno o de forma masiva sobre el listado filtrado. Notificar a la familia el bloqueo o el desbloqueo.

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
| `com_*` | hilos / envíos | Comunicado institucional al notificar (mismo mecanismo que sanciones). |

## Flujo principal

1. Elegir curso o «Todos los cursos».
2. Opcional: filtrar por apellido, nombre o DNI (mismo criterio que legajos; el masivo respeta ese filtro).
3. Alternar SÍ/NO por fila (guarda al instante).
4. Acciones masivas: bloquear o desbloquear **pedagógico** o **administrativo** para todos los alumnos del filtro actual (todas las páginas, no solo la visible). Confirmación SweetAlert con cantidad.
5. **Notif. Bloqueo** (habilitado si hay al menos un bloqueo activo): el diálogo de confirmación muestra el **estudiante** y los **correos válidos** del legajo (madre / padre / tutor) que se usarán en el refuerzo. Crea un comunicado institucional hacia la familia del alumno (remitente = usuario logueado), con push si el canal lo permite y **refuerzo por correo**. Saludo «Estimada Familia»; motivos según bloqueos activos (`PEDAGÓGICOS`, `ADMINISTRATIVOS` o `PEDAGÓGICOS y/o ADMINISTRATIVOS`); contacto con Secretaría de Nivel [nivel del alumno] y, si aplica, Administración. Incluye `Estudiante`, `Curso` y `Nivel`. El correo de refuerzo se envía a **todos** los mails válidos del legajo (`emailmad`, `emailpad`, `emailtut`); el resto del módulo de comunicaciones sigue enviando un solo mail (madre→padre→tutor).
6. **Notif. Desbloqueo** (habilitado si no hay bloqueos activos; ambos botones se muestran siempre en la columna Avisar): mismo canal y refuerzo de mail. Texto de desbloqueo con requisitos según lo liberado (`pedagógicos`, `administrativos` o `administrativos y/o pedagógicos`) e indica que pueden continuar el trámite de matriculación. Incluye `Estudiante`, `Curso` y `Nivel`.

## Fuente de verdad

Columnas `matricula.bloqmatr` y `matricula.bloqadmi` del ciclo activo. Lectura en cuotas/SIRO vía `MatriculaBloqueos`.

En autogestión familia, esos flags impiden entrar a **Actualización de Datos Personales** e **Imprimir Ficha de Matrícula**. El texto visible es `ento.mensajeBloqPeda` / `ento.mensajeBloqAdmi` del nivel del alumno (Parametrización → Parámetros).

## Archivos clave

- `app/Livewire/MatriculaWeb/BloqueosMatriculaIndex.php`
- `app/Support/MatriculaWeb/BloqueosMatriculaConsulta.php`
- `app/Support/MatriculaWeb/BloqueosMatriculaService.php`
- `app/Support/MatriculaWeb/NotificarFamiliaBloqueoMatricula.php`
- `resources/views/livewire/matricula-web/bloqueos-matricula-index.blade.php`
- Mensajes por nivel: `app/Livewire/Parametrizacion/ParametrosSistemaForm.php` (solapa PARÁMETROS)

## Qué no hacer / reglas de negocio

- No actualizar `legajos.bloqmatr` / `bloqadmi`.
- No aplicar el masivo fuera del filtro de curso / búsqueda / `queryBase` (revalidar IDs con `idTerlec` y alcance de nivel).
- Guardado masivo con `PersistenciaColumnas` (sin falso éxito si falta la columna).
- Confirmación con `seSwalConfirmar`; no `wire:confirm` ni `window.confirm`.
- No notificar bloqueo si no hay bloqueo activo; no notificar desbloqueo si aún hay bloqueo; revalidar flags en servidor.
- El `id_nivel` del hilo es el **nivel pedagógico del alumno** (relevante en sesión Administración).

## Checklist al modificar

- [ ] ¿Listado y masivo filtrados por `schoolCtx()` / `SchoolAlcancePedagogico`?
- [ ] ¿Solo regulares sin fecha de baja?
- [ ] ¿Curso inválido no cae a «todos» en el masivo sin `validarIdCurso`?
- [ ] ¿Rate-limit en toggle individual, masivo y notificación?
- [ ] ¿Autogestión (ficha + datos personales) usa `MatriculaBloqueos::impideFichaYDatosAutogestion()` y el mensaje de `ento` del nivel?
- [ ] ¿Notif. Bloqueo / Desbloqueo exige canal remitente→familia y reporta estado del correo de refuerzo?
