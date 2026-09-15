# Módulo: Libro de aranceles

## Propósito

PDF A4 apaisado con el listado de aranceles por curso (matrícula y cuotas marzo–diciembre) para impresión en Administración.

## Modalidades / variantes

Un PDF por selección de cursos; cada curso empieza en página nueva. Numeración desde la página inicial indicada.

Alumnos incluidos (tenant `cuotas.libro_aranceles.solo_regulares`):

| Valor | Condiciones | Colegios |
|-------|-------------|----------|
| `false` (default) | `idCondiciones` 1 a 4 (regular, pase y demás) | Todos salvo override |
| `true` | Solo Regular (`idCondiciones` = 1) | Instituto Ramallo |

Helper: `tenantCuotasLibroArancelesSoloRegulares()`. No ramificar por `tenantSlug()`.

## Actores y permisos

Menú de Administración. Gate: `PermisosCuotas::puedeLibroAranceles()`. Rutas `cuotas.libro-aranceles` (selección) y `cuotas.libro-aranceles.pdf`.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `matricula` | `idCursos`, `idTerlec`, `idCondiciones`, `fechaBaja`, `idCuotasbecas` | Sin baja. Condiciones según el flag del tenant. |
| `condiciones` | `proteg` | Se excluye `proteg` = 99. |
| `legajos` | `apellido`, `nombre` | Orden alfabético español. |
| `cuotasgeneradas` | `pagado`, `nroComp`, tipo 1 (meses 3–12) y tipos 2/3 (matrícula/reserva) | |

## Flujo principal

1. Elegir cursos del ciclo activo y página inicial.
2. Abrir el PDF: un bloque por curso, alumnos ordenados según el filtro del tenant.

## Fuente de verdad

`LibroArancelesDatos` arma las filas (`idsCondicionesParaQuery()`); `LibroArancelesTcpdf` maqueta.

## Archivos clave

- `app/Livewire/Cuotas/LibroArancelesIndex.php`
- `app/Support/Cuotas/LibroArancelesDatos.php`
- `app/Support/Cuotas/LibroArancelesTcpdf.php`
- `app/Http/Controllers/Cuotas/LibroArancelesPdfController.php`
- `config/tenant.php` → `cuotas.libro_aranceles.solo_regulares`
- `config/tenants/institutoramallo.php`

## Qué no hacer / reglas de negocio

- No hardcodear `if (tenantSlug() === 'institutoramallo')`. Usar el flag.
- Default: condiciones 1–4. Solo regulares si el colegio activa `solo_regulares`.

## Checklist al modificar

- [ ] Default del tenant sigue incluyendo 1–4.
- [ ] Con `solo_regulares` solo entra condición 1.
- [ ] Cursos sin alumnos del filtro no generan página vacía.
- [ ] PDF TCPDF A4 apaisado; orden alfabético con `OrdenAlfabeticoEstudiante`.
