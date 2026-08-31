# Módulo: Cierre anual (secundario)

## Propósito

Pasar al **libro matriz** las materias aprobadas del ciclo lectivo activo (diciembre) y, en febrero, las que faltaban más marcar el resto como **previa**. Cada ejecución queda registrada en un **lote** con snapshot antes/después, para reconocerlo después y revertirlo si hace falta.

## Modalidades / variantes

- **Diciembre:** solo aprobadas (promedio anual ≥ 7 o coloquio dic ≥ 7) → `apro = 2` y datos de matriz (`calif`, `mes = 12`, `ano`, `cond`, `escuapro`).
- **Febrero:** aprobadas (promedio, dic o feb ≥ 7) → matriz (`mes = 2`, limpia `condAdeuda` / `inscri`); el resto → previa (`apro = 1`, `condAdeuda = PR`, `inscri = 0`). Re-ejecutar no duplica previas ya marcadas; una previa que ahora aprueba pasa al matriz.

## Actores y permisos

- Menú de Secretaría, grupo **CALIFICACIONES (Secundario)**.
- Permiso **15** (`PermisosIaCatalog::CALIF_CIERRE_ANUAL`): historial y pasaje al matriz (Dic / Feb).
- Permiso **100** (`PermisosIaCatalog::CALIF_CIERRE_ANUAL_LOTES`): botón **Lotes**, informe persistido y **Revertir este lote**. Quien no lo tiene no ve el botón ni entra a `/cierre-anual/lotes`.
- Contexto de nivel cuyo nombre contiene «secundari».
- No está en el Menú de Docentes.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `calificaciones` | `apro`, `calif`, `mes`, `ano`, `cond`, `escuapro`, `condAdeuda`, `inscri` | Lo que el cierre **escribe**. Tabla legacy: no se le agregan columnas. |
| `cierre_anual_lotes` | operación, contexto, conteos, `estado`, actor, fechas de cierre/reversión | Una fila por «Confirmar». |
| `cierre_anual_lote_filas` | identidad + snapshot `_antes` / `_despues` de los 8 campos + `tipo` (`matriz`/`previa`) | Solo filas **actualizadas**. |

Estados del lote: `aplicado`, `revertido_parcial`, `revertido`.

## Flujo principal

1. Verificar calificaciones completas del ciclo activo.
2. Confirmar Dic o Feb. Si faltan las tablas de journal, el cierre **no corre**.
3. Se crea el lote, se actualizan calificaciones y se guardan snapshots en la misma transacción.
4. Informe en pantalla (persistido: **Lotes** → Ver).
5. Historial del alumno: columna **Cierre** con chip del lote (no se infiere por `mes`/`escuapro`).
6. **Revertir este lote:** restaura `_antes` solo si la fila sigue igual a `_despues`. Las editadas después (libro matriz, mesa) se omiten.

## Fuente de verdad

- Resultado pedagógico: `calificaciones` (igual que el legado).
- Qué tocó *esta* corrida y cómo deshacerla: `cierre_anual_lotes` + `cierre_anual_lote_filas`.
- Criterio de aprobación: `CierreAnualSecundario::estaAprobadaEnDiciembre` / `estaAprobadaEnFebrero`.

## Archivos clave

- `app/Support/CalificacionesSecundario/CierreAnualSecundario.php`
- `app/Support/CalificacionesSecundario/CierreAnualJournal.php`
- `app/Livewire/CalificacionesSecundario/CierreAnualIndex.php`
- `app/Livewire/CalificacionesSecundario/CierreAnualLotes.php`
- `app/Livewire/CalificacionesSecundario/CierreAnualHistorial.php`
- `database/sql/cierre_anual_lotes.sql`
- `database/sql/permiso_ia_orden_100_cierre_anual_lotes.sql`

## Qué no hacer / reglas de negocio

- No inferir «lo hizo el cierre» por `mes = 12/2` o `escuapro` lleno.
- No revertir a ciegas: si el valor actual ≠ snapshot después, no tocar.
- Revertir el lote **más reciente** primero si hay varios en el ciclo.
- No calcular promedios aquí; se leen `calif` / coloquios.
- No ejecutar el cierre si no existen las tablas de journal (error visible).

## Checklist al modificar

- [ ] ¿El `UPDATE` de `calificaciones` y el insert del detalle van en la misma transacción?
- [ ] ¿El snapshot cubre los 8 campos, también los que esa operación no cambia?
- [ ] ¿La reversión compara actual vs `_despues` antes de escribir `_antes`?
- [ ] ¿Listados filtrados por `schoolCtx()` (`id_nivel` + `id_terlec`)?
- [ ] ¿SQL / migración aditiva, sin alterar columnas legacy?
- [ ] ¿Lotes y reversión siguen detrás del permiso 100 (botón, ruta, `revertirLote`)?
