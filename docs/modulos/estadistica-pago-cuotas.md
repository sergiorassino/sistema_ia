# Módulo: Estadística de pago de cuotas

## Propósito

Consultar el **porcentaje de pago** de una o más plantillas de cuota del ciclo activo: cuántas cuotas generadas están **pagadas** y cuántas **no pagadas**, con gráficos por cuota.

No modifica datos. No hay PDF ni portal de Alumnos/Docentes.

## Modalidades / variantes

Ninguna por tenant. El alcance de nivel sigue `SchoolAlcancePedagogico` (el ítem vive en el Menú de Administración).

## Actores y permisos

| Requisito | Detalle |
|-----------|---------|
| Menú | **Menú de Administración** (`layouts/administracion`), grupo sidebar **Resúmenes** |
| Permiso | `PermisosIaCatalog::ADMIN_ESTADISTICA_PAGO_CUOTAS` — **orden 107** |
| Gate | `PermisosCuotas::puedeEstadisticaPagoCuotas()` |
| Ruta | `cuotas.estadistica-pago` — middleware `permiso:107` + `menu.portal:administracion` |

Sin permiso o fuera de Administración: **403**.

## Tablas y campos críticos

Todas las consultas filtran por **`idTerlec`** del contexto de sesión.

| Tabla | Uso |
|-------|-----|
| `cuotas` | Plantillas del ciclo para el selector (orden + mes + tipo). |
| `cuotasgeneradas` | Conteo e importes: `faltapa`, `importe`, `pagado`, `idCuotas`, `idCursos`. |
| `cursos` | Filtro opcional de nivel pedagógico (`idNivel`). |
| `terlec` | Etiqueta del año en el hero (contexto). |

**Universo:** cuotas generadas del ciclo con **`importe > 0`**. Se excluyen reservas canceladas e importes en cero (inflarían el % pagado). No se filtra por `matricula.idCondiciones`.

## Fuente de verdad

Implementación: `App\Support\Cuotas\EstadisticaPagoCuotasDatos`.

| Concepto | Regla |
|----------|--------|
| **Pagada** | `ROUND(faltapa, 2) <= 0` (saldo cubierto o a favor). |
| **No pagada** | `faltapa > 0` (incluye pagos parciales). |
| **% pago** | `pagadas / total × 100` (un decimal). Es el dato de los gráficos. |
| **% cobrado** | `SUM(pagado) / SUM(importe) × 100` (tabla; puede superar 100 si hay pagos de más). |

Los gráficos de barras apiladas y las tortas usan **% pago / % no pagadas** (suman 100), no el % cobrado en pesos.

## Flujo principal

1. Usuario con permiso 107 entra al **Menú de Administración** → **Resúmenes** → **Estadística de pago de cuotas**.
2. Elige nivel (Todos / Inicial / Primario / Secundario) y marca una o más plantillas. «Todas» / «Ninguna» y buscador local no consultan la BD de generadas.
3. **Graficar** agrega `cuotasgeneradas` y muestra tabla + gráfico comparativo + una torta por cuota.
4. Cambiar selección o nivel oculta los resultados hasta volver a graficar (no se consulta al entrar ni al tildar).
5. «Limpiar» vacía selección, nivel y gráficos.

## Gráficos

- Mismos Chart.js 4 + `estadistica-charts.js` que Estadística de rendimiento escolar.
- Barras horizontales apiladas: % pagadas (`#40848D`) y % no pagadas (`#A65D57`).
- Torta/dona por cada plantilla seleccionada.
- Datos en `<textarea hidden data-se-estad-chart-json>` (partial `livewire/estadistica/partials/chart-canvas`).

**Requisito de despliegue de JS:** el bundle Vite ya incluye los gráficos; no hace falta `npm run build` salvo que se toque `app.js` / `estadistica-charts.js` en el mismo cambio.

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Livewire | `app/Livewire/Cuotas/EstadisticaPagoCuotasIndex.php` |
| Consulta / % / charts | `app/Support/Cuotas/EstadisticaPagoCuotasDatos.php` |
| Vista | `resources/views/livewire/cuotas/estadistica-pago-cuotas.blade.php` |
| Sidebar | `resources/views/layouts/partials/sidebar-nav-administracion.blade.php` |
| Ruta | `routes/web.php` (`cuotas.estadistica-pago`) |
| Permiso | `PermisosIaCatalog::ADMIN_ESTADISTICA_PAGO_CUOTAS` (107) |
| SQL / migración | `database/sql/permiso_ia_orden_107_estadistica_pago_cuotas.sql`, migración homónima |

## Qué no hacer / reglas de negocio

1. **No calcular al entrar** ni al tildar cuotas: esperar **Graficar**.
2. **No contar `importe <= 0`** como pagadas (reservas canceladas).
3. **No tratar un pago parcial como pagada:** si queda `faltapa`, es no pagada.
4. **No mezclar ciclos:** solo plantillas y generadas del `schoolCtx()->idTerlec`.
5. **No poner IDs en la URL.**
6. **No usar DomPDF** para este módulo (no hay PDF).

## Checklist al modificar

- [ ] ¿Sigue exigiendo Graficar antes de consultar `cuotasgeneradas`?
- [ ] ¿Pagada = `faltapa <= 0` y universo `importe > 0`?
- [ ] ¿Filtro de nivel con `SchoolAlcancePedagogico` / selector de niveles pedagógicos?
- [ ] ¿Permiso 107 en ruta, Livewire (`mount` / acciones) y sidebar?
- [ ] ¿Gráficos probados tras morph Livewire (cambio de selección + Graficar) con assets Vite?
- [ ] ¿Tabla ancha con scroll `justify-start` (no centrar bajo sidebar)?
