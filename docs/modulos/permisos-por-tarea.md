# Módulo: Permisos por Tarea

## Propósito

Consulta **quién tiene acceso a cada módulo o función** del catálogo `permisos_ia`, en el nivel de sesión. Es la vista inversa de **Permisos por Usuario**: agrupa por tarea y lista los usuarios habilitados.

No edita la cadena `profesores.permisos_ia`. La asignación sigue en **Asignación de Permisos de Usuario**.

## Modalidades / variantes

Ninguna. Mismo listado en Menú de Secretaría y Menú de Administración.

## Actores y permisos

- Menú de Secretaría / Administración → Configuración → **Permisos del sistema** → **Permisos por Tarea**.
- Permiso `permisos_ia` orden **99** (`PermisosIaCatalog::PERMISOS_POR_TAREA`).
- No hay ítem en Menú de Docentes ni Menú de Alumnos.

El nivel es el de `schoolCtx()->idNivel` (selector de contexto). No filtra por ciclo lectivo: los permisos viven en el legajo de `profesores`.

## Tablas y campos críticos

| Tabla | Uso |
|-------|-----|
| `permisos_ia` | Catálogo: `orden`, `tema`, `descripcion` |
| `profesores` | Cadena `permisos_ia` (carácter en posición `orden`) y datos de usuario |
| `profesortipo` | Rol mostrado en el chip (`tipo`) |

SQL del permiso: `database/sql/permiso_ia_orden_99_permisos_por_tarea.sql`.

Migración (`php artisan se:migrate-legacy --force`, no ejecutar desde el asistente): `database/migrations/2026_08_31_120000_add_permiso_ia_orden_99_permisos_por_tarea.php` (solo INSERT/UPDATE del catálogo). El `UPDATE` que alarga `profesores.permisos_ia` va en el SQL revisable.

## Flujo principal

1. Usuario con permiso 99 abre **Permisos por Tarea**.
2. Ve solo las tareas del catálogo que tienen **al menos un** usuario del nivel con `'1'` en esa posición.
3. Puede filtrar por **tema** y buscar por descripción, apellido, nombre o DNI.
4. Cada fila muestra la descripción de la tarea y chips con los usuarios habilitados (excluye rol «Sin Rol», `IdTipoProf = 1`).

## Fuente de verdad

| Dato | Quién escribe | Quién solo lee |
|------|---------------|----------------|
| Cadena `profesores.permisos_ia` | Asignación de Permisos de Usuario (orden 0) | Este módulo y Permisos por Usuario (14) |
| Catálogo `permisos_ia` | Migraciones / SQL de cada orden | Listados y el editor de asignación |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Livewire | `app/Livewire/Administracion/Permisos/PermisosPorTareaIndex.php` |
| Vista | `resources/views/livewire/administracion/permisos/por-tarea-index.blade.php` |
| Ruta | `admin.permisos-por-tarea` (`/administracion/permisos-por-tarea`) |
| Menú Secretaría | `resources/views/layouts/app.blade.php` |
| Menú Administración | `resources/views/layouts/partials/sidebar-nav-administracion.blade.php` |
| Permiso | `PermisosIaCatalog::PERMISOS_POR_TAREA` (99) |

## Qué no hacer / reglas de negocio

1. No mutar `profesores.permisos_ia` desde esta pantalla.
2. No listar usuarios de otro nivel que el de `schoolCtx()`.
3. No incluir usuarios con rol «Sin Rol» (`IdTipoProf = 1`).
4. No mostrar tareas sin ningún usuario habilitado en el nivel.
5. No calcular ni inferir permisos del portal docente: este listado es solo `permisos_ia` de secretaría/administración.

## Checklist al modificar

- [ ] Permiso 99 en ruta, Livewire (`mount`) y ambos sidebars.
- [ ] Grupo **Permisos del sistema** visible con 0, 14 o 99 (`PermisosConfiguracion::tieneAlgunPermisoSistemaMenu()`).
- [ ] Filtro de usuarios por `schoolCtx()->idNivel`.
- [ ] Catálogo y `maxOrden` alineados si se agrega un orden nuevo (el editor de asignación rellena la cadena).
- [ ] Vista de consulta: sin `alert` nativo ni formularios de guardado.
