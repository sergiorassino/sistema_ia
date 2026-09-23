# Módulo: Estadística de pago por curso y cuota

## Propósito

PDF A4 apaisado con la estadística de cobro **por sala / grado / curso** y por columna de matrícula y meses marzo–diciembre, a una **fecha de cálculo**.

No modifica datos. No hay portal de Alumnos ni de Docentes.

## Modalidades / variantes

Ninguna por tenant. El alcance de nivel sigue `SchoolAlcancePedagogico` (el ítem vive en el Menú de Administración).

## Actores y permisos

| Requisito | Detalle |
|-----------|---------|
| Menú | **Menú de Administración** (`layouts/administracion`), grupo sidebar **Resúmenes** |
| Permiso | `PermisosIaCatalog::ADMIN_ESTADISTICA_PAGO_POR_CURSO` — **orden 108** |
| Gate | `PermisosCuotas::puedeEstadisticaPagoPorCurso()` |
| Rutas | `cuotas.estadistica-pago-por-curso` y `.pdf` — middleware `permiso:108` + `menu.portal:administracion` |

Sin permiso o fuera de Administración: **403**.

## Tablas y campos críticos

Todas las consultas filtran por **`idTerlec`** del contexto de sesión.

| Tabla | Uso |
|-------|-----|
| `cursos` | Una fila del PDF por curso del ciclo (y del nivel elegido). Orden: nivel, año/sala, `orden`. |
| `cuotasgeneradas` | Conteos e importes por `idCursos` + `idCuotasmeses`. |
| `ento` | Nombre y logo del encabezado. |

Columnas de mes: matrícula (`idCuotasmeses` **15**) y marzo–diciembre (**3** a **12**).

## Fuente de verdad

`App\Support\Cuotas\EstadisticaPagoPorCursoDatos`.

Universo de cada celda: cuotas generadas del curso y mes con **`venc1` menor o igual a la fecha de cálculo** (el día completo, hasta las 23:59:59).

| Fila | Regla |
|------|--------|
| Cantidad de estudiantes | Cantidad de cuotas con `venc1` alcanzado. |
| Estudiantes que pagaron | De esas, `ROUND(faltapa, 2) <= 0` y `fechaPago` hasta el fin de la fecha de cálculo. |
| Importe esperado | `SUM(importe)` de esas cuotas. No se resta la bonificación prevista del 1.er vencimiento (`signo1v` / `valor1v`). |
| Importe abonado | `SUM(pagado) − SUM(interes)` de las cuotas con `venc1` alcanzado y `fechaPago` hasta esa fecha. |
| Con / sin pago bonificado | Pagadas (`faltapa` cubierto) con `bonificacion > 0` o `<= 0`. |
| Total adeudado | `SUM(faltapa)` de las cuotas con `venc1` alcanzado. Es el **saldo actual** de esas cuotas, no una reconstrucción histórica del saldo a la fecha. |
| Porcentaje de deuda | `adeudado × 100 / importe esperado`. Si el esperado es 0, el porcentaje es 0. |

El bloque **Totales** suma cursos y vuelve a calcular el porcentaje sobre esos totales (no promedia porcentajes).

La consulta es un `GROUP BY idCursos, idCuotasmeses`. No repite una subconsulta por curso y mes.

## Flujo principal

1. Usuario con permiso 108 entra a **Menú de Administración** → **Resúmenes** → **Estadística de pago por curso**.
2. Elige la fecha de cálculo (por defecto, hoy) y, si corresponde, un nivel.
3. **Imprimir estadística (PDF)** abre el A4 apaisado.

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Livewire | `app/Livewire/Cuotas/EstadisticaPagoPorCursoIndex.php` |
| Consulta | `app/Support/Cuotas/EstadisticaPagoPorCursoDatos.php` |
| PDF | `app/Support/Cuotas/EstadisticaPagoPorCursoTcpdf.php` |
| Controlador | `app/Http/Controllers/Cuotas/EstadisticaPagoPorCursoPdfController.php` |
| Vista | `resources/views/livewire/cuotas/estadistica-pago-por-curso.blade.php` |
| Permiso | `PermisosIaCatalog::ADMIN_ESTADISTICA_PAGO_POR_CURSO` (108) |
| SQL / migración | `database/sql/permiso_ia_orden_108_estadistica_pago_por_curso.sql`, migración homónima |

## Qué no hacer / reglas de negocio

1. **No mezclar ciclos:** solo cursos y cuotas generadas del `schoolCtx()->idTerlec`.
2. **No tratar un pago parcial como estudiante que pagó:** si queda `faltapa`, no entra en «que pagaron» ni en los conteos con/sin bonificación. El importe abonado sí suma `pagado`.
3. **No restar la bonificación prevista** (`signo1v` / `valor1v` de `cuotasimportes`) del importe esperado. El informe anterior imprimía `SUM(importe)`.
4. **No reconstruir el saldo a una fecha pasada** con `faltapa`: ese campo es el saldo de hoy.
5. **No poner IDs de estudiantes en la URL.** La fecha y el nivel pedagógico van en la query del PDF.
6. **No usar DomPDF.**

## Checklist al modificar

- [ ] ¿El universo sigue siendo `venc1` hasta la fecha de cálculo?
- [ ] ¿Importe esperado = `SUM(importe)`, sin descontar `signo1v`?
- [ ] ¿Importe abonado = `pagado − interes`?
- [ ] ¿El porcentaje de totales se recalcula y no se promedia?
- [ ] ¿Permiso 108 en ruta, Livewire y sidebar?
- [ ] ¿PDF TCPDF A4 apaisado, fuente Arial, fecha `d/m/Y`?
