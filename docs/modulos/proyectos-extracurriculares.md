# Módulo: Calendario Institucional

## Propósito

Los docentes proponen actividades extraprogramáticas a dirección. El directivo las aprueba (pasan al calendario escolar) y puede comunicar a organizadores, docentes del curso (`ppc`) y preceptores (`preceptoresporcurso`). El calendario (mes / semana / día) se ve en Secretaría, en el Menú de Docentes y como widget en ambos escritorios.

## Modalidades / variantes

| Superficie | Cómo se habilita | Qué hace |
|------------|------------------|----------|
| **Menú de Docentes** | `tenant.portal_docente.menu.{nivel}.proyectos_extracurriculares` y `calendario_escolar` (default `true`) | Alta/edición de propuestas propias; calendario de aprobadas |
| **Menú de Secretaría** | Niveles pedagógicos 1–4 | **Calendario Institucional** con permiso IA **109**; calendario (todos); **Aprobar actividades** con permiso IA **96**; **Autorizaciones y notificaciones** con permiso IA **110** |

No hay ítem en el Menú de Alumnos ni en Administración.

## Actores y permisos

| Rol | Permiso / acceso | Alcance |
|-----|------------------|---------|
| Docente (portal aula) | Catálogo del Menú de Docentes (sin `permiso_ia`) | CRUD de **sus** actividades pendientes; ver calendario del nivel/ciclo |
| Dirección / Secretaría | `PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_VER` (orden **109**) | Alta/edición de **sus** propuestas (ítem **Calendario Institucional**) |
| Dirección / Secretaría | `PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_APROBAR` (orden **96**) | Listado del nivel/ciclo, aprobar, volver a pendiente, comunicar |
| Dirección / Secretaría | `PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_DOCUMENTOS` (orden **110**) | Listar actividades aprobadas, revisar alumnos e imprimir autorizaciones |
| Personal de Secretaría | Sin 109 | Calendario y widget (actividades **aprobadas**). Sin 96 tampoco aprueba |

## Tablas y campos críticos

Tablas **nuevas** (prefijo `ext_`):

| Tabla | Campos | Notas |
|-------|--------|--------|
| `ext_tipo_registro` | `id`, `nombre` | Semillas: `1` Actividad Extraprogramática, `2` Actividad de Nivel, `3` Actividad Institucional. Relacionada con `ext_actividades` |
| `ext_actividades` | nombre, lugar, horario, descripción, evaluación, presupuesto, `tipo_grupo` (`cursos`\|`alumnos`), `estado` (`pendiente`\|`aprobado`), contexto nivel/ciclo, proponente | Una fila por proyecto |
| `ext_fechas` | `fecha`, `hora_inicio`, `hora_fin` | Uno o más días por proyecto |
| `ext_actividad_cursos` | `id_curso` | Si el grupo es por cursos |
| `ext_actividad_alumnos` | `id_legajo` | Si el grupo es por alumnos |
| `ext_actividad_docentes` | `id_profesor`, `rol` (`a_cargo`\|`otro`) | Legajos con rol Profesor/a (`IdTipoProf = 6`) |

SQL: `database/sql/create_ext_proyectos_extracurriculares.sql` · tipos 2 y 3: `database/sql/seed_ext_tipo_registro_nivel_institucional.sql` · presupuesto: `database/sql/add_presupuesto_to_ext_actividades.sql` · permisos: `database/sql/permiso_ia_orden_96_proyectos_extracurriculares.sql`, `database/sql/permiso_ia_orden_109_proyectos_extracurriculares_ver.sql` y `database/sql/permiso_ia_orden_110_autorizaciones_actividades_extracurriculares.sql` · nombre visible del catálogo: `database/sql/rename_permisos_ia_actividad_institucional.sql` y `database/sql/rename_permisos_ia_calendario_institucional.sql`.

**Campos según el tipo.** Actividad Extraprogramática usa el formulario completo (incluye evaluación, presupuesto y otros docentes, y la descripción con subtítulos Previas/Durante/Posteriores). Actividad de Nivel y Actividad Institucional usan nombre, una o varias fechas, lugar, horario, descripción, cursos o alumnos, y docentes a cargo.

## Flujo principal

