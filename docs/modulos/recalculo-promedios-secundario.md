# Módulo: Recalcular promedios (secundario)

## Propósito

Completar `calificaciones.calif` (promedio final) **después de importar CIDI**, cuando el CSV trae `ic01`…`ic28` pero el promedio oficial de CIDI todavía no viene (hasta su cierre anual). Usa **la misma fórmula** que la carga manual de notas.

## Modalidades / variantes

- Solo **carga estándar** (Eval 1..8 + JIS 1..2). La variante EPQ no calcula promedio anual automático: el ítem no se muestra.

## Actores y permisos

- Menú de Secretaría, grupo **CALIFICACIONES (Secundario)** (`niveles.id` = 3).
- Visible si el usuario tiene permiso **94** (`PermisosIaCatalog::CALIF_RECALCULO_PROMEDIOS`).
- En el sidebar: encima de **Gestión de Solicitudes de Evaluación**.
- No está en el Menú de Docentes.

## Tablas y campos críticos

- `calificaciones`: lee `ic01`…`ic28`, `dic`, `feb`; escribe solo `calif`.
- Filtro: `calificaciones.idTerlec` + cursos del contexto (`cursos.idNivel`, `cursos.idTerlec`).

## Flujo principal

1. Tras **Descargar calificaciones desde CIDI**, abrir **Recalcular promedios**.
2. Confirmar (SweetAlert). El proceso recorre todas las filas del ciclo/nivel.
3. Informe: procesados, actualizados, sin cambio, omitidos por coloquio, errores.

## Fuente de verdad

- Fórmula: `PromedioAnualCalificacionesSecundario::calcular()` vía `RecalculoPromedioAnualSecundario::califDesdeFilaModulos()`.
- La carga celda a celda y coloquios (si Dic/Feb no aprueban) usan el mismo helper.
- Boletines, planillas, consulta e importación CIDI **no** recalculan: leen `calif`.

## Archivos clave

- `app/Support/CalificacionesSecundario/RecalculoPromedioAnualSecundario.php`
- `app/Livewire/CalificacionesSecundario/RecalculoPromediosSecundario.php`
- `resources/views/livewire/calificaciones-secundario/recalculo-promedios-secundario.blade.php`
- `app/Support/PromedioAnualCalificacionesSecundario.php`

## Qué no hacer / reglas de negocio

- No duplicar la fórmula en Blade, importadores ni PDF.
- No pisar `calif` si Dic o Feb están aprobados (≥ 7): ese valor lo escribe carga de coloquios.
- No ejecutar el recálculo masivo en tenants EPQ.
- Si hay módulo con nota desaprobado, `calif` queda vacío (igual que en carga).

## Checklist al modificar

- [ ] ¿Sigue siendo el único puente hacia `calcular()`?
- [ ] ¿El filtro sigue usando `schoolCtx()` (nivel + ciclo)?
- [ ] ¿Se actualizó `docs/05` §7 si hay un caller nuevo?
