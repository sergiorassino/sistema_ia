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
- Horario del día: **`horarios26`** / `materias` / `reloj` vía `HorariosProfesores::filasParteDiarioCursoDia()` (no la tabla `horarios` de ScriptCase). El docente de cada hora es el **cargado en la grilla** (`horarios26.idProfesores`), el mismo que el PDF de horario por curso. Detalle: [horarios.md](horarios.md).

## Flujo principal

1. Elegir curso(s), **turno a imprimir** (todos / mañana / tarde / …) y fecha → PDF (`seguimiento.partes-diarios.pdf`).
2. **Todos los turnos:** curso de un turno → una hoja; doble jornada → una hoja por jornada.
3. **Un turno concreto:** solo esas hojas; se omiten cursos que no tienen esa jornada.
4. El controlador elige el modelo según `tenantParteDiarioImplementacion()`.

## Archivos clave

- Livewire: `app/Livewire/Seguimiento/Inasistencias/PartesDiariosIndex.php`
- PDF estándar: `ParteDiarioPreceptorPdfController` + `resources/views/pdf/parte-diario-preceptor*.blade.php`
- PDF SFA: `ParteDiarioSanfranciscoasisDatos`, `ParteDiarioSanfranciscoasisTcpdf`
- Config: `config/tenant.php` → `parte_diario`

## Qué no hacer / reglas de negocio

- No mezclar layouts: el cambio de modelo es solo por config de tenant.
- En SFA no inventar ausencias: las columnas de hora quedan vacías para marcado manual.
- PDF nuevo SFA: TCPDF + Arial (no DomPDF).
- No reemplazar el docente de la celda por la lista `ppc` de la materia: si está cargado en `horarios26`, sale ese nombre.
- No imprimir solo el primer turno de un curso doble jornada: una hoja por mañana y otra por tarde, salvo que el usuario filtre un turno.

## Checklist al modificar

- [ ] ¿Permiso 81 en menú y rutas?
- [ ] ¿Variante lee `tenantParteDiarioImplementacion`?
- [ ] ¿Cursos filtrados por `schoolCtx`?
- [ ] ¿SFA: solo regulares (`idCondiciones = 1`)?
- [ ] ¿Docente por hora = el de `horarios26`, no la lista `ppc`?
- [ ] ¿Curso mañana/tarde: una hoja por turno, o solo el turno filtrado?
