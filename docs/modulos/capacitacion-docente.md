# Módulo: Capacitación docente

## Propósito

Registrar los cursos de capacitación que realiza cada docente (fecha, nombre, entidad otorgante, duración, modalidad y PDF del certificado), consultar el listado con filtro por profesor y ver un resumen de cantidad de cursos por docente en el año calendario actual, más el total del nivel.

## Modalidades / variantes

| Superficie | Cómo se habilita | Qué hace |
|------------|------------------|----------|
| **Menú de Secretaría** / **Administración** | Permiso IA **93** | Alta, edición, baja, listado, descarga de certificado, resumen del año |

No hay ítem en el Menú de Docentes (portal aula) ni en el Menú de Alumnos.

## Actores y permisos

| Rol | Permiso | Alcance |
|-----|---------|---------|
| Secretaría / Administración | `PermisosIaCatalog::CAPACITACION_DOCENTE` (orden **93**) | Todo el CRUD del nivel de sesión (`profesores.nivel` / `id_nivel` del registro) |

Rutas: `docentes.capacitacion` + middleware `permiso:93`.  
Descarga PDF: `docentes.capacitacion.certificado` con `OpaqueRouteToken` (sin ID en la URL).

## Tablas y campos críticos

Tabla **nueva** (aditiva): `capacitacion_docente`.

| Campo | Notas |
|-------|--------|
| `id_profesor` | Legajo en `profesores` del nivel activo |
| `id_nivel` | Nivel de sesión al crear (`SchoolAlcancePedagogico::idNivelLegajosDocente()`) |
| `fecha` | Fecha del curso; el resumen filtra por **año calendario** (`YEAR(fecha)`) |
| `nombre` | Nombre del curso |
| `entidad_otorgante` | Institución que otorga |
| `duracion` | Texto libre (ej. «8 horas») |
| `modalidad` | `presencial` \| `virtual` \| `hibrida` |
| `certificado_archivo` | Ruta relativa en disco `privado` (`storage/app/private/ento/capacitacion-docente/{tenant}/{nivel}/…`) |

SQL: `database/sql/create_capacitacion_docente.sql` · permiso: `database/sql/permiso_ia_orden_93_capacitacion_docente.sql`.

## Flujo principal

1. Usuario con permiso 93 abre **Capacitación docente** (final del grupo DOCENTES / USUARIOS).
2. **Listado:** filtros por texto (curso/entidad) y por docente; alta/edición en modal; opcional PDF; eliminar con SweetAlert.
3. **Resumen {año}:** cantidad de cursos por docente en el año calendario actual + total del nivel.
4. Descarga del certificado vía token opaco (revalida permiso y `id_nivel`).

## Fuente de verdad

| Dato | Quién escribe | Quién solo lee |
|------|---------------|----------------|
| Registros de cursos | Livewire `CapacitacionDocenteIndex` | Listado / resumen |
| Archivo PDF | `CapacitacionDocenteService` en disco `privado` | Controlador de descarga |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Livewire | `app/Livewire/Docentes/Capacitacion/CapacitacionDocenteIndex.php` |
| Vista | `resources/views/livewire/docentes/capacitacion/index.blade.php` |
| Servicio | `app/Support/CapacitacionDocente/CapacitacionDocenteService.php` |
| Modelo | `app/Models/CapacitacionDocente.php` |
| Descarga PDF | `app/Http/Controllers/Docentes/CapacitacionDocenteCertificadoController.php` |
| Menú | `resources/views/layouts/partials/sidebar-grupo-docentes-usuarios.blade.php` |
| Permiso | `PermisosIaCatalog::CAPACITACION_DOCENTE` (93) |

## Qué no hacer / reglas de negocio

1. No exponer el ID numérico del curso en la URL de descarga del PDF.
2. No listar docentes ni cursos de otro `id_nivel` que el del contexto.
3. No calcular el resumen por ciclo lectivo (`terlec`): el criterio es **año calendario** de `fecha`.
4. No guardar certificados fuera del disco `privado` (`ento/capacitacion-docente/{tenant}/…`); acceso solo por controlador autenticado + token opaco.

## Checklist al modificar

- [ ] Permiso 93 en rutas, Livewire y sidebar.
- [ ] Filtro por nivel de sesión en consultas y descarga.
- [ ] Fechas en UI en `d/m/Y`.
- [ ] Confirmaciones con `seSwalConfirmar` / eventos `se-swal-*`.
- [ ] Paginación `se-compact` si el listado crece.
- [ ] Si cambia el esquema: actualizar SQL en `database/sql/` y bloque de despliegue.
