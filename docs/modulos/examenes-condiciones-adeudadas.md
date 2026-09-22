# Módulo: Recálculo de condiciones de examen

## Propósito

Al entrar a Exámenes (listado, gestión, actas volantes, permiso) y confirmar turno + año lectivo, recalcular `calificaciones.condAdeuda` (y a veces `inscri`) en materias adeudadas (`apro = 1`) del nivel activo.

## Modalidades / variantes

- **EQ / TM:** no se tocan.
- **Regular del ciclo del contexto** (`matricula.idCondiciones = 1`): `condAdeuda = PR`. Si `ento.examTodosInscri = T`, también `inscri = 1`.
- **Egresado de último año de medio** (no regular ahora; el año anterior al turno cursó el último `cursos.c` como regular):
  - Turnos **febrero, abril, julio, septiembre** del **año posterior al egreso** → condición de ventana (`RE` por default) en **todas** las materias adeudadas (6.º y también 4.º, 5.º, etc.). Si `ento.examTodosInscri = T`, también `inscri = 1` (aunque el tenant asigne `PR`).
  - **Diciembre** de ese año y **cualquier turno de años posteriores** → `PR`.
- **Resto de no regulares:** `PR`. No se modifica `inscri`.

El último año de medio es `config('tenant.promocion.ultimo_curso_secundario')` (6 por defecto; 5 en EPQ). Solo corre en nivel cuyo nombre contiene «secundari».

Variante por tenant: `config('tenant.examenes.egresados_ventana_condicion')` (`RE` default | `PR`). Helper: `tenantExamenesEgresadosVentanaCondicion()`.

- **Regular (`RE`):** default para todos los colegios.
- **Previo (`PR`):** IESS y Caixal SF.

```php
// config/tenants/iess.php y config/tenants/caixalsf.php
return [
    'examenes' => [
        'egresados_ventana_condicion' => 'PR',
    ],
];
```

## Actores y permisos

- Menú de Secretaría, grupo Exámenes. Permiso IA 12.
- Contexto `schoolCtx()` (nivel + ciclo).

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `calificaciones` | `apro`, `condAdeuda`, `inscri` | Recálculo escribe condición; `inscri` si `examTodosInscri = T` en regulares (PR) y en egresados en la ventana feb–sep (aunque el tenant asigne `PR`). |
| `matricula` | `idCondiciones`, `idTerlec`, `idNivel` | Regular = 1. Egreso = regular del ciclo `ano_turno - 1` en último curso. |
| `cursos` | `c`, `idNivel` | Año de cursado. |
| `terlec` | `ano` | El ciclo anterior se busca por año, no por `id - 1`. |
| `turnos` | `turno`, `nturno` | Ventana de egresados por nombre (no por id 2/3/4). |
| `ento` | `examTodosInscri` | `T` = inscribir a todos los regulares. |

## Flujo principal

1. Elegir turno de examen y año lectivo; confirmar.
2. Recorrer `apro = 1` del nivel.
3. EQ/TM: omitir. Regular: PR. Egresado + feb/abr/jul/sep del año siguiente: condición de ventana (`RE` default, `PR` si el tenant lo configura) en todas las deudas. Resto: PR.

## Fuente de verdad

`MateriasAdeudadasCondicionRecalculo`. Códigos canónicos: `MateriasAdeudadasFiltros::CONDICIONES` (`PR`, `RE`, `EQ`, `TM`).

## Archivos clave

- `app/Support/Examenes/MateriasAdeudadasCondicionRecalculo.php`
- `app/Support/Examenes/MateriasAdeudadasFiltros.php`
- `app/Livewire/Examenes/Concerns/PreparaMateriasAdeudadasExamenes.php`
- `app/Support/helpers.php` (`tenantExamenesEgresadosVentanaCondicion`)
- `config/tenant.php` (`examenes.egresados_ventana_condicion`)
- `config/tenants/iess.php` / `config/tenants/caixalsf.php` (`PR`)
- `tests/Unit/MateriasAdeudadasCondicionRecalculoTest.php`

## Qué no hacer / reglas de negocio

- No pisar EQ/TM.
- No borrar `inscri` de no regulares (mesas cargadas a mano). En la ventana feb–sep sí se puede **poner** `inscri = 1` si `examTodosInscri = T` (no se pone 0).
- Actas volantes: no exigir `calificaciones.idMatPlan`; resolver el plan como listado/permiso (`materias.idMatPlan` o, si falta, `calificaciones.idMatPlan`). Si no hay plan, agrupar por `idMaterias`. Detalle: [examenes-actas-volantes.md](examenes-actas-volantes.md).
- No usar `idTerlec - 1` ni IDs fijos de turno.
- No aplicar la rama de egresados en primario (sexto grado) ni en adultos.

## Checklist al modificar

- [ ] ¿EQ/TM siguen intactos?
- [ ] ¿Regulares del ciclo actual siguen en PR?
- [ ] ¿Egresados: ventana hasta septiembre del año siguiente (`RE` default / `PR` por tenant) y PR desde diciembre?
- [ ] ¿El ciclo anterior se resuelve por `terlec.ano`?
- [ ] ¿`RE` está en filtros, inscripción, notas y etiquetas de acta?
- [ ] ¿Las deudas de cursos anteriores (p. ej. Matemática 4.º) de un egresado de 6.º quedan en la condición de ventana y salen en el acta correspondiente de esa materia?
