# Módulo: Parte diario del preceptor

## Propósito

Imprimir el **parte diario del preceptor** por curso(s) y fecha (PDF).

## Modalidades / variantes

Config por tenant: `config/tenant.php` → `parte_diario.implementacion`.

| Clave | PDF | Comportamiento |
|-------|-----|----------------|
| `estandar` (default) | DomPDF A4 / media hoja | Filas manuales vacías + firmas por hora del día (`HorariosProfesores`). |
| `sanfranciscoasis` | TCPDF **Legal** | Listado de alumnos **regulares** (`idCondiciones = 1`) con columnas 1ºh–10ºh vacías + bloque de firmas docentes por hora. |

Override documentado: `config/tenants/sanfranciscoasis.php` → `sanfranciscoasis`.

Helper: `tenantParteDiarioImplementacion()`.

## Actores y permisos

- Menú de Secretaría → grupo **ASISTENCIA ESTUDIANTES**.
- Permiso `permisos_ia` orden **81** (`PermisosIaCatalog::PARTE_DIARIO_PRECEPTOR`).

## Tablas y campos críticos

- `cursos` (selección; filtro `schoolCtx` nivel/ciclo).
- Modelo `sanfranciscoasis`: `matricula` + `legajos` (solo `idCondiciones = 1`).
- Horario del día: `horarios26` / `materias` / `reloj` vía `HorariosProfesores::filasParteDiarioCursoDia()`.

## Flujo principal

1. Elegir fecha + curso(s) (+ turno si un solo curso) → PDF (`seguimiento.partes-diarios.pdf`).
2. El controlador elige el modelo según `tenantParteDiarioImplementacion()`.

## Archivos clave

- Livewire: `app/Livewire/Seguimiento/Inasistencias/PartesDiariosIndex.php`
- PDF estándar: `ParteDiarioPreceptorPdfController` + `resources/views/pdf/parte-diario-preceptor*.blade.php`
- PDF SFA: `ParteDiarioSanfranciscoasisDatos`, `ParteDiarioSanfranciscoasisTcpdf`
- Config: `config/tenant.php` → `parte_diario`

## Qué no hacer / reglas de negocio

- No mezclar layouts: el cambio de modelo es solo por config de tenant.
- En SFA no inventar ausencias: las columnas de hora quedan vacías para marcado manual.
- PDF nuevo SFA: TCPDF + Arial (no DomPDF).

## Checklist al modificar

- [ ] ¿Permiso 81 en menú y rutas?
- [ ] ¿Variante lee `tenantParteDiarioImplementacion`?
- [ ] ¿Cursos filtrados por `schoolCtx`?
- [ ] ¿SFA: solo regulares (`idCondiciones = 1`)?
