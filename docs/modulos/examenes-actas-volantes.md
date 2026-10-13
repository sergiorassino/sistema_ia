# Módulo: Actas volantes de examen

## Propósito

Armar e imprimir actas volantes de materias adeudadas inscriptas a examen (`apro = 1`, `inscri = 1`), agrupadas por materia del plan y condición (`PR`, `RE`, `EQ`, `TM`).

La matrícula de esa calificación (`calificaciones.idMatricula`) tiene que ser regular (`matricula.idCondiciones = 1`). Si en el curso de la materia la condición es otra (pase, analítico u otra distinta de regular), esa fila no entra al acta.

## Modalidades / variantes

- **`curso_seccion`** (default, IESS y el resto salvo override): una acta por `idMatPlan` + `condAdeuda` + sección estructural (letra/turno). Reúne el mismo espacio de distintos años lectivos.
- **`curso`**: una acta por `idMatPlan` + `condAdeuda` (junta secciones del mismo año de plan). Override en `config/tenants/{slug}.php` si un colegio lo pide.

El `idMatPlan` se resuelve igual que en listado/permiso: `materias.idMatPlan` si existe; si no, `calificaciones.idMatPlan`. Si no hay plan, se agrupa por `idMaterias`.

Un egresado de 6.º con deudas de 6.º B **y** de Matemática 4.º B genera **actas distintas**. Matemática 4.º B no va en el acta de 6.º.

## Actores y permisos

Menú de Secretaría, grupo Exámenes. Permiso IA 12. Contexto `schoolCtx()`.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `calificaciones` | `apro`, `inscri`, `condAdeuda`, `idMatPlan`, `idMaterias`, `idCursos`, `idMatricula` | Solo filas inscriptas cuya matrícula es regular. |
| `matricula` | `idCondiciones` | Regular = 1. Otra condición en el curso de la materia: no es previa. |
| `materias` | `idMatPlan`, `idCurPlan`, `materia` | Plan de deudas viejas suele estar acá, no en `calificaciones.idMatPlan`. |
| `matplan` / `curplan` | `matPlanMateria`, `curPlanCurso` | Etiquetas de materia y año de plan. |
| `cursos` | `cursec`, `s`, `c`, `idTurnoClase` | Sección del curso de la calificación. |

## Flujo principal

1. Confirmar turno y año (recálculo de condiciones).
2. Listar actas del nivel.
3. PDF TCPDF por acta seleccionada.

## Fuente de verdad

`ActaVolantePrevios`. Modalidad: `tenantExamenesActaVolantePreviosModalidad()`.

## Archivos clave

- `app/Support/Examenes/ActaVolantePrevios.php`
- `app/Support/Examenes/ActaVolantePreviosTcpdf.php`
- `app/Livewire/Examenes/ActaVolantePreviosIndex.php`
- `tests/Unit/ActaVolantePreviosTest.php`

## Qué no hacer / reglas de negocio

- No inner-join solo con `calificaciones.idMatPlan > 0`: se pierden deudas de años anteriores.
- No incluir una materia si la matrícula de esa calificación no es regular (`idCondiciones = 1`).
- No mezclar Matemática 4.º y materias de 6.º en la misma hoja (el plan/año es otro `idMatPlan`).
- No exigir `planes.idNivel` además de `cursos.idNivel`.

## Checklist al modificar

- [ ] ¿Una deuda de 4.º de una egresada de 6.º aparece en un acta Regular de esa materia solo si esa matrícula de 4.º es regular?
- [ ] ¿Una matrícula con `idCondiciones` distinto de 1 queda fuera del acta?
- [ ] ¿EQ/TM/PR/RE siguen en el encabezado de condición?
- [ ] ¿El default (`curso_seccion`) y la alternativa (`curso`) siguen agrupando como antes, ahora con el plan resuelto?
