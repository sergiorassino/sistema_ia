# Módulo: Listado de Profesores con Horarios

Ítem del grupo HORARIOS. Contexto de la grilla (`horarios26` vs `horarios`): [horarios.md](horarios.md).

## Propósito

Listar los **docentes que tienen materia y curso asignados** en el nivel y ciclo activos, con **cinco columnas** (lunes a viernes) y el **horario en que asiste** cada uno. Incluye el listado en pantalla y la impresión PDF (TCPDF, A4 apaisado).

## Modalidades / variantes

Ninguna. Comportamiento único para todos los tenants.

## Actores y permisos

- Menú de Secretaría → grupo **HORARIOS** → **Listado de Profesores con Horarios**.
- Permiso IA orden **115** (`PermisosIaCatalog::LISTADO_PROFESORES_HORARIOS`).
- No usa el permiso 13 (ese queda en Configuración y Carga).
- No hay ítem en Menú de Administración, Menú de Docentes ni Menú de Alumnos.

## Tablas y campos críticos

| Tabla | Uso |
|-------|-----|
| `horarios26` | Grilla: docente, materia, curso, día (`idDia` lun/mar/…), módulo (`idHora` 1–10), turno. **No** usar la tabla `horarios` (ScriptCase). |
| `reloj` | Texto de hora reloj por módulo y turno; se fusionan los módulos consecutivos del día |
| `ppc` | Asignación vigente docente × materia. Sin fila en `ppc` el docente no se lista |
| `cursos` / `materias` / `profesores` | Alcance `schoolCtx()` (nivel + ciclo) y el nombre del docente |

Solo entran **lunes a viernes**. Sábado y domingo no tienen columna aunque el nivel los tenga activos.

## Flujo principal

1. Usuario con permiso 115 abre el ítem.
2. Ve un renglón por docente, orden alfabético (collation española), con el horario de cada día.
3. **Imprimir PDF**: mismo listado, sin filtros ni IDs en la URL. El alcance sale de la sesión.

El horario de un día une módulos seguidos (`08:00 a 12:40`). Si hay un hueco, quedan varios tramos separados por ` · `.

## Fuente de verdad

| Dato | Quién escribe | Este módulo |
|------|---------------|-------------|
| Grilla | Carga de horarios | Solo lectura |
| Reloj | Configuración de horarios | Solo lectura |
| Asignación | ABM ppc | Solo lectura |

Un docente entra si tiene al menos una celda de lunes a viernes en `horarios26` cuya materia está asignada en `ppc` y cuyo reloj se puede leer. Si `horarios26.idProfesores` coincide con `ppc`, se usa ese docente; si no (o viene vacío), se usan los docentes de `ppc` de la materia.

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Livewire | `app/Livewire/Horarios/ListadoProfesoresHorariosIndex.php` |
| Vista | `resources/views/livewire/horarios/listado-profesores-horarios-index.blade.php` |
| Consulta | `app/Support/Horarios/ListadoProfesoresHorariosConsulta.php` |
| PDF | `app/Support/Horarios/ListadoProfesoresHorariosTcpdf.php` |
| Controlador PDF | `app/Http/Controllers/Horarios/ListadoProfesoresHorariosPdfController.php` |
| Rutas | `horarios.listado-profesores`, `horarios.listado-profesores.pdf` |
| Menú | `resources/views/layouts/app.blade.php` (grupo HORARIOS) |
| Permiso | `PermisosIaCatalog::LISTADO_PROFESORES_HORARIOS` (115) |
| SQL | `database/sql/permiso_ia_orden_115_listado_profesores_horarios.sql` |

## Qué no hacer / reglas de negocio

1. No leer la tabla `horarios` (ScriptCase). La grilla es `horarios26`.
2. No listar docentes de otro nivel o ciclo que el de `schoolCtx()`.
3. No listar un docente sin asignación `ppc` de esa materia.
4. No agregar columnas de sábado o domingo: el impreso es de cinco días.
5. PDF nuevo: TCPDF + Arial. La URL del PDF no lleva IDs.
6. No calcular promedios ni otros datos ajenos al horario.

## Checklist al modificar

- [ ] Permiso 115 en ruta, Livewire (`mount`), controlador PDF y sidebar.
- [ ] Filtro `schoolCtx()->idNivel` / `idTerlec` en pantalla y PDF.
- [ ] Días vía códigos legacy de `HorariosProfesores` (`lun` … `vie`).
- [ ] Reloj por turno; doble jornada usa el turno de la celda.
- [ ] Cruce con `ppc`.
- [ ] Horas en `hh:mm`; fecha de emisión del PDF en `d/m/Y`.
- [ ] Orden alfabético con `OrdenAlfabeticoEstudiante`.
