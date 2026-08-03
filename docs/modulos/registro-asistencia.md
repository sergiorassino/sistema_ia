# Módulo: Registro de Asistencia

## Propósito

Imprimir el **registro mensual de asistencia** por curso(s) (PDF A4) y administrar **feriados por nivel**.

## Modalidades / variantes

Config por tenant × nivel en `config/tenant.php` → `registro_asistencia.por_nivel`:

| Clave | Comportamiento |
|-------|----------------|
| `con_datos` | Marca faltas en la grilla, totales por alumno/día y cuadros de estadísticas. |
| `sin_datos` | Misma grilla (alumnos + días) vacía para llenado manual; sin estadísticas. |

Default si no hay override: **`con_datos`** en todos los niveles.
Excepción documentada: Montecristo (inicial/primario → `sin_datos`) en `config/tenants/montecristo.php`.

Helper: `tenantRegistroAsistenciaImplementacion(?int $idNivel)`.

## Actores y permisos

- Menú de Secretaría → grupo **ASISTENCIA ESTUDIANTES**.
- Permiso `permisos_ia` orden **90** (`PermisosIaCatalog::REGISTRO_ASISTENCIA`).
- El mismo permiso cubre el ABM de feriados.

## Tablas y campos críticos

- `matricula` + `legajos` (listado; `idCondiciones < 5`).
- `inasistencias` (tipos día 2/3/4/6/7; EF = tipo 5; `just` J/I).
- `feriados` (`fechaFeriado`, `nombre`, `idNivel`).
- `terlec.fechaInicio` / `fechaFin` (ajuste de febrero/comienzo y diciembre).
- Acumulado desde `ento.fechaInicio` (si existe, legacy) o inicio de `terlec`.

## Flujo principal

1. Elegir mes + cursos → PDF (`seguimiento.registro-asistencia.pdf`).
2. Botón **Feriados** → ABM del nivel activo.

## Archivos clave

- Livewire: `app/Livewire/Seguimiento/RegistroAsistencia/`
- PDF: `RegistroAsistenciaDatos`, `RegistroAsistenciaTcpdf`
- Config: `config/tenant.php` → `registro_asistencia`

## Qué no hacer / reglas de negocio

- No inventar feriados globales: siempre filtrar por `idNivel` del contexto.
- No calcular promedios de calificaciones aquí (solo asistencia).
- PDF nuevo: TCPDF + Arial (no DomPDF).

## Checklist al modificar

- [ ] ¿Permiso 90 en menú y rutas?
- [ ] ¿Variante lee `tenantRegistroAsistenciaImplementacion`?
- [ ] ¿Feriados acotados al nivel?
- [ ] ¿Persistencia con `PersistenciaColumnas` en ABM?
