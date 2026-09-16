# Módulo: Recálculo de condiciones de examen

## Propósito

Al entrar a Exámenes (listado, gestión, actas volantes, permiso) y confirmar turno + año lectivo, recalcular `calificaciones.condAdeuda` (y a veces `inscri`) en materias adeudadas (`apro = 1`) del nivel activo.

## Modalidades / variantes

- **EQ / TM:** no se tocan.
- **Regular del ciclo del contexto** (`matricula.idCondiciones = 1`): `condAdeuda = PR`. Si `ento.examTodosInscri = T`, también `inscri = 1`.
- **Egresado de último año de medio** (no regular ahora; el año anterior al turno cursó el último `cursos.c` como regular):
  - Turnos **febrero, abril, julio, septiembre** del **año posterior al egreso** → `RE`.
  - **Diciembre** de ese año y **cualquier turno de años posteriores** → `PR`.
- **Resto de no regulares:** `PR`. No se modifica `inscri`.

El último año de medio es `config('tenant.promocion.ultimo_curso_secundario')` (6 por defecto; 5 en EPQ). Solo corre en nivel cuyo nombre contiene «secundari».

## Actores y permisos

- Menú de Secretaría, grupo Exámenes. Permiso IA 12.
- Contexto `schoolCtx()` (nivel + ciclo).

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `calificaciones` | `apro`, `condAdeuda`, `inscri` | Recálculo escribe condición; `inscri` solo en regulares con `examTodosInscri = T`. |
| `matricula` | `idCondiciones`, `idTerlec`, `idNivel` | Regular = 1. Egreso = regular del ciclo `ano_turno - 1` en último curso. |
| `cursos` | `c`, `idNivel` | Año de cursado. |
| `terlec` | `ano` | El ciclo anterior se busca por año, no por `id - 1`. |
| `turnos` | `turno`, `nturno` | Ventana RE por nombre (no por id 2/3/4). |
| `ento` | `examTodosInscri` | `T` = inscribir a todos los regulares. |

## Flujo principal

1. Elegir turno de examen y año lectivo; confirmar.
2. Recorrer `apro = 1` del nivel.
3. EQ/TM: omitir. Regular: PR. Egresado + feb/abr/jul/sep del año siguiente: RE. Resto: PR.

## Fuente de verdad

`MateriasAdeudadasCondicionRecalculo`. Códigos canónicos: `MateriasAdeudadasFiltros::CONDICIONES` (`PR`, `RE`, `EQ`, `TM`).

## Archivos clave

- `app/Support/Examenes/MateriasAdeudadasCondicionRecalculo.php`
- `app/Support/Examenes/MateriasAdeudadasFiltros.php`
- `app/Livewire/Examenes/Concerns/PreparaMateriasAdeudadasExamenes.php`
- `tests/Unit/MateriasAdeudadasCondicionRecalculoTest.php`

## Qué no hacer / reglas de negocio

- No pisar EQ/TM.
- No borrar `inscri` de no regulares (mesas cargadas a mano).
- No usar `idTerlec - 1` ni IDs fijos de turno.
- No aplicar la rama RE en primario (sexto grado) ni en adultos.

## Checklist al modificar

- [ ] ¿EQ/TM siguen intactos?
- [ ] ¿Regulares del ciclo actual siguen en PR?
- [ ] ¿Egresados: RE hasta septiembre del año siguiente y PR desde diciembre?
- [ ] ¿El ciclo anterior se resuelve por `terlec.ano`?
- [ ] ¿`RE` está en filtros, inscripción, notas y etiquetas de acta?
