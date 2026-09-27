# Módulo: Discriminación de cuotas

## Propósito

Definir, para cada plantilla de cuota y cada curso, los ítems en los que se discrimina el importe (las líneas de una factura) y el monto de cada ítem.

## Modalidades / variantes

Un editor por plantilla del ciclo activo. Los ítems son propios del curso: un curso puede tener un servicio especial y otro no. La copia dentro del editor lleva ítems e importes de un curso a otros. En el listado, «Copiar a» lleva la discriminación completa de una cuota a otras cuotas del mismo año.

## Actores y permisos

Menú de Administración, grupo Gestión masiva. Mismo gate que Importes por curso: `PermisosCuotas::puedeImportesPorCurso()`. Rutas `cuotas.detalle.index` y `cuotas.detalle.editar` (plantilla en sesión, sin ID en la URL).

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `cuotasdetalle` | `idCuotas`, `idCursos`, `orden`, `nombre`, `importe` | Ítem de esa cuota en ese curso, con su importe. Hasta 30 por curso. Nombre hasta 180 caracteres. |

No modifica `cuotas` ni `cuotasimportes`. El importe de la cuota sigue en `cuotasimportes.importe`; el editor lo muestra al lado del subtotal y avisa si no coinciden, sin bloquear el guardado.

## Flujo principal

1. Elegir la cuota en el listado (o entrar desde Importes por curso → Discriminación).
2. Elegir un curso. Agregar, renombrar, reordenar o eliminar ítems de ese curso, y cargar el importe de cada uno.
3. Al cambiar de curso se guardan los montos del curso que se deja, si son válidos.
4. «Copiar a otros cursos» reemplaza ítems e importes de los cursos marcados por los del curso en pantalla. Después se puede agregar en un curso el ítem que los demás no tienen.
5. En el listado, «Copiar a» (junto a Discriminar) abre las demás cuotas del año. Se eligen una o más de destino. Antes de copiar se advierte que, si el destino ya tiene registros, se sobrescriben. La copia reemplaza ítems e importes de todos los cursos y no toca la cuota de origen ni `cuotasimportes`. Si la cuota de origen no tiene ítems, el botón queda deshabilitado.

## Fuente de verdad

`cuotasdetalle` de la plantilla (`idCuotas`) y del curso (`idCursos`). Un curso nuevo del ciclo no recibe ítems hasta que se carguen o se copien. Al borrar la plantilla se borran también estas filas.

## Archivos clave

- `app/Livewire/Cuotas/CuotasDetalleIndex.php`
- `app/Livewire/Cuotas/CuotasDetalleForm.php`
- `resources/views/livewire/cuotas/detalle-index.blade.php`
- `resources/views/livewire/cuotas/detalle-form.blade.php`
- `app/Support/Cuotas/CuotasDetalleCatalog.php`
- `app/Support/Cuotas/FacturaAfipLineasDetalle.php`
- `database/sql/cuotasdetalle_tablas.sql`

## Qué no hacer / reglas de negocio

- No compartir la lista de ítems entre cursos: cada fila de `cuotasdetalle` pertenece a un `idCursos`.
- No poner el ID de la cuota en la URL: va en `ContextoCuotasDetalleSesion`.
- No recalcular ni pisar `cuotasimportes.importe` desde esta pantalla.
- La factura AFIP imprime primero el nombre de la cuota y, debajo, los ítems de `cuotasdetalle` de esa cuota y de ese curso. Los intereses del cobro van en una línea aparte («Intereses»). El total autorizado no se modifica: si la suma de los ítems no coincide con el neto facturado, los montos se reparten en la misma proporción. Una nota de crédito no usa este detalle.
- Si las tablas no existen, la pantalla avisa y no simula un guardado.

## Checklist al modificar

- [ ] Alta de un ítem crea la línea y el importe 0 solo en el curso activo.
- [ ] Copiar reemplaza ítems e importes del destino y no modifica el curso de origen.
- [ ] «Copiar a» en el listado reemplaza la discriminación de las cuotas marcadas y no modifica la cuota de origen.
- [ ] Sin ítems en el origen, no se copia. La advertencia de sobrescritura aparece antes de confirmar.
- [ ] Un importe inválido no cambia de curso ni abre la copia.
- [ ] Borrar la plantilla de cuota borra sus filas de `cuotasdetalle`.
- [ ] La factura muestra el nombre de la cuota, debajo los ítems de ese curso, y los intereses en una línea aparte.
