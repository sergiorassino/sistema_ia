# Módulo: Profesores presentes

## Propósito

Listar los **docentes que tienen clase** un día de la semana, entre un horario de inicio y uno de fin, en **cursos y secciones** elegidos. Sirve para saber quién está (o debería estar) en la escuela en esa franja. Incluye listado en pantalla e impresión PDF (TCPDF).

## Modalidades / variantes

Ninguna. Comportamiento único para todos los tenants.

## Actores y permisos

- Menú de Secretaría → grupo **HORARIOS** → **Profesores presentes**.
- Visible para cualquier usuario del Menú de Secretaría (igual que Impresión de horarios). **No** exige el permiso IA orden **13** (ese permiso solo habilita Configuración y Carga).
- No hay ítem en Menú de Administración, Menú de Docentes ni Menú de Alumnos.

## Tablas y campos críticos

| Tabla | Uso |
|-------|-----|
| `horarios26` | Grilla: docente, materia, curso, día (`idDia` lun/mar/…), módulo (`idHora` 1–10), turno (`idTurnoClase` si existe) |
| `reloj` | Texto de hora reloj por módulo (`orden` 1–10) y turno/nivel; se parsea para cruzar con la franja pedida |
| `ppc` | Asignación vigente docente × materia; sin fila en `ppc` el docente de la grilla no se lista |
| `cursos` / `materias` / `profesores` | Alcance `schoolCtx()` (nivel + ciclo) y etiquetas del listado |

La hora reloj se interpreta con textos tipo `08:00-08:40` o `8:00 a 8:40`. Un módulo entra si su intervalo **solapa** la franja (no si apenas coincide en el borde de fin).

## Flujo principal

1. Elegir día de la semana, horario inicio/fin y uno o más cursos/secciones.
2. **Emitir listado**: **un renglón por docente**, ordenado por **horario de presencia** (inicio del primer tramo; a igual hora, apellido), con el horario de ese día (módulos consecutivos unidos; si hay hueco, varios tramos: `08:00 a 09:20 · 11:00 a 11:40`).
3. **Imprimir PDF** (TCPDF, A4 vertical, Arial): mismos filtros; se revalidan cursos del nivel/ciclo activos.

## Fuente de verdad

| Dato | Quién escribe | Este módulo |
|------|---------------|-------------|
| Grilla | Carga de horarios | Solo lectura |
| Reloj | Configuración de horarios | Solo lectura |
| Asignación | ABM ppc | Solo lectura |

Si `horarios26.idProfesores` coincide con `ppc` para esa materia, se lista ese docente. Si no (o viene vacío), se usan los docentes de `ppc` de la materia (mismo criterio que la grilla por curso).

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Livewire | `app/Livewire/Horarios/ProfesoresPresentesIndex.php` |
| Vista | `resources/views/livewire/horarios/profesores-presentes-index.blade.php` |
| Consulta | `app/Support/Horarios/ProfesoresPresentesConsulta.php` |
| PDF | `app/Support/Horarios/ProfesoresPresentesTcpdf.php` |
| Controlador PDF | `app/Http/Controllers/Horarios/ProfesoresPresentesPdfController.php` |
| Rutas | `horarios.profesores-presentes`, `horarios.profesores-presentes.pdf` |
| Menú | `resources/views/layouts/app.blade.php` (grupo HORARIOS) |

## Qué no hacer / reglas de negocio

1. No calcular ni inventar presencia: solo hay docente si hay celda en `horarios26` cuyo reloj cae en la franja **y** hay asignación `ppc`. **Un docente = una fila**, con el horario fusionado de ese día.
2. No listar cursos de otro nivel o ciclo que el de `schoolCtx()`.
3. PDF nuevo: TCPDF + Arial; no DomPDF.
4. No exigir permiso 13 (consistente con Impresión de horarios).

## Checklist al modificar

- [ ] Filtro de cursos por `schoolCtx()->idNivel` / `idTerlec` en pantalla y PDF.
- [ ] Día vía códigos legacy de `HorariosProfesores` (`lun`, `mar`, …).
- [ ] Reloj por turno (`relojPorTurnoClase`); cursos de doble jornada usan ambas bandas.
- [ ] Cruce con `ppc` (no listar docente de grilla sin asignación).
- [ ] Fechas en UI/PDF en `d/m/Y` si se muestran; horas en `hh:mm`.
- [ ] Confirmaciones/errores con `se-swal-error` (no `alert` nativo).
