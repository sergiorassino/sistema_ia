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
| `ento` | `mensajeBloqPeda`, `mensajeBloqAdmi` | Cartel en autogestión (ficha / datos) por nivel. No se usa en el comunicado. |
| `ento` | `mensajeComBloqMatricula`, `mensajeComDesbloqMatricula` | Cuerpo de Notif. Bloqueo / Notif. Desbloqueo (cuaderno + mail). Vacío = texto institucional por defecto. Marcadores `{motivos}`, `{contacto}`, `{requisitos}`. |
| `com_*` | hilos / envíos | Comunicado institucional al notificar (mismo mecanismo que sanciones). |

## Flujo principal

1. Elegir curso o «Todos los cursos».
2. Opcional: estado **Todos** / **Bloqueo pedagógico** (`bloqmatr = 1`) / **Bloqueo administrativo** (`bloqadmi = 1`) / **Bloqueo administrativo y pedagógico** (los dos en 1) / **No bloqueados** (ninguno de los dos en 1). Pedagógico y administrativo incluyen a quienes tienen el otro bloqueo también. El masivo respeta este filtro.
3. Opcional: filtrar por apellido, nombre o DNI (mismo criterio que legajos; el masivo respeta ese filtro).
4. Alternar SÍ/NO por fila (guarda al instante).
5. Acciones masivas: bloquear o desbloquear **pedagógico** o **administrativo** para todos los alumnos del filtro actual (todas las páginas, no solo la visible). Confirmación SweetAlert con cantidad.
6. **Notif. Bloqueo** (habilitado si hay al menos un bloqueo activo): el diálogo de confirmación muestra el **estudiante** y los **correos válidos** del legajo (madre / padre / tutor) que se usarán en el refuerzo. Crea un comunicado institucional hacia la familia del alumno (remitente = usuario logueado), con push si el canal lo permite y **refuerzo por correo**. El cuerpo sale de `ento.mensajeComBloqMatricula` del **nivel del alumno** (Parametrización → PARÁMETROS); si está vacío, el texto institucional por defecto. Marcadores `{motivos}` (`PEDAGÓGICOS`, `ADMINISTRATIVOS` o ambos) y `{contacto}` (Secretaría de Nivel [nivel del alumno] y, si aplica, Administración). Incluye `Estudiante`, `Curso` y `Nivel`. El correo de refuerzo usa **el mismo cuerpo** y se envía a **todos** los mails válidos del legajo (`emailmad`, `emailpad`, `emailtut`); el resto del módulo de comunicaciones sigue enviando un solo mail (madre→padre→tutor).
7. **Notif. Desbloqueo** (habilitado si no hay bloqueos activos; ambos botones se muestran siempre en la columna Avisar): mismo canal y refuerzo de mail. Cuerpo de `ento.mensajeComDesbloqMatricula` (vacío = texto por defecto). Marcador `{requisitos}` según lo liberado (`pedagógicos`, `administrativos` o ambos). Incluye `Estudiante`, `Curso` y `Nivel`.

## Fuente de verdad

Columnas `matricula.bloqmatr` y `matricula.bloqadmi` del ciclo activo. Lectura en cuotas/SIRO vía `MatriculaBloqueos`.

En autogestión familia, esos flags impiden entrar a **Actualización de Datos Personales** e **Imprimir Ficha de Matrícula**. El cartel visible es `ento.mensajeBloqPeda` / `ento.mensajeBloqAdmi` del nivel del alumno (Parametrización → Parámetros). Ese cartel **no** es el texto de Notif. Bloqueo / Desbloqueo.

En **Aranceles** de autogestión y en **Gestión de aranceles** (nivel Administración), no se emite el cupón ni se imputa el pago de las cuotas **reserva** (`cuotasgeneradas.idCuotastipo = 2`) ni **matrícula** (`idCuotastipo = 3`) cuando hay bloqueo pedagógico, administrativo o ambos, o cuando hay **deuda familiar vencida**. El botón queda deshabilitado y el PDF rechaza el enlace directo. En Administración, el cobro múltiple tampoco incluye esas cuotas. El cartel del bloqueo es el de `ento`; el de la deuda avisa que hay saldo con 2.º vencimiento ya cumplido y sin aviso de pago (misma base que la subida a SIRO). Si el alumno no tiene familia asignada (`idFamilias` 0 o 1), esa deuda es solo la suya. Las demás cuotas (marzo, abril, etc.) se descargan y se cobran igual. En autogestión el bloqueo se toma de la matrícula de la sesión; en Administración, de la matrícula del estudiante en el ciclo activo. Si esa no está bloqueada, se mira la matrícula del ciclo de la cuota.

## Archivos clave

- `app/Livewire/MatriculaWeb/BloqueosMatriculaIndex.php`
- `app/Support/MatriculaWeb/BloqueosMatriculaConsulta.php`
- `app/Support/MatriculaWeb/BloqueosMatriculaService.php`
- `app/Support/MatriculaWeb/NotificarFamiliaBloqueoMatricula.php`
- `resources/views/livewire/matricula-web/bloqueos-matricula-index.blade.php`
- Cupón autogestión: `app/Support/Alumnos/ArancelesEscolares.php`, `app/Support/Cuotas/DeudaFamiliarVencida.php`, `app/Http/Controllers/Alumnos/ComprobantePagoPdfController.php`
- Cupón y cobro en Gestión de aranceles: `app/Livewire/Cuotas/CuotasEstudianteShow.php`, `app/Livewire/Cuotas/ImputarPagoForm.php`, `app/Http/Controllers/Cuotas/ComprobantePagoCuotasPdfController.php`
- Mensajes por nivel: `app/Livewire/Parametrizacion/ParametrosSistemaForm.php` (solapa PARÁMETROS)

## Qué no hacer / reglas de negocio

- No actualizar `legajos.bloqmatr` / `bloqadmi`.
- No bloquear el cupón ni el cobro de cuotas que no sean reserva (2) o matrícula (3).
- No aplicar el masivo fuera del filtro de curso / búsqueda / estado (Todos, Bloqueo pedagógico, Bloqueo administrativo, Bloqueo administrativo y pedagógico, No bloqueados) / `queryBase` (revalidar IDs con `idTerlec` y alcance de nivel).
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
- [ ] ¿El cupón de reserva/matrícula en autogestión usa `cuponBloqueadoPorMatricula()` y `cuponBloqueadoPorDeudaFamiliar()` (listado y PDF)?
- [ ] ¿Notif. Bloqueo / Desbloqueo exige canal remitente→familia y reporta estado del correo de refuerzo?
- [ ] ¿El cuerpo del aviso usa `mensajeComBloqMatricula` / `mensajeComDesbloqMatricula` y no los carteles de autogestión?
