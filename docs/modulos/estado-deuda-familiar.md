# Módulo: Estado de Deuda Familiar

## Propósito

Listar **familias** con estudiantes matriculados en el ciclo lectivo activo y emitir el PDF de **estado de deuda** de esa familia (cuotas con saldo, interés diario hasta hoy). No incluye la familia legacy «sin asignar» (`id` = 1). El módulo **Estado de Deuda por Estudiante** es independiente.

## Actores y permisos

Menú de Administración → Gestión de mora → **Estado de Deuda Familiar**. Permiso `permisos_ia` orden **63** (`PermisosIaCatalog::ADMIN_MORA_ESTADO_DEUDA`). Gate: `PermisosMora::puedeEstadoDeudaFamiliar()`.

## Flujo principal

1. Búsqueda por apellido de familia, responsable, o apellido/nombre/DNI del estudiante.
2. Filtro de nivel pedagógico.
3. Casilla **Solo alumnos con deuda**: solo familias que tienen al menos un estudiante matriculado (ciclo/nivel del listado) con cuotas `faltapa > 0` e `importe > 0`. En esa vista, la columna de estudiantes muestra **solo** esos alumnos con deuda.
4. **Exportar PDF / Excel** del listado filtrado (búsqueda, nivel y «Solo alumnos con deuda»). Incluye **todos** los registros que coinciden, no solo la página. Una fila por estudiante: curso, **deuda del estudiante**, familia, responsable y deuda de familia. PDF en A4 **vertical**; si el texto no entra, la fila usa hasta dos líneas. URLs con `{ref}` opaco.
5. PDF de la familia (ícono de la fila): todas las cuotas adeudadas de los miembros, no solo el ciclo activo.

## Archivos clave

- `app/Livewire/Mora/EstadoDeudaFamiliarIndex.php`
- `app/Support/Mora/EstadoDeudaFamiliarListado.php`
- `app/Support/Mora/EstadoDeudaFamiliarDatos.php`
- `resources/views/livewire/mora/estado-deuda-familiar-index.blade.php`
- PDF familia: `EstadoDeudaFamiliarPdfController`
- Listado PDF/Excel: `EstadoDeudaFamiliarListadoPdfController` / `EstadoDeudaFamiliarListadoExcelController`
- `app/Support/Mora/EstadoDeudaFamiliarListadoExport.php`, `EstadoDeudaListadoExcel.php`, `EstadoDeudaListadoTcpdf.php`

## Qué no hacer / reglas de negocio

- No listar `familias.id` = 1.
- El PDF sigue siendo por familia; el filtro de deuda no cambia el contenido del PDF.
- URLs de PDF con `{ref}` opaco.
