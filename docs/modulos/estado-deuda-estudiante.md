# Módulo: Estado de Deuda por Estudiante

## Propósito

Listar **estudiantes matriculados** en el ciclo lectivo activo y emitir el PDF de **estado de deuda** de ese estudiante (cuotas con saldo, interés diario hasta hoy). Incluye alumnos **sin familia asignada** (`legajos.idFamilias` = 1). El módulo **Estado de Deuda Familiar** no se reemplaza.

## Modalidades / variantes

Ninguna por tenant. El alcance de nivel sigue `SchoolAlcancePedagogico` (el ítem vive en el Menú de Administración).

## Actores y permisos

Menú de Administración (`layouts/administracion`) → Gestión de mora → **Estado de Deuda por Estudiante**. Permiso `permisos_ia` orden **98** (`PermisosIaCatalog::ADMIN_MORA_ESTADO_DEUDA_ESTUDIANTE`). Gate: `PermisosMora::puedeEstadoDeudaEstudiante()`.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|-------|
| `legajos` | `idFamilias`, apellido, nombre, DNI | Se listan todos los matriculados del ciclo; familia 1 = sin asignar. |
| `familias` | `apellido`, `responsable` | Solo se muestran si el id no es 1. |
| `matricula` / `cursos` | `idTerlec`, `idNivel` | Filtro de ciclo activo y nivel pedagógico. |
| `cuotasgeneradas` | `faltapa`, `importe`, vencimientos, `idLegajos` | Solo saldo > 0 e importe > 0 **de ese estudiante**. |

## Flujo principal

1. Búsqueda por apellido, nombre, DNI; opcionalmente por familia o responsable (si hay familia real).
2. Filtro de nivel pedagógico (Todos / Inicial / Primario / Secundario).
3. Casilla **Solo alumnos con deuda**: deja en el listado únicamente estudiantes con cuotas `faltapa > 0` e `importe > 0` (cualquier ciclo).
4. En cada fila: estudiante, deuda, DNI, curso, familia (o «Sin familia»), responsable y PDF.
5. El PDF usa el mismo layout TCPDF que el estado de deuda familiar, con líneas de cuotas **solo del estudiante**.

## Fuente de verdad

Listado: `EstadoDeudaEstudianteListado`. Totales y PDF: `EstadoDeudaEstudianteDatos` + `ImputacionPagoCalculo` (mora diaria hasta hoy). Maquetación: `EstadoDeudaFamiliarTcpdf`. Token opaco: `OpaqueRouteToken::forEstadoDeudaEstudiante`.

## Archivos clave

- `app/Livewire/Mora/EstadoDeudaEstudianteIndex.php`
- `app/Support/Mora/EstadoDeudaEstudianteListado.php`
- `app/Support/Mora/EstadoDeudaEstudianteDatos.php`
- `resources/views/livewire/mora/estado-deuda-estudiante-index.blade.php`
- `app/Http/Controllers/Mora/EstadoDeudaEstudiantePdfController.php`

## Qué no hacer / reglas de negocio

- No excluir estudiantes con `idFamilias` = 1.
- No agrupar deuda de hermanos: el PDF y el total de la fila son **solo de ese legajo**.
- URLs de PDF con `{ref}` opaco, no IDs de legajo.
- No quitar ni reutilizar el permiso 63 (estado de deuda familiar).

## Checklist al modificar

- [ ] Permiso 98 en ruta, Livewire (`mount`) y sidebar.
- [ ] Filtro de matrícula por `schoolCtx()->idTerlec` y `SchoolAlcancePedagogico`.
- [ ] Familia id 1 se muestra como «Sin familia», no como familia real.
- [ ] Interés del PDF con `moraDiariaHastaFechaCalculo = true`.
- [ ] Casilla «Solo alumnos con deuda» filtra por `faltapa > 0` e `importe > 0` y hace `resetPage()`.
