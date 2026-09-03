# Módulo: Listados de estudiantes con formato

## Propósito

Generar PDFs A4 verticales (TCPDF) de **estudiantes regulares** matriculados en el ciclo activo, con un modelo de hoja preconfigurado. Un listado por curso elegido. Orden alfabético español (`OrdenAlfabeticoEstudiante`).

## Modalidades / variantes

Mismos modelos en Menú de Secretaría y Menú de Docentes (alcance: cursos del nivel de sesión).

| Modelo | Extra en el formulario | Contenido |
|--------|------------------------|-----------|
| Cuadriculado | — | Nº, apellido y nombre, cuadros vacíos |
| Renglón | — | Nº, apellido y nombre, renglón vacío |
| Calendario | Mes a imprimir | Grilla de días del mes (sábados y domingos en gris) |
| Registro de firmas | — | Madre/padre, firma y aclaración |
| Fotos | Tamaño 2×2 / 4×4 / 8×8 cm | Foto carnet, apellido y nombre, curso y sección, año lectivo. **Solo aparece si el colegio tiene foto carnet en solapas del legajo** (`FotoCarnetLegajo::habilitadaEnSolapasLegajo()`: columna `legajos.fotoCarnet` + campo asignado a una solapa). Tenants con solapa: Caixal SF, IESS, Montecristo. |

## Actores y permisos

- **Menú de Secretaría:** `listados.estudiantes-formato` (mismo permiso de listados de estudiantes que el resto del grupo).
- **Menú de Docentes:** `portalDocente.listados.estudiantesFormato` (`listado_estudiantes_formato` en el menú del portal).

El PDF revalida cursos contra `ListadoCursoConsulta::cursosPermitidosEnContexto()`. Rate-limit 30/min.

## Tablas y campos críticos

| Tabla | Campos |
|-------|--------|
| `matricula` | `idCursos`, `idLegajos`, `idCondiciones` (regulares), `idTerlec`, `fechaBaja` |
| `legajos` | `apellido`, `nombre`; firmas: `nombremad`, `nombrepad`; fotos: `fotoCarnet`, `dni` (si existen) |
| `cursos` | `c`, `s`, `cursec` (curso y sección bajo cada foto) |
| Disco `privado` | Archivo de foto carnet (`FotoCarnetLegajo`) |

## Flujo principal

1. Elegir uno o más cursos.
2. Elegir el modelo. Si es calendario, el mes. Si es fotos, el tamaño (por defecto mediano 4×4 cm).
3. Abrir PDF en pestaña nueva (`cursos`, `modelo`, y `mes`/`tamano` en query; sin DNI ni IDs de legajo en la ruta).
4. Un bloque por curso; si no hay foto carnet, queda el recuadro vacío.

## Fuente de verdad

Matrícula regular del ciclo (`schoolCtx()->idTerlec`) y nivel de sesión. Fotos: `legajos.fotoCarnet` + respaldo por DNI en disco privado. Curso/sección: `cursos.c` / `cursos.s`, o `cursec`.

## Archivos clave

- `app/Livewire/Listados/ListadoEstudiantesFormato.php`
- `app/Http/Controllers/ListadoEstudiantesFormatoPdfController.php`
- `app/Support/Listados/ListadoEstudiantesFormatoCatalog.php`
- `app/Support/Listados/ListadoEstudiantesFormatoDatos.php`
- `app/Support/Listados/ListadoEstudiantesFormatoTamanoFoto.php`
- PDF fotos: `ListadoEstudiantesFormatoFotosTcpdf.php`
- Vista: `resources/views/listados/livewire/listados/estudiantes-formato.blade.php`

## Qué no hacer / reglas de negocio

- No calcular promedios. No incluir alumnos dados de baja ni fuera de regulares.
- No poner IDs de legajo ni DNI en la URL del PDF.
- PDFs nuevos en TCPDF + Arial, no DomPDF.
- Si el colegio no tiene foto carnet en solapas (`FotoCarnetLegajo::habilitadaEnSolapasLegajo()`), no mostrar ni generar el modelo de fotos (tampoco por URL).
- Si el modelo está habilitado y un estudiante no tiene archivo de foto, el recuadro queda vacío; no simular que hay imagen.

## Checklist al modificar

- [ ] ¿El orden usa `OrdenAlfabeticoEstudiante`?
- [ ] ¿Un curso nuevo en el catálogo tiene clase TCPDF y rama en el controlador?
- [ ] ¿Fotos resuelven el archivo con `FotoCarnetLegajo` (path + respaldo DNI)?
- [ ] ¿El tamaño 2×2 / 4×4 / 8×8 está en cm (20 / 40 / 80 mm)?
- [ ] ¿El modelo de fotos se oculta y el PDF responde 404 si no hay solapa de foto carnet?
