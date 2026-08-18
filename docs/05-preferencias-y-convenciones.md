# Preferencias y Convenciones de Desarrollo

> Este archivo concentra las preferencias del usuario/proyecto y las convenciones
> de código que deben respetarse en todos los módulos, presentes y futuros.

---

## 1. Seguridad (obligatorio)

Aplicar **medidas de seguridad de un sistema profesional** (PHP + MySQL + Laravel)
a todos los módulos. Ver [06-reglas-de-seguridad.md](06-reglas-de-seguridad.md) para el detalle completo.

### Resumen de medidas mínimas por módulo

- ✅ Autenticación para todo lo interno (`auth` middleware)
- ✅ Autorización / control de alcance por contexto (`schoolCtx()`)
- ✅ Validación server-side y normalización (`trim`, formatos)
- ✅ Protección XSS (escape en Blade, evitar `{!! !!}`)
- ✅ Evitar SQL injection (sin `raw` con input de usuario)
- ✅ Rate limit en operaciones ABM sensibles

---

## 2. Base de datos

- **NO modificar** tablas existentes de la base legacy.
- Crear migraciones **aditivas** (agregar columnas, tablas nuevas).
- Crear migraciones para **instalación limpia** del sistema nuevo.
- Modelos Eloquent con `$table` explícito, sin timestamps automáticos.
- `$fillable` explícito en todos los modelos — nunca `$guarded = []`.

---

## 3. Estilo de implementación

- Preferir cambios **seguros y conservadores** (hardening) sin romper compatibilidad.
- Donde falten roles/permisos, aplicar al menos **control de alcance por contexto** 
  (ej. `schoolCtx()`).
- Toda acción ABM (crear/editar/eliminar) debe revalidar el alcance del registro 
  consultando con el filtro de contexto.

---

## 4. Convenciones de código

### PHP / Laravel

- Nombres de clases en PascalCase.
- Componentes Livewire organizados por dominio: `Livewire/Auth/`, `Livewire/Abm/`.
- Vistas Blade en mirror: `livewire/auth/`, `livewire/abm/`.
- Helper global `schoolCtx()` para acceder al contexto de sesión.
- **Selects de año lectivo (`terlec`):** orden **decreciente** (año más reciente primero en el `<select>`). Usar `Terlec::paraSelector()` o `Terlec::ordenado()`; en formularios Livewire con muchos re-renders, el componente `livewire:components.terlec-selector` (ver `app/Models/Terlec.php`).
- Mensajes de validación en español.
- Comentarios en español cuando aclaren lógica de negocio.

### Frontend / Blade

- Usar `{{ }}` siempre (escape XSS).
- Tailwind CSS 4 para estilos.
- Colores del design system (ver [04-identidad-visual.md](04-identidad-visual.md)).
- Layout responsivo, mobile-first para autogestión.

### Grillas / listados anchos (convención)

- Para listados tipo planilla con muchas columnas (patrón `.gf-*`), **no centrar** el contenedor con `.gf-wrap` si puede haber overflow horizontal: al cambiar el ancho disponible (ej. sidebar), se pueden ocultar columnas.
- Usar siempre un wrapper con scroll horizontal y alineación a la izquierda:

```blade
<div class="w-full overflow-x-auto">
    <div class="flex justify-start">
        <div class="gf min-w-[1180px]">
            <!-- gf-head / gf-row -->
        </div>
    </div>
</div>
```

### Grillas con pocos campos (convención)

Cuando la pantalla muestra una **tabla o grilla angosta** (pocas columnas: curso + acción, alumno + botón, búsqueda + listado corto, etc.):

1. **Separación entre columnas:** **30 mm** (`padding-right: 30mm` en cada celda salvo la última). Clase de tabla: **`se-grid-pocos-campos`** (`resources/css/app.css`).
2. **Centrado horizontal:** la grilla no debe ocupar todo el ancho del card; va **centrada** con `width: max-content` y contenedor **`se-grid-angosta-wrap`** (scroll horizontal si hace falta en mobile).
3. **Barra superior** (botones de marcar/desmarcar, generar PDF, cuadro de búsqueda, filtros): también **centrada**, con **30 mm** entre controles. Clase: **`se-toolbar-pocos-campos`** (o `se-matriz-list-toolbar--angosta` si solo hace falta centrar sin el gap de 30 mm).

