# Módulo: Gestión de tercer materia

## Propósito

Cargar y consultar las notas TM (TM1–TM6 y Nota) de alumnos regulares del ciclo activo con `calificaciones.apro = 1` y `condAdeuda = TM`. Esas notas se muestran en el pie del **boletín** y de la **consulta de calificaciones** (si el tenant lo habilita).

## Modalidades / variantes

- Solo visible si `tenantBoletinMuestraTercerMateria()` (`config/tenant.php` → `boletin.mostrar_tercer_materia`).
- Carga en grilla Livewire (guardado al salir de cada campo). Flechas y Enter pasan de celda.
- Desplegable de cada celda: 1 a 10, **a** (ausente), **Aprob** y **Reprob**.
- Búsqueda por alumno y filtros por curso de la materia adeudada y por curso actual (orden pedagógico: 1.º, 2.º, 3.º… y sección A, B, C).
- PDF de grilla e impresión de acta de compromiso.

## Actores y permisos

- Menú de Secretaría, grupo Exámenes. Permiso IA de exámenes (`PermisosIaCatalog::EXAMENES`).
- Filtrado por `schoolCtx()` (nivel + ciclo lectivo activo).

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `calificaciones` | `tm1`…`tm6`, `tmNota`, `apro`, `condAdeuda` | Escritura solo de los campos TM. Valores: nota numérica, **a** (ausente) o textos **Aprob** / **Reprob**. |

## Flujo principal

1. Listar TM del nivel en sesión, con matrícula regular en el ciclo activo.
2. Filtrar por alumno, curso de la materia adeudada y/o curso actual.
3. Editar TM1–TM6 / Nota; al blur se persiste (`TercerMateriaGestor::actualizarCamposTm`).
4. Boletín / consulta leen las mismas filas vía `ConsultaCalificacionesAlumno`.

## Fuente de verdad

- `calificaciones.tm1`…`tm6` y `tmNota`.

## Archivos clave

- `app/Livewire/Examenes/TercerMateriaIndex.php`
- `app/Support/Examenes/TercerMateriaGestor.php`
- `app/Support/BoletinesSecundario/BoletinConsultaCalificacionesTcpdf.php` (pie TM)
- `resources/views/pdf/partials/consulta-calificaciones-tercer-materia-pie.blade.php` (DomPDF legacy)

## Qué no hacer / reglas de negocio

- No exigir solo números: **a** (ausente), **Aprob** y **Reprob** son valores válidos (se normalizan sin importar mayúsculas).
- En boletín y consulta de calificaciones, celdas TM de 10 mm y **Nota** (celda 7) de 22 mm, fondo `#C1D7DA`, mismo borde que las parciales (sin línea extra de separación).
- En la grilla de carga, TM1–TM6 y Nota van juntas, del mismo ancho (sin barra divisoria).
- No mostrar el módulo ni el pie si el tenant no tiene `mostrar_tercer_materia`.

## Checklist al modificar

- [ ] ¿El desplegable lista 1–10, a, Aprob y Reprob?
- [ ] ¿La búsqueda y los filtros de curso recortan el listado (y el PDF de grilla)?
- [ ] ¿Los filtros de curso salen en orden pedagógico (no alfabético: SEGUNDO antes que CUARTO)?
- [ ] ¿Flechas y Enter pasan de campo sin recargar la grilla?
- [ ] ¿Boletín y consulta (TCPDF) muestran la celda 7 más ancha, con fondo, sin borde distinto de las parciales?
- [ ] ¿El guardado usa `PersistenciaColumnas` y no muestra éxito si no persistió?
