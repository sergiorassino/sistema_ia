# Módulo: Proyectos extracurriculares

## Propósito

Los docentes proponen actividades extraprogramáticas a dirección. El directivo las aprueba (pasan al calendario escolar) y puede comunicar a organizadores, docentes del curso (`ppc`) y preceptores (`preceptoresporcurso`). El calendario (mes / semana / día) se ve en Secretaría, en el Menú de Docentes y como widget en ambos escritorios.

## Modalidades / variantes

| Superficie | Cómo se habilita | Qué hace |
|------------|------------------|----------|
| **Menú de Docentes** | `tenant.portal_docente.menu.{nivel}.proyectos_extracurriculares` y `calendario_escolar` (default `true`) | Alta/edición de propuestas propias; calendario de aprobadas |
| **Menú de Secretaría** | Niveles pedagógicos 1–4 | Calendario (todos); **Aprobar proyectos** con permiso IA **96** |

No hay ítem en el Menú de Alumnos ni en Administración.

## Actores y permisos

| Rol | Permiso / acceso | Alcance |
|-----|------------------|---------|
| Docente (portal aula) | Catálogo del Menú de Docentes (sin `permiso_ia`) | CRUD de **sus** proyectos pendientes; ver calendario del nivel/ciclo |
| Dirección / Secretaría | `PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_APROBAR` (orden **96**) | Listado del nivel/ciclo, aprobar, volver a pendiente, comunicar |
| Personal de Secretaría | Sin 96 | Solo calendario y widget (actividades **aprobadas**) |

## Tablas y campos críticos

Tablas **nuevas** (prefijo `ext_`):

| Tabla | Campos | Notas |
|-------|--------|--------|
| `ext_tipo_registro` | `id`, `nombre` | Semilla `id=1` «Actividad Extraprogramática». Relacionada con `ext_actividades` |
| `ext_actividades` | nombre, lugar, horario, descripción, evaluación, `tipo_grupo` (`cursos`\|`alumnos`), `estado` (`pendiente`\|`aprobado`), contexto nivel/ciclo, proponente | Una fila por proyecto |
| `ext_fechas` | `fecha`, `hora_inicio`, `hora_fin` | Uno o más días por proyecto |
| `ext_actividad_cursos` | `id_curso` | Si el grupo es por cursos |
| `ext_actividad_alumnos` | `id_legajo` | Si el grupo es por alumnos |
| `ext_actividad_docentes` | `id_profesor`, `rol` (`a_cargo`\|`otro`) | Legajos con rol Profesor/a (`IdTipoProf = 6`) |

SQL: `database/sql/create_ext_proyectos_extracurriculares.sql` · permiso: `database/sql/permiso_ia_orden_96_proyectos_extracurriculares.sql`.

## Flujo principal

1. El docente abre **Proyectos extracurriculares**, carga el formulario (tipo de registro fijo, fechas por día, grupo, docentes, descripción con subtítulos Previas/Durante/Posteriores, evaluación) y presenta a dirección.
2. Dirección abre **Aprobar proyectos**, revisa el detalle y marca **Aprobado**. Desde entonces figura en el calendario.
3. **Comunicar** arma destinatarios: docentes a cargo y otros del proyecto, docentes `ppc` de los cursos involucrados, preceptores de esos cursos. Envía hilos `scope=docentes` por canal habilitado.
4. Secretaría y docentes ven el **calendario** (mes/semana/día) y el widget del escritorio (próximas fechas desde hoy). Clic en la actividad abre el detalle.

## Fuente de verdad

| Dato | Quién escribe | Quién solo lee |
|------|---------------|----------------|
| Proyecto pendiente | Docente proponente | Dirección (listado) |
| Estado aprobado | Dirección (permiso 96) | Calendario / widget |
| Comunicado | Dirección (permiso 96) | Bandeja de involucrados |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Servicio | `app/Support/ProyectosExtracurriculares/ExtActividadesService.php` |
| Formulario docente | `app/Livewire/ProyectosExtracurriculares/ProyectoForm.php` |
| Gestión dirección | `app/Livewire/ProyectosExtracurriculares/GestionIndex.php` |
| Calendario | `app/Livewire/ProyectosExtracurriculares/CalendarioEscolar.php` |
| Widget escritorio | `app/Livewire/ProyectosExtracurriculares/CalendarioWidget.php` |

## Qué no hacer / reglas de negocio

1. No listar ni editar proyectos de otro nivel o ciclo que el de `schoolCtx()`.
2. El docente no edita ni borra un proyecto ya aprobado.
3. El calendario **solo** muestra `estado = aprobado`.
4. No poner el ID numérico del proyecto en la URL del portal docente (edición con `OpaqueRouteToken`).
5. No mostrar éxito si faltan las tablas `ext_*`.
6. La comunicación no incluye al remitente; si no hay canal hacia un rol, ese grupo se omite con aviso.

## Checklist al modificar

- [ ] Permiso 96 en gestión, sidebar «Aprobar proyectos» y `PermisosIaCatalog`.
- [ ] Filtro `id_nivel` + `id_terlec` en consultas y por ID.
- [ ] Fechas en UI en `d/m/Y`.
- [ ] Confirmaciones con `seSwalConfirmar` / eventos `se-swal-*`.
- [ ] Paginación `se-compact` en listados.
- [ ] Calendario usable en Secretaría y Menú de Docentes; widget en ambos escritorios.