```blade
<div class="se-toolbar-pocos-campos border-b border-accent-100 px-5 py-3">
    <!-- botones / búsqueda -->
</div>
<div class="w-full overflow-x-auto px-4 py-2 se-grid-angosta-wrap">
    <table class="se-grid-pocos-campos w-auto text-sm">
        ...
    </table>
</div>
```

- **Grillas anchas** (muchas columnas): no aplicar esta regla; seguir con alineación a la izquierda (sección anterior).
- Referencia en UI: `livewire/seguimiento/inasistencias/informe-lote-index.blade.php`.

---

## 5. Varios colegios (tenants)

- Un despliegue (o entorno local) por colegio: `TENANT_SLUG` + BD propia. Ver [07-versionado-de-modulos-por-tenant.md](07-versionado-de-modulos-por-tenant.md) (personalización real: config + BD + permisos; **sin** paquetes Composer por módulo).
- Preferir parametrización en tablas (`solapas_legajo`, `campos_legajo`, permisos, `ento`) antes de ramas de código por colegio.
- Overrides en `config/tenants/{slug}.php` solo para lo que no corresponda en BD.

---

## 6. Menú lateral, dashboard y módulos por nivel educativo

**Nombres oficiales de los tres sidebars:** [08-menus-de-navegacion.md](08-menus-de-navegacion.md) — *Menú de Secretaría*, *Menú de Alumnos*, *Menú de Docentes*.

Cuando un módulo aplica solo a **secundario**, **primario** o **inicial** (o existirán variantes por nivel, como boletines o calificaciones), debe quedar explícito en código, menú y documentación. No usar nombres genéricos ambiguos (`Boletines` a secas) si el alcance es de un solo nivel.

### Sidebar del Menú de Secretaría (`resources/views/layouts/app.blade.php`)

- Cada enlace del menú lleva atributo **`title`** (tooltip al pasar el mouse), con el mismo criterio que el resto del sistema:
  - Nombre del módulo con **nivel entre paréntesis** cuando corresponda: `(secundario)`, `(primario)`, `(inicial)`.
  - Descripción breve opcional separada por ` · `.
  - **Versión del módulo al final:** `v1.0` (referencia visual; no implica conmutación por config).
- Ejemplo actual: `title="Boletines (secundario) · Informe de progreso escolar v1.0"`.
- El texto visible del ítem (`<span class="truncate">`) debe incluir el nivel si pronto coexistirán ítems homónimos (p. ej. `Boletines (secundario)` y, más adelante, `Boletines (primario)`).

### Rutas, PHP y nombres

