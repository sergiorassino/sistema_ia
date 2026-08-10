# Módulo: Reserva de Material Didáctico

Prefijo de tablas / código: **`rrd_*`** (Reserva de Recursos Didácticos).

## Propósito

Permitir reservar, entregar y devolver material didáctico compartido del colegio
(notebooks, proyectores, espacios, etc.): un **pedido** agrupa uno o más **recursos**
en un mismo horario. Incluye ABM de catálogo (grupos, recursos, ventanas horarias)
y operación diaria (listado, entrega, devolución, cancelación).

No hay PDF ni portal de Alumnos para este módulo.

## Modalidades / variantes

| Superficie | Cómo se habilita | Qué hace |
|------------|------------------|----------|
| **Menú de Secretaría** | Permisos IA 68 / 69 / 70 | Listado, reserva, ABM recursos (solo admin), entrega/devolución |
| **Menú de Docentes** | Flags tenant por nivel: `portal_docente.menu.{inicial\|primario\|secundario}.recursos_didacticos_nueva_reserva` y `…_listado` (default `false`) | Misma lógica de reserva/listado; **sin** ABM de catálogo ni entrega/devolución |

Variantes de tenant conocidas (activar en `config/tenants/{slug}.php`):

- `epq`, `sanfranciscoasis`: inicial + primario + secundario.
- `sfq`: inicial + primario (secundario queda en default `false` salvo que se agregue).

No hay variantes por nivel pedagógico en la lógica de negocio del catálogo: ver regla de **catálogo compartido** abajo.

## Actores y permisos

Helper: `rrdRol()` → `admin` \| `profesor` \| `lectura` \| `null` (gana el bit más alto).

| Rol / contexto | Permiso / flag | Alcance |
|----------------|----------------|---------|
| **Admin** (Secretaría) | 68 `RESERVA_MATERIAL_ADMIN` | Todo: ABM grupos/recursos/disponibilidad, ver todos los pedidos del ciclo, préstamo espontáneo (entrega directa), registrar entrega/devolución |
| **Profesor** (Secretaría) | 69 `RESERVA_MATERIAL_PROFESOR` | Crear/editar/cancelar **pedidos propios** mientras estén pendientes; en listado solo ve los suyos |
| **Lectura** (Secretaría) | 70 `RESERVA_MATERIAL_LECTURA` | Solo consulta de **pedidos propios** |
| **Portal Docentes** | Flags tenant (no `permisos_ia`) | Listado: ve reservas del **ciclo** (todos los pedidos). Con `nueva_reserva`: actúa como profesor (propios al editar). Sin entrega/devolución ni ABM |

Rutas Secretaría: `material-didactico.*` + middleware `permiso:…`.  
Rutas Docentes: `portalDocente.materialDidactico.*` + `abort`/404 según flags.

## Tablas y campos críticos

Tablas **nuevas** (migraciones `2026_06_11_*` / aditivas posteriores). No son legacy.

| Tabla | Rol |
|-------|-----|
| `rrd_grupos` | Categorías (`nombre`, `orden`, `activo`, `id_nivel` de alta) |
| `rrd_recursos` | Ítems reservables: `id_grupo`, `antelacion_min_horas`, `siempre_disponible`, `activo`, `id_nivel` de alta |
| `rrd_recurso_disponibilidad` | Ventanas por `dia_semana` (1–7 ISO) + `hora_inicio` / `hora_fin` |
| `rrd_pedidos` | Cabecera: fecha/horas, `sala_curso_grado`, `auxiliar`, `observaciones`, `id_profesor`, `id_terlec`, `id_nivel` del contexto al crear |
| `rrd_reservas` | Ítems del pedido: `id_recurso`, estado, datos de entrega/devolución, `id_terlec` |

**Estados** (`rrd_reservas.estado`): `pendiente` → `entregado` → `devuelto`; o `cancelado` solo desde pendiente.

**Scopes de contexto:**

- Catálogo (`RrdGrupo` / `RrdRecurso` `enContexto`): **compartido entre todos los niveles** del colegio. `id_nivel` se graba al crear (auditoría) y **no filtra** visibilidad ni reserva.
- Pedidos/reservas (`enContexto`): filtran por **`id_terlec`** del `schoolCtx()` (mismo ciclo; visibles entre niveles).

## Flujo principal