1. El docente abre **Calendario Institucional**, elige el tipo de registro y carga el formulario (fechas por día, grupo y docentes a cargo). En Actividad Extraprogramática también completa la descripción con subtítulos Previas/Durante/Posteriores, la evaluación, el presupuesto y, si corresponde, otros docentes. Presenta a dirección.
2. Dirección abre **Aprobar actividades**, revisa el detalle y marca **Aprobado**. Desde entonces figura en el calendario.
3. **Comunicar** abre un diálogo con el listado de destinatarios (nombre y participación: docente a cargo, otro docente, docente del curso `ppc`, preceptor). Envía hilos `scope=docentes` por canal habilitado y, en producción, **correo de refuerzo** (`profesores.email` / `emailInsti`). En `APP_ENV=local` el correo no se pide (queda el comunicado interno); para una prueba SMTP real: `MAIL_FORCE_REAL=true`. El remitente no figura en el listado ni recibe el aviso.
4. Secretaría y docentes ven el **calendario** (mes/semana/día) y el widget del escritorio (próximas fechas desde hoy). Clic en la actividad abre el detalle. En mes, semana y día, el nombre se escribe con el color del nivel del legajo del estudiante (inicial, primario o secundario). El tipo **Actividad Institucional** va en negro.
5. Con el permiso **110**, Secretaría abre **Autorizaciones y notificaciones**, elige una actividad aprobada y revisa los alumnos. **Autorizaciones** genera un PDF: membrete con logo y datos de `ento` (institución, dirección, teléfono/fax, correo, localidad). La segunda línea y la leyenda de adscripción salen de `config/tenants/{slug}.php` (`membrete_subtitulo`, `membrete_adscripcion`); si no hay subtítulo, se usa `ento.categoria`. Debajo va el formulario en el orden del modelo en papel. **Notificaciones** muestra el mismo listado; el PDF queda para una versión siguiente.

## Fuente de verdad

| Dato | Quién escribe | Quién solo lee |
|------|---------------|----------------|
| Actividad pendiente | Docente, directivo o secretario proponente | Dirección (listado de aprobación) |
| Estado aprobado | Dirección (permiso 96) | Calendario / widget; autorizaciones (permiso 110) |
| Comunicado | Dirección (permiso 96) | Bandeja de involucrados (+ correo de refuerzo en producción) |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Servicio | `app/Support/ProyectosExtracurriculares/ExtActividadesService.php` |
| Formulario docente | `app/Livewire/ProyectosExtracurriculares/ProyectoForm.php` |
| Gestión dirección | `app/Livewire/ProyectosExtracurriculares/GestionIndex.php` |
| Autorizaciones | `app/Livewire/ProyectosExtracurriculares/AutorizacionesIndex.php`, `AutorizacionAlumnos.php`, `app/Support/ProyectosExtracurriculares/AutorizacionActividadTcpdf.php` |
| Calendario | `app/Livewire/ProyectosExtracurriculares/CalendarioEscolar.php` |
| Widget escritorio | `app/Livewire/ProyectosExtracurriculares/CalendarioWidget.php` |

## Qué no hacer / reglas de negocio

1. No listar ni editar actividades de otro nivel o ciclo que el de `schoolCtx()`.
2. El docente no edita ni borra una actividad ya aprobada.
3. El calendario **solo** muestra `estado = aprobado`.
4. No poner el ID numérico de la actividad en la URL del portal docente (edición con `OpaqueRouteToken`).
5. No mostrar éxito si faltan las tablas `ext_*`.
6. La comunicación no incluye al remitente; si no hay canal hacia un rol, ese grupo se omite con aviso.
7. El correo de refuerzo se agrega al canal (aunque el canal no tenga medio email). En desarrollo (`APP_ENV=local`) no se envía; `MailDesarrollo` también bloquea SMTP real.
8. La autorización solo se imprime de una actividad **aprobada** del nivel y ciclo de `schoolCtx()`. Los alumnos del PDF se revalidan contra el grupo de la actividad (cursos regulares o alumnos elegidos, sin baja).
9. Las fechas del formulario viven en Alpine (`wire:ignore`): agregar/quitar día no llama a Livewire. Solo al guardar se envían como JSON a `guardar()`.

## Checklist al modificar

- [ ] Permiso 109 en el ítem «Calendario Institucional» de Secretaría, rutas de alta/edición y `PermisosIaCatalog`.
- [ ] Permiso 96 en gestión, sidebar «Aprobar actividades» y `PermisosIaCatalog`.
- [ ] Permiso 110 en «Autorizaciones y notificaciones», rutas del PDF y `PermisosIaCatalog`.
- [ ] Filtro `id_nivel` + `id_terlec` en consultas y por ID.
- [ ] Fechas en UI en `d/m/Y`.
- [ ] Confirmaciones con `seSwalConfirmar` / eventos `se-swal-*`.
- [ ] Paginación `se-compact` en listados.
- [ ] Calendario usable en Secretaría y Menú de Docentes; widget en ambos escritorios.
- [ ] Comunicar: correo de refuerzo en producción; omitido en `APP_ENV=local` (`MailDesarrollo`).
