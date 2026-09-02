# Módulo: Libro de temas

## Propósito

Registrar las clases dictadas de cada materia (fecha, número de clase, unidad, carácter, temas, actividades y observaciones). Sirve como libro de temas digital del docente y consulta de Secretaría.

## Modalidades / variantes

| Superficie | Cómo se habilita | Qué hace |
|------------|------------------|----------|
| **Menú de Secretaría** / **Administración** | `tenant.modulos.libro_de_temas` **y** permiso IA **101** | Elige curso y materia (dos `<select>`) del ciclo/nivel y ABM de clases |
| **Menú de Docentes** | Mismo flag de módulo **y** `portal_docente.menu.{nivel}.libro_de_temas` | Solo materias asignadas en `ppc`; mismo ABM, sin `permisos_ia` |

Default **off**. Hoy lo activa **iess** (`config/tenants/iess.php`) en inicial, primario y secundario.

## Actores y permisos

| Rol | Acceso | Alcance |
|-----|--------|---------|
| Secretaría / Administración | `PermisosIaCatalog::LIBRO_DE_TEMAS` (orden **101**) | Materias del contexto (`idTerlec` + nivel; en Administración, todos los pedagógicos) |
| Docente (portal / Autogestión Docente) | Config tenant, sin permiso IA | Solo `ppc` del profesor en el ciclo/nivel de sesión |

Rutas staff: `docentes.libro-de-temas` + middleware `permiso:101`.  
Rutas portal: `portalDocente.libroDeTemas` (`menu.portal:docente`).

## Tablas y campos críticos

Tabla **legacy** `librodetemas` (no se crea en IESS; SQL idempotente para otros tenants: `database/sql/librodetemas_tabla_idempotente.sql`).

| Campo | Notas |
|-------|--------|
| `idMateria` | FK lógica a `materias.id`. El curso, nivel y ciclo salen de la materia. |
| `fecha` | Fecha de la clase (`d/m/Y` en UI) |
| `claseNro` | Número de clase (puede ser 0 en días sin dictado) |
| `unidad` | Unidad curricular |
| `caracter` | Texto libre (sugerencias: Introducción, Desarrollo, Cierre, etc.) |
| `temas` / `actividades` / `observaciones` | Texto. Observaciones se usa p. ej. para paro, feriado o clase suspendida |

No hay `idProfesor` en la tabla: el libro es **por materia**.

## Flujo principal

1. Elegir **curso** y luego **materia** en dos desplegables (mismo patrón que carga de calificaciones). Secretaría: cursos/materias del ciclo/nivel; docente: solo `ppc`. Materia deshabilitada hasta elegir curso. **Abrir libro**.
2. Grilla tipo planilla (`.gf` / celdas) cronológica. Búsqueda sobre temas, actividades, observaciones y carácter.
3. **Nueva Clase** → modal (fecha de hoy, próximo `claseNro`, última unidad).
4. **Editar** / **Eliminar** (SweetAlert) una fila.
5. **Insertar Copia de Última clase guardada**: duplica el último registro (`id` más alto) de esa materia, para dos horas seguidas.

## Fuente de verdad

| Dato | Quién escribe | Quién solo lee |
|------|---------------|----------------|
| Clases | Livewire `LibroDeTemasClases` | Listado / búsqueda |
| Materias y docentes | `materias` + `ppc` | Selects de curso y materia |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Livewire | `app/Livewire/Docentes/LibroDeTemas/LibroDeTemasIndex.php`, `LibroDeTemasClases.php` |
| Vistas | `resources/views/livewire/docentes/libro-de-temas/` |
| Servicio | `app/Support/LibroDeTemas/LibroDeTemasService.php` |
| Modelo | `app/Models/LibroDeTema.php` |
| Menú Secretaría | `resources/views/layouts/partials/sidebar-grupo-docentes-usuarios.blade.php` |
| Menú Docentes | `PortalDocenteMenuCatalog` (`*.libro_de_temas`) |
| Permiso | `PermisosIaCatalog::LIBRO_DE_TEMAS` (101) |
| Config | `config/tenant.php` → `modulos.libro_de_temas`; `config/tenants/iess.php` |

## Qué no hacer / reglas de negocio

1. No ramificar por `tenantSlug() === 'iess'`; usar `tenantLibroDeTemasHabilitado()` / flags de menú.
2. No listar ni mutar clases de una materia fuera del ciclo/nivel (ni fuera de `ppc` en el portal). En el Menú de Docentes el alcance `ppc` se fija en `mount` (`modoPortalDocente` locked): no usar `request()->routeIs()` en `render` / acciones Livewire.
3. No poner IDs de alumno/DNI en la URL; `idMateria` en ruta staff/portal es el mismo criterio que calificaciones.
4. La copia inserta de inmediato; no abre el modal. Si no hay clases, avisar y no crear fila vacía.

## Checklist al modificar

- [ ] Flag tenant + permiso 101 en Secretaría; menú portal por nivel.
- [ ] Filtro `schoolCtx()` / `SchoolAlcancePedagogico` y revalidación por ID.
- [ ] Fechas en UI en `d/m/Y`.
- [ ] Confirmaciones con `seSwalConfirmar` / eventos `se-swal-*`.
- [ ] Paginación `se-compact`.
- [ ] Persistencia con `PersistenciaColumnas` (sin falso éxito si falta columna).
