# Módulo: Importes por curso

## Propósito

Cargar el importe de cada cuota por sala / grado / curso y las fórmulas de bonificación o interés de los cuatro tramos de vencimiento (`cuotasimportes`).

## Modalidades / variantes

Una grilla por plantilla de cuota del ciclo activo. No hay paginación: se listan todos los cursos del ciclo. La búsqueda filtra en el navegador, sin recargar Livewire.

## Actores y permisos

Menú de Administración / Secretaría. Gate: `PermisosCuotas::puedeImportesPorCurso()`. Rutas `cuotas.importes.index` y `cuotas.importes.editar` (plantilla en sesión, sin ID en la URL).

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `cuotasimportes` | `importe` | Importe base por curso. |
| `cuotasimportes` | `signoNv`, `valorNv`, `porcanNv` (N=1..4) | Bonif (−) o interés (+) por tramo. `porcan`: `%` `$` `m` `p`. |

## Flujo principal

1. Elegir la cuota en el listado.
2. Editar celdas: flechas / Enter navegan; al salir de un importe/valor o al cambiar un select se guarda esa celda.
3. El guardado es **renderless** (`skipRender`): no redibuja la grilla ni reenvía todas las filas en el snapshot.

## Fuente de verdad

`cuotasimportes` de la plantilla (`idCuotas`) del ciclo de sesión. Las filas se crean al alta de la plantilla o en la generación masiva.

## Archivos clave

- `app/Livewire/Cuotas/CuotasImportesForm.php`
- `resources/views/livewire/cuotas/importes-form.blade.php`
- `app/Support/Cuotas/CuotasImportesCatalog.php`
- Navegación y commit de celdas: `resources/js/app.js` (`seCii*`)

## Qué no hacer / reglas de negocio

- No volver a poner `$draft` público ni `wire:model.live` en cada select: el remorph de toda la planilla vuelve a dejar la pantalla lenta y saca el foco.
- No calcular ni mostrar promedios acá (no aplica).
- URL de edición sin ID: la plantilla va en `ContextoCuotasImportesSesion`.

## Checklist al modificar

- [ ] Guardar una celda no reconstruye la tabla (foco y scroll se mantienen).
- [ ] Select de signo y `%`/`$` persisten sin `wire:model.live`.
- [ ] Búsqueda por curso oculta filas en el cliente; las flechas no caen en filas ocultas.
- [ ] Valor inválido revierte la celda y muestra el error inline, sin SweetAlert por cada tecla.