- Namespaces, carpetas Livewire, prefijos de ruta y nombres de ruta (`boletinesSecundario.*`, `BoletinesSecundario\`, etc.) deben incluir el nivel.
- Al agregar el mismo tipo de módulo para otro nivel: **ítem de menú y ruta propios**; no reutilizar un único enlace genérico.

### Dashboard

- `title` y `hint` en `dashboard.blade.php` deben alinear con el sidebar (nivel en el título; versión o alcance en el `hint` cuando aplique).

### Referencia

- Calificaciones secundario: tooltips `… (secundario) v1.0` en el grupo **CALIFICACIONES (Secundario)** (solo con `schoolCtx()->idNivel` = 3).
- Boletines secundario: `boletinesSecundario.index`, tooltip y etiqueta `Boletines (secundario)`.

---

## 7. Calificaciones — promedio anual (secundario)

**Regla obligatoria:** no calcular promedios de calificaciones en ninguna parte del sistema salvo que se pida explícitamente en una tarea o decisión de producto documentada.

### Único lugar autorizado (por ahora)

- **Carga manual de calificaciones (secundario):** `Livewire/CalificacionesSecundario/CargaCalificacionesSecundario.php`
- Al salir de un campo de módulo (`ic01`…`ic28`, blur/change), tras persistir la nota, se ejecuta `syncPromedioAnual()` y se guarda el resultado en `calificaciones.calif` (columna Pr. Final, solo lectura en la UI).
- La lógica numérica vive en `App\Support\PromedioAnualCalificacionesSecundario::calcular()` y **solo** debe llamarse desde ese `syncPromedioAnual()`.

### Qué no debe calcular promedios

- Planilla PDF de calificaciones: mostrar `calif` de BD (vacío si no hay valor).
- Boletines, consulta de calificaciones (alumno o personal), exportaciones e impresiones: leer `calif` persistido.
- Sincronización GE/CIDI: importar `calif` del archivo; no recalcular desde `ic**`.
- Cualquier vista, job o script nuevo: no inferir Pr. Final desde Eval/JIS salvo nueva autorización explícita.

### Presentación sin promedio

- En la planilla PDF se puede usar `PromedioAnualCalificacionesSecundario::bloqueDesaprobado()` solo para **resaltar** bloques desaprobados; no sustituye ni recalcula `calif`.

### Extender el cálculo en el futuro

Si se agrega otro flujo que deba calcular (p. ej. batch nocturno o otro nivel), documentarlo aquí y centralizar la llamada a `calcular()` — no duplicar fórmulas en Blade ni en importadores.

---

## 8. PDFs nuevos con TCPDF (fuente Arial)

Todo **PDF nuevo** y toda **migración explícita** desde DomPDF debe usar **TCPDF** (`tecnickcom/tcpdf`), clase en `app/Support/`, controlador `*PdfController`.

- **Fuente:** **Arial** mediante `App\Support\Pdf\TcpdfFuenteArial::aplicar($pdf, $style, $pt)` (no `dejavusans` ni `SetFont` directo).
- **Archivos TTF:** `storage/fonts/arial.ttf` y opcional `arialbd.ttf` (ver `storage/fonts/README.md`). En Windows también se detecta `C:\Windows\Fonts\`.
- **Maquetación:** coordenadas en mm (`Cell`, `MultiCell`, `Rect`); fechas `d/m/Y`.
- **Texto justificado:** no usar `MultiCell(..., 'J')` ni `writeHTMLCell` con alineación `J` para párrafos corridos. TCPDF estira también la **última línea** del bloque y queda con espaciado exagerado. Usar `App\Support\Pdf\TcpdfMultiCellJustificado::escribir($pdf, $ancho, $alturaLinea, $texto)` — justifica las líneas completas y deja la última alineada a la izquierda. Referencia: `InformeProgresoInicialTcpdf`, `SalidaViajeTcpdf`.
- **Referencia:** `App\Support\InformeInasistenciasTcpdf` + `InformeInasistenciasPdfController`.

Regla Cursor: `.cursor/rules/pdf-tcpdf-nuevos.mdc`.

---

## 9. PDFs con DomPDF (anchos de columna, legacy)

Los PDFs **legacy** usan **Barryvdh DomPDF** (`Pdf::loadView(...)`) y vistas Blade en `resources/views/pdf/` (y `resources/views/listados/pdf/`).

### Regla de oro

**No confiar en `colgroup`, anchos en mm/pt sueltos ni en clases CSS sin ancho en cada celda.** DomPDF reparte mal el ancho si solo hay `table-layout: fixed` o si el contenido largo (nombres, textos) empuja una columna.

Aplicar el mismo criterio que ya funciona en:

| Referencia | Archivo |
|------------|---------|
| Planilla de calificaciones | `App\Support\CalificacionesSecundario\PlanillaCalificacionesTcpdf` (vistas Blade en `pdf/partials/planilla-calificaciones-hoja.blade.php` solo referencia legacy) |
| Libro de matrícula | `resources/views/listados/pdf/libro-matricula.blade.php` |
| Informe de inasistencias | `App\Support\InformeInasistenciasTcpdf` (migrado a TCPDF + Arial) |
| Acta volante de coloquio | `resources/views/pdf/acta-volante-coloquios.blade.php` |

### Patrón obligatorio para tablas con columnas de distinto ancho

1. **Tabla al 100%** del área imprimible (no mezclar `width: 170mm` fijo con `@page` distinto sin motivo):

   ```css
   table.mi-tabla {
       width: 100%;
       border-collapse: collapse; /* o separate si hace falta (planilla) */
       table-layout: fixed;
   }
   ```

2. **Porcentajes en cada `th` y cada `td`**, con `min-width: 0` y `overflow: hidden` (inline, generados en PHP en el Blade):

   ```php
   @php
       $pct = static fn (float $parte, float $total): string =>
           number_format(($parte / $total) * 100, 4, '.', '').'%';
       $sty = static fn (float $parte, float $total): string =>
           'width:'.$pct($parte, $total).';min-width:0;max-width:'.$pct($parte, $total).';overflow:hidden;';
       $wNombre = $sty(78, 170);
   @endphp
   ```

   ```blade
   <th style="{{ $wNombre }}">Apellido y Nombres</th>
   <td style="{{ $wNombre }}">{{ $nombre }}</td>
   ```

   En libro de matrícula el mismo porcentaje va en **regla CSS duplicada** (`th.col-x` y `td.col-x`); en planillas nuevas preferir **inline en cada celda** como arriba.

3. **Texto largo en una columna estrecha:** `white-space: nowrap; overflow: hidden` en esa celda (ver columna estudiante en la planilla). No depender de `word-break` solo.

4. **Subcolumnas** (ej. Eval. con N / R1 / R2): una celda exterior con el `%` ya fijado y una **`table.inner` al 100%** dentro (ver planilla). No usar `colspan` en el cuerpo para repartir subcolumnas.

### Qué evitar (suele romper DomPDF)

| Evitar | Motivo |
|--------|--------|
| Solo `<colgroup><col style="width:…mm">` | DomPDF lo ignora o lo aplica mal con `colspan`/`rowspan`. |
| Dos tablas (encabezado + cuerpo) sin los mismos anchos en cada celda | Cada tabla calcula columnas por su cuenta. |
| `width: 100%` en la tabla sin `%` en **cada** `th`/`td` | El motor reparte por contenido; la columna de nombres se ensancha. |
| `colspan` / `rowspan` en el encabezado **sin** `style` de ancho en todas las celdas afectadas | El cuerpo hereda mal los anchos. Si hay `colspan`, la celda agrupada debe llevar el **suma** de los % (ej. tres columnas de 11,76 % → 35,29 %). |
| Anidar el partial `pdf.partials.header` **dentro** de la misma tabla de datos | El header es otro bloque; no mezclar con la grilla (ver informe de inasistencias: header arriba, tabla abajo). |

### Encabezado institucional (`pdf.partials.header`)

- Usarlo **fuera** de la tabla de datos (contenedor aparte).
- Sus estilos van scoped en `.pdf-header`; no sustituye el título del acta volante si el diseño legacy pide solo el nombre del colegio centrado.
- Si tras agregar el header las columnas se desalinean, el problema casi nunca es el logo sino que la tabla no cumple el patrón de la sección anterior.

### Fechas en PDF

Ver regla global `.cursor/rules/formato-fechas-es.mdc`: `d/m/Y` en UI y PDFs.

### Nuevos PDFs

Antes de cerrar un PDF con columnas variables: comparar con `planilla-calificaciones-hoja.blade.php` o `acta-volante-coloquios.blade.php` y verificar en impresión real (Ctrl+F5 / sin caché del navegador).

---

## 9. Convenciones de documentación

- Mantener la carpeta `docs/` actualizada con cada cambio significativo.
- Cuando aparezcan nuevas preferencias/restricciones, agregarlas en este archivo.
- Los archivos de documentación se numeran secuencialmente para facilitar la lectura.
- Personalización multi-colegio: [07-versionado-de-modulos-por-tenant.md](07-versionado-de-modulos-por-tenant.md).
- **Reglas de funcionamiento por módulo** (propósito, permisos, tablas, flujos, trampas): carpeta [modulos/](modulos/README.md). Al tocar un módulo, leer (o crear) su doc antes de cambiar comportamiento.

---

## 10. Depuración SQL en pantalla (uso puntual)

Algunos módulos muestran en Blade el texto SQL o las consultas “equivalentes” que usa el servidor para ayudar a diagnosticar problemas.

### Costo real del servidor

Ese texto suele obtenerse llamando a helpers PHP que **no son gratis**: además del armado del string pueden ejecutarse **consultas reales** o helpers que recorren listas para armar `IN (...)`, filtros legados, etc.

Por eso la forma correcta de “apagar” la depuración no es ocultar el panel en la vista con `@if(false)` ni un flag que oculte el `<pre>` mientras **`render()` o las acciones Livewire siguen llamando al helper**. Eso sigue cargando PHP y la base cada request **en silencio**.

### Patrón recomendado

1. **Desactivado (normal):** comentar con `//` en PHP las llamadas a helpers de texto SQL (`textoDepuracionSql*`, `textoConsultasEjecutadas*`, etc.) y comentar con `{{-- ... --}}` el bloque Blade del panel / botón “Mostrar depuración”. Las propiedades Livewire solo usadas por ese panel pueden dejarse comentadas en el mismo bloque de nota.
2. **Activado (minutos / horas, para investigar):** descomentar en orden: propiedades del componente → asignaciones en `updated*` / acciones → bloque en `render()` → Blade.
3. **Cierre:** volver a comentarlo todo antes de dejar código en uso continuo.

### Referencia en código

Ejemplo aplicado en **Horarios**: `HorariosCargaIndex`, `HorariosImpresionIndex` + vistas `horarios-*-index.blade.php`, y métodos `HorariosProfesores::textoDepuracionSql*` (conservados; no se ejecutan si no hay llamada desde el componente).

**ABM asignación docente (ppc):** `ProfesoresPorMateriaIndex` + `profesores-por-materia/index.blade.php`; método privado `textoConsultasEjecutadasAlElegirMateria` conservado (no se ejecuta sin la asignación en `selectMateria`).

---

## 11. Modales Livewire (centrado en viewport)

Los modales de confirmación, edición o selección múltiple (destinatarios, listas largas, etc.) deben **verse centrados en el monitor** y **no moverse** cuando el usuario hace scroll en el formulario o listado de la página de fondo.

### Patrón obligatorio

1. **Un único elemento HTML raíz** en la vista Livewire; dentro de él, `@teleport('body')` … `@endteleport` junto al `se-page` (no dos raíces hermanas).
2. Contenedor **`fixed inset-0 z-[90]`** con `flex items-center justify-center` y, si el contenido puede crecer, `overflow-y-auto` en ese contenedor.
3. Overlay **`absolute inset-0`** con fondo semitransparente (`bg-neutral-900/55 backdrop-blur-sm`).
4. Panel del modal con **`relative z-10 my-auto`**, `max-h-[calc(100dvh-1.75rem)]`, `flex flex-col overflow-hidden`, `rounded-2xl`, `ring-1 ring-black/5`.
5. **Scroll solo dentro del panel** (`min-h-0 flex-1 overflow-y-auto` en listas o cuerpo de formulario); header y footer con `shrink-0`.

### Referencia en el repo

- **Comunicaciones — elegir destinatarios:** `resources/views/comunicaciones/livewire/comunicaciones/nuevo-comunicado.blade.php` (modales alumnos, cursos, docentes, grupos).
- **Historial de exámenes:** `resources/views/livewire/examenes/historial-examenes.blade.php` (bloque `@teleport` al final del archivo).

### Regla Cursor

Detalle para el agente: `.cursor/rules/modales-livewire.mdc`.

### Antipatrones

- Modal al final del Blade de una página larga, sin teleport.
- Panel sin `my-auto` (queda anclado al scroll del documento).
- Hacer scroll de toda la página para alcanzar el modal en lugar de centrarlo en el viewport.
- Poner `@teleport` dentro de un `@include` anidado en el root (Livewire no refresca el modal; los botones parecen no responder).

---

## 12. Diálogos y avisos — SweetAlert2 (obligatorio)

Para **confirmaciones**, **éxitos**, **advertencias** y **errores** orientados al usuario, usar **SweetAlert2** con los helpers globales de `resources/js/app.js`. **No** usar `window.confirm`, `window.alert`, ni `wire:confirm` / `wire:confirm.prompt` de Livewire (diálogo nativo del navegador).

### Helpers disponibles (`window.*`)

| Helper | Uso | Retorno |
|--------|-----|---------|
| `seSwalExito(mensaje, titulo?)` | Operación guardada / eliminada con éxito | void |
| `seSwalAviso(mensaje, titulo?)` | Advertencia informativa (sin bloquear flujo crítico) | void |
| `seSwalError(mensaje, titulo?)` | Error de negocio o rechazo de operación | void |
| `seSwalConfirmar(mensaje, titulo?, opciones?)` | Pregunta Sí / Cancelar antes de borrar o acción irreversible | `Promise<boolean>` |

Color de botón principal: `#40848D` (paleta SE). La librería ya está en el bundle Vite (`sweetalert2`).

### Livewire — eventos desde PHP

Tras guardar, eliminar o rechazar una acción en el servidor, **no** depender solo de `session()->flash()` + banners verdes/rojos. Preferir:

```php
$this->dispatch('se-swal-exito', mensaje: 'Registro actualizado.');
$this->dispatch('se-swal-error', mensaje: 'No se puede eliminar: tiene dependencias.');
```

En la vista del componente, bloque `@script` que escuche esos eventos:

```blade
@script
<script>
    $wire.on('se-swal-exito', (event) => {
        window.seSwalExito?.(event?.mensaje ?? event?.detail?.mensaje ?? 'Guardado.');
    });
    $wire.on('se-swal-error', (event) => {
        window.seSwalError?.(event?.mensaje ?? event?.detail?.mensaje ?? 'Error.');
    });
</script>
@endscript
```

Referencia: `resources/views/livewire/cuotas/plantilla-index.blade.php`, `resources/views/livewire/cuotas/historial-pagos-cuota.blade.php`, `resources/views/livewire/alumnos/actualizacion-datos-personales-form.blade.php`.

### Confirmación antes de `wire:click` destructivo

En el botón, usar Alpine + `seSwalConfirmar` y llamar a Livewire solo si el usuario confirma:

```blade
<button type="button"
        x-on:click="window.seSwalConfirmar('¿Eliminar este registro?', 'Confirmar eliminación', { confirmButtonText: 'Sí, eliminar' })
            .then((ok) => { if (ok) $wire.eliminar({{ $id }}); })"
        class="btn-danger btn-sm">
    Eliminar
</button>
```

Filas nuevas sin persistir pueden llamar `$wire.eliminar(...)` directo, sin confirmación.

### Cuándo no usar SweetAlert

- Validación de campos: mensajes bajo el input (`@error`) o toast liviano de calificaciones (`seCalif*` en `app.js`).
- Confirmaciones complejas con muchos campos: modal Livewire con `@teleport('body')` (sección 11), no SweetAlert.

### Regla Cursor

`.cursor/rules/sweetalert-dialogos-se.mdc`

---

## 13. Paginación en listados Livewire (obligatorio)

Todo **listado paginado** del sistema (ABM, certificados, matrícula web, cuotas, etc.) debe usar el **mismo control compacto** integrado con Livewire, para que el cambio de página no recargue la pantalla ni pierda filtros.

### Vista Blade (patrón obligatorio)

```blade
@if ($registros->hasPages())
    <div class="se-matriz-list-footer">
        {{ $registros->links('vendor.pagination.se-compact') }}
    </div>
@endif
```

- **`vendor.pagination.se-compact`**: resumen `1–50 de 234 · pág. 1/5`, flechas anterior/siguiente e índices de página (desktop). En mobile muestra la página actual entre flechas.
- **`se-matriz-list-footer`**: pie del listado (borde superior, fondo blanco semitransparente, padding compacto). Definido en `resources/css/app.css`.
- **`hasPages()`**: solo renderizar el bloque si hay más de una página (evita un footer vacío cuando el total cabe en una sola página).

El pie va **debajo de la tabla/grilla**, dentro del mismo card (`se-matriz-list-card`, `se-card`, etc.), no flotando fuera del contenedor del listado.

### Componente Livewire (PHP)

```php
use Livewire\WithPagination;

class MiListadoIndex extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    public function updatedFiltroCurso(): void
    {
        $this->resetPage(); // siempre al cambiar búsqueda o filtros
    }

    public function render()
    {
        $registros = MiConsulta::paginar(..., self::POR_PAGINA);

        return view('livewire....', compact('registros'));
    }
}
```

- Trait **`WithPagination`** en el componente.
- **`resetPage()`** en cada `updated*()` de filtros, búsqueda o selectores que alteren el universo del listado.
- Tamaño de página habitual: **50** (`POR_PAGINA = 50` en la clase o en la clase de consulta `Support`).

### Plantillas disponibles

| Vista | Cuándo usar |
|-------|-------------|
| **`vendor.pagination.se-compact`** | **Listados operativos** (tablas en cards, certificados, bloqueos, libro matriz, etc.). **Preferida para módulos nuevos.** |
| `vendor.pagination.se` | Variante más alta con texto “Mostrando X a Y de Z registros”. Solo en pantallas legacy que aún no migraron; no usar en módulos nuevos. |

Ambas están preparadas para Livewire (`wire:click.prevent` en `previousPage`, `gotoPage`, `nextPage`). El default global en `AppServiceProvider` es `se`, pero **en listados hay que invocar explícitamente `se-compact`**.

### Layout recomendado (listados densos)

Cuando el listado usa el patrón matriz/certificados:

```blade
<div class="se-matriz-list-card min-h-0">
    <div class="se-cierre-anual-grilla se-matriz-list-grilla se-matriz-list-grilla--unified">
        <div class="se-cierre-anual-body-wrap se-matriz-list-scroll se-grid-angosta-wrap" tabindex="0">
            <table class="se-matriz-list-tabla ...">...</table>
        </div>
    </div>

    @if ($registros->hasPages())
        <div class="se-matriz-list-footer">
            {{ $registros->links('vendor.pagination.se-compact') }}
        </div>
    @endif
</div>
```

En listados dentro de `se-card` simple, basta con el bloque `@if ($registros->hasPages())` + `se-matriz-list-footer` al cierre del card.

### Referencias en el repo

- Plantilla: `resources/views/vendor/pagination/se-compact.blade.php`
- Estilos: `resources/css/app.css` (`.se-matriz-list-footer`, `.se-pagination--compact`)
- Ejemplos: `resources/views/livewire/certificados/certificado-alumno-regular-index.blade.php`, `resources/views/livewire/matriz-analiticos/libro-matriz-index.blade.php`, `resources/views/livewire/matricula-web/bloqueos-matricula-index.blade.php`

### Regla Cursor

Detalle para el agente: `.cursor/rules/paginacion-listados-se.mdc`.

### Antipatrones

- `{{ $registros->links() }}` sin `se-compact` en un listado nuevo.
- Mostrar el footer de paginación siempre, aunque `lastPage() === 1`.
- Olvidar `resetPage()` al cambiar filtros (el usuario queda en la página 7 sin resultados).
- Paginación fuera del card o con wrappers ad hoc (`border-t px-5 py-3`) en lugar de `se-matriz-list-footer`.

---

## 14. Persistencia en BD — sin falsos éxitos (obligatorio)

En tenants legacy el esquema puede **no tener** todas las columnas que el código nuevo espera. Un formulario **no debe** mostrar éxito si algún dato ingresado por el usuario no se pudo guardar.

### Prohibido

- Omitir en silencio un campo del `payload` porque `Schema::hasColumn()` devolvió `false`, cuando el usuario **sí ingresó un valor** (o activó un flag).
- Mostrar `session()->flash('success')`, `se-swal-exito` o mensaje equivalente si el `UPDATE`/`INSERT` falló o si el valor no quedó persistido.
- Confiar solo en que Eloquent «no tiró excepción» sin verificar columnas ni releer el registro cuando el esquema es variable por tenant.

### Patrón obligatorio

Usar `App\Support\Database\PersistenciaColumnas`:

1. **Antes de guardar:** `prepararPayload($tabla, $payload)` — si `columnas_con_valor_sin_columna` no está vacío, **error al usuario** (no guardar).
2. **Durante el guardado:** `try/catch` de `QueryException` y mensaje con `mensajeDesdeQueryException()` (columna/tabla inexistente, etc.).
3. **Después de guardar:** `columnasNoPersistidas($tabla, $where, $valoresEsperados)` — si hay discrepancias, **error** (no éxito).

Valores vacíos (`null`, cadena vacía, flag en `false`) pueden omitirse del payload si la columna no existe; no es un error del usuario.

### Referencia en código

- Helper: `app/Support/Database/PersistenciaColumnas.php`
- Implementación: `app/Livewire/Parametrizacion/ParametrosSistemaForm.php` (método `save()`)

### Regla Cursor

`.cursor/rules/persistencia-bd-sin-falso-exito.mdc`

---

## 15. Correo en desarrollo local (sin SMTP real)

Con `APP_ENV=local` **no se envía correo por SMTP** (comunicados, recuperación de contraseña, cooperadora, emails masivos). Laravel usa el mailer `log`: el mensaje queda en `storage/logs`.

- El bloqueo es de código (`App\Support\Mail\MailDesarrollo`): `MailInstitucionalConfig` ya no puede forzar SMTP al enviar.
- En `.env` local conviene `MAIL_MAILER=log`. En producción: `APP_ENV=production` y `MAIL_MAILER=smtp`.
- Escape hatch puntual en la PC: `MAIL_FORCE_REAL=true` (no usar en servidores).