1. **ABM (admin):** grupos → recursos → si no es `siempre_disponible`, ventanas en `rrd_recurso_disponibilidad`.
2. **Nueva reserva:** fecha + horario → elegir grupo → agregar recursos que pasen antelación, ventana y sin solapamiento → guardar.
   - 1 pedido + N reservas (atómico).
   - Admin puede marcar **préstamo espontáneo** (`entregado_directo`): estado inicial `entregado` y **omite** antelación.
3. **Listado:** reservas agrupadas por pedido; filtros fecha (día / todas), grupo, recurso, estado, curso, texto.
4. **Entrega / devolución (solo admin Secretaría):** modal por pedido; nombres quedan fijos (no se editan ni se revierten desde UI).
5. **Edición / cancelación:** solo con ítems pendientes; profesor solo sobre pedidos propios.
   - Al **editar** (`editarPedido`): valida antelación/ventanas **antes** de modificar la BD; el reemplazo de ítems va en **una sola** transacción. Si la validación falla, el pedido no se altera.

## Fuente de verdad

| Dato | Quién escribe | Quién solo lee |
|------|---------------|----------------|
| Catálogo (grupos/recursos/ventanas) | `RecursosAdmin` (admin) | Formulario y listado |
| Pedidos / reservas | `RrdReservaService` vía `ReservaForm` / dashboard | Listados |
| Disponibilidad efectiva | Calculada: antelación + ventanas + solapes activos (`pendiente`/`entregado`) del mismo `id_terlec` | — |
| Cursos / sala en formulario | Lee `cursos` del nivel elegido en el form (no del filtro de catálogo) | — |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Listado | `app/Livewire/MaterialDidactico/ReservasDashboard.php` + vista homónima |
| Alta/edición pedido | `app/Livewire/MaterialDidactico/ReservaForm.php` |
| ABM catálogo | `app/Livewire/MaterialDidactico/RecursosAdmin.php` |
| Reglas de negocio | `app/Support/MaterialDidactico/RrdReservaService.php` |
| Modelos | `app/Models/Rrd{Grupo,Recurso,RecursoDisponibilidad,Pedido,Reserva}.php` |
| Rol / menú Docentes | `rrdRol()`, `tenantPortalDocenteRecursosDidacticos*()` en `app/Support/helpers.php` |
| Permisos | `PermisosIaCatalog` 68–70 |
| Rutas | `routes/web.php` (`material-didactico.*`, `portalDocente.materialDidactico.*`) |
| Menús | `layouts/app.blade.php`, `layouts/docente.blade.php` |
| Config tenant | `config/tenant.php` → `portal_docente.menu.*.recursos_didacticos_*` |

## Qué no hacer / reglas de negocio

1. **No filtrar el catálogo por `id_nivel` del contexto.** Los recursos son del colegio entero; filtrar otra vez por nivel vuelve a ocultar el material en primario/inicial si se cargó en secundario.
2. **No calcular solapes solo del nivel activo:** las reservas activas del mismo ciclo bloquean el recurso para todos los niveles.
3. **No omitir antelación** salvo préstamo espontáneo admin (`entregado_directo`).
4. **No borrar reservas del pedido antes de validar** al editar: si falla antelación/ventana/solape, el pedido debe quedar intacto (una sola transacción).
5. **Recurso sin `siempre_disponible` y sin ventanas:** no es reservable.
6. **No revertir ni editar** nombres de entrega/devolución desde el listado (métodos deprecated en el service lanzan excepción).
7. **No cancelar** pedido/ítem ya entregado; primero devolución.
8. Portal Docentes: **no** exigir bits 68–70; respetar flags tenant. **No** exponer ABM ni entrega ahí.
9. Diálogos: SweetAlert SE (`se-swal-*`), no `wire:confirm` / `alert`.
10. Tablas `rrd_*` son del módulo: migraciones aditivas sí; no mezclar con tablas legacy de cuotas “reserva”.

## Checklist al modificar

- [ ] ¿Secretaría y Portal Docentes siguen coherentes (rutas, layout, flags vs `rrdRol`)?
- [ ] ¿Catálogo sigue visible en todos los niveles pedagógicos?
- [ ] ¿Solapes siguen mirando `id_terlec` (todos los niveles del ciclo)?
- [ ] ¿Antelación / ventanas / `siempre_disponible` intactos?
- [ ] ¿Admin-only: ABM, entrega, devolución, préstamo espontáneo?
- [ ] ¿Profesor Secretaría solo toca pedidos propios pendientes?
- [ ] ¿Tenant nuevo necesita flags `recursos_didacticos_*` por nivel?
- [ ] ¿Sin PDF nuevo innecesario? Si hubiera PDF: TCPDF + Arial (regla de PDFs nuevos).
