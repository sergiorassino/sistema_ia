# Módulo: Estadística por edad

## Propósito

Contar **alumnos regulares** del ciclo y nivel de sesión según la **edad cumplida** a una fecha de cálculo, con una columna por curso.

La planilla replica el impreso «ESTADÍSTICAS POR EDAD» (solo regulares): filas de edad y columnas de sala, grado o curso. No modifica datos.

## Modalidades / variantes

| Vista | Ruta | Salida |
|-------|------|--------|
| Grilla | `estadistica.porEdad` | Fecha de cálculo y la matriz edad × curso |
| PDF | `estadistica.porEdad.pdf?fecha=aaaa-mm-dd` | El mismo cuadro en A4 apaisado (TCPDF) |

El año lectivo y el nivel son los de **contexto de sesión** (`schoolCtx()`). Para otro nivel hay que cambiar el contexto. No hay selector de nivel en la pantalla.

## Actores y permisos

| Requisito | Detalle |
|-----------|---------|
| Menú | **Menú de Secretaría** (`layouts/app`), grupo sidebar **ESTADÍSTICAS** |
| Permiso | `PermisosIaCatalog::ESTADISTICA_POR_EDAD` — **orden 113** |
| Niveles | Inicial, primario, secundario, terciario y adultos. No en sesión Administración |
| Rutas | Middleware `permiso:113` |

El grupo ESTADÍSTICAS también muestra rendimiento escolar (orden 65, solo secundario) y estadística por sexo y curso (orden 112).

## Tablas y campos críticos

| Tabla | Uso |
|-------|-----|
| `cursos` | Una columna por curso del `idNivel` + `idTerlec` de sesión. Etiqueta abreviada: `c` + `s` (ej. `1 A`). Si faltan, `cursec`. |
| `matricula` | Regulares: `idCondiciones = 1`. Entran si `fechaMatricula` es nula, `0000-00-00` o anterior o igual a la fecha de cálculo. Quedan fuera si `fechaBaja` es anterior o igual a esa fecha. |
| `legajos` | `fechnaci`: edad cumplida (años enteros) respecto de la fecha de cálculo. |
| `ento` | Nombre y logo del encabezado del PDF. |

## Fuente de verdad

`App\Support\Estadistica\EstadisticaPorEdadDatos`.

Filas fijas:

| Fila | Edad cumplida |
|------|----------------|
| Menos de 3 años | 0, 1 o 2 |
| 3 años … 19 años | esa edad |
| 20 a 25 años | 20 a 25 |
| Más de 25 años | 26 o más |
| Sin fecha de nacimiento | vacía, `0000-00-00` o posterior a la fecha de cálculo |
| Total | suma de la columna |

La columna **Total** suma la fila. Los cursos sin alumnos se muestran en 0.

Si no entran todas las columnas en la hoja, el PDF sigue en otra página y repite las filas de edad.

## Flujo principal

1. Usuario con permiso 113 entra a **Menú de Secretaría → ESTADÍSTICAS → Estadísticas por edad**.
2. Elige la fecha de cálculo (por defecto, hoy).
3. La grilla muestra los cursos del ciclo y nivel activos.
4. **Imprimir (PDF)** abre el A4 apaisado con esa fecha.

## Archivos clave

- `app/Support/Estadistica/EstadisticaPorEdadDatos.php`
- `app/Support/Estadistica/EstadisticaPorEdadTcpdf.php`
- `app/Livewire/Estadistica/PorEdad.php`
- `app/Http/Controllers/Estadistica/EstadisticaPorEdadPdfController.php`
- `resources/views/livewire/estadistica/por-edad.blade.php`
- `resources/views/layouts/partials/sidebar-grupo-estadisticas.blade.php`
- `database/sql/permiso_ia_orden_113_estadistica_por_edad.sql`

## Qué no hacer / reglas de negocio

1. No incluir otra condición que no sea regular (`idCondiciones = 1`).
2. No listar cursos de otro nivel o ciclo que el de la sesión.
3. No calcular la edad al 31/12 del ciclo: la referencia es la fecha de cálculo.
4. No poner IDs de alumnos en la URL. La fecha va en la query del PDF.
5. No usar DomPDF.

## Checklist al modificar

- [ ] ¿Sigue siendo edad cumplida a la fecha de cálculo?
- [ ] ¿Regulares con baja posterior a esa fecha siguen contando?
- [ ] ¿Quienes se matriculan después de esa fecha quedan fuera?
- [ ] ¿Permiso 113 en ruta, Livewire y sidebar?
- [ ] ¿PDF TCPDF A4 apaisado, fuente Arial, fecha `d/m/Y`?
