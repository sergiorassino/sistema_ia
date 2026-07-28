# Módulo: Estadística de rendimiento escolar

## Propósito

Consultas de **aprobación y promoción** en nivel secundario (medio): cuántas calificaciones / materias / estudiantes aprobaron **durante el año**, en **Diciembre**, en **Febrero** o quedaron **pendientes**.

Tres vistas operativas (más un índice) leen `calificaciones` del ciclo activo en `schoolCtx()`; no modifican datos.

No hay PDF ni portal de Alumnos/Docentes para este módulo.

## Modalidades / variantes

| Vista | Ruta | Filtro mínimo para calcular | Salida principal |
|-------|------|----------------------------|------------------|
| **Índice** | `estadistica.rendimiento` | — (solo enlaces) | Cards a las tres vistas |
| **Por materias** | `estadistica.rendimiento.porMateria` | **Por materia y curso** *o* **Por curso** | Resumen, tabla por materia/curso, gráfico de barras apiladas, torta |
| **Por docente** | `estadistica.rendimiento.porDocente` | **Por docente** | Tablas por docente/materia, gráfico comparativo |
| **Por estudiante** | `estadistica.rendimiento.porEstudiante` | **Por curso** *o* **Por estudiante** | Resumen promoción, resumen materias, tabla con inasistencias/previas/boletín, gráficos |

**Carga diferida:** al entrar a cada vista los filtros arrancan vacíos (`— Elegir —`). **No se consulta la BD** hasta que el usuario elija al menos un filtro de la fila correspondiente. «Limpiar» vuelve al estado vacío.

El año lectivo mostrado es el de **contexto de sesión** (`schoolCtx()->idTerlec`); no es editable en pantalla.

## Actores y permisos

| Requisito | Detalle |
|-----------|---------|
| Menú | **Menú de Secretaría** (`layouts/app`), grupo sidebar **ESTADÍSTICAS** |
| Permiso | `PermisosIaCatalog::ESTADISTICA_RENDIMIENTO_ESCOLAR` — **orden 65** |
| Nivel de sesión | `NivelSistema::esSecundario(schoolCtx()->idNivel)` — solo `niveles.id = 3` |
| Visibilidad menú | `MenuSecretariaPerfil::muestraEstadisticas()` (equivale a mostrar calificaciones secundario) |
| Rutas | Middleware `permiso:65` + trait `RequiresPermisoEstadisticaRendimiento` en cada componente Livewire |

Sin permiso, nivel distinto de secundario o ciclo sin `idTerlec`: **403** o mensaje de contexto vacío.

## Tablas y campos críticos

Todas las consultas filtran por **`idTerlec`** del contexto y **`matricula.idCondiciones = 1`**.

| Tabla | Uso en el módulo |
|-------|------------------|
| `calificaciones` | Fuente de notas: `ic01`…`ic28`, `dic`, `feb`; flag `tea` (vista estudiante) |
| `matricula` | Enlace alumno–curso–ciclo; `idNivel = 3` |
| `materias` | Nombre y orden; `idNivel = 3`, `idTerlec` del ciclo |
| `cursos` | `cursec`, `orden` |
| `legajos` | Apellido y nombre (vista estudiante) |
| `profesores` + `ppc` | Docente por materia (vista docente) |
| `inasistencias` | Solo vista estudiante: `SUM(cantidad)` por matrícula, `tipo <> 5` (sin educación física) |
| `terlec` | Etiqueta del año en UI |

**Alcance de alumnos en selectores:** matriculados en el ciclo, nivel secundario, condición regular (`idCondiciones = 1`).

## Reglas de aprobación (fuente de verdad)

Implementación: `App\Support\Estadistica\AprobacionEstadistica`.

### Nota mínima

- Aprobado numérico: **≥ 7** (`NOTA_MINIMA`).

### Durante el año

- Los campos `ic01`…`ic28` se agrupan en **bloques** (núcleos + JIS); ver constante `BLOQUES_IC` en el servicio.
- Por cada bloque con al menos una nota cargada: debe haber **alguna nota ≥ 7** en ese bloque.
- Si no hay ninguna nota en `ic*` → no cuenta como aprobado durante el año.

### Diciembre / Febrero

- `dic` ≥ 7 → aprobado en diciembre.
- `feb` ≥ 7 → aprobado en febrero.

### Pendiente

- Materia sin aprobar durante el año, sin `dic` aprobatorio ni `feb` aprobatorio.

### Contadores del resumen

Los totales **Año / Dic / Feb / Pend** en cards y tablas **no son excluyentes entre sí**: una misma materia puede sumar en «durante el año» y también en «diciembre» si cumple ambas condiciones. «Pendiente» solo suma cuando **ninguna** de las tres vías aplica.

### Promoción anual (solo vista estudiante)

Por cada alumno se cuenta la **vía efectiva** de cada materia (prioridad: año → dic → feb → pendiente) y luego:

| Resultado | Condición |
|-----------|-----------|
| `promovido_anio` | Todas las materias aprobadas durante el año |
| `promovido_dic` | Sin pendientes; al menos una materia vía dic; ninguna vía feb |
| `promovido_feb` | Sin pendientes; al menos una materia vía feb |
| `no_promovido` | Alguna materia pendiente |

### Destacados en tabla por estudiante

| Señal | Regla |
|-------|--------|
| Nombre en **rojo** | 3 o más materias sin aprobar durante el año (excluye «sin nota» del conteo de riesgo) |
| Nombre en **ámbar** | TEA (`calificaciones.tea = 1`) **o** ≥ 25 inasistencias de clase |
| **(Rep: N)** | Materias pendientes menos las «sin nota» |
| **(Sin nota: N)** | Materias sin ninguna nota en `ic*`, `dic` ni `feb` |
| Columna **Inas** en rojo | > 10 inasistencias |
| Celda **Boletín** rosa | Tiene previas (`calificaciones.apro = 1` en otro `idTerlec`) |
| Botón boletín | POST a `boletinesSecundario.pdf` con `idMatricula` |

### Inasistencias (vista estudiante)

- **`SUM(inasistencias.cantidad)`**, no `COUNT(*)` de filas: en varios tenants hay **una fila resumen por tipo** de inasistencia.
- Excluir **`tipo = 5`** (educación física), alineado con informes y certificados.

## Flujo principal

1. Usuario con permiso 65 entra al **Menú de Secretaría** → **ESTADÍSTICAS** → **Estadística de Rendimiento Escolar**.
2. Elige una de las tres vistas.
3. Selecciona filtro(s); Livewire recalcula (`wire:model.live`).
4. Se muestran cards de resumen, tabla ordenable (`data-se-tabla-ordenable`) y gráficos Chart.js.
5. «Limpiar» resetea filtros y vacía resultados (sin consulta pesada).

### Filtros — por materias

| Control | Propiedad Livewire | Efecto |
|---------|-------------------|--------|
| Por materia y curso | `materiaCurso` (`idMaterias-idCursos`) | Filtra esa materia en ese curso; tabla con una o pocas filas |
| Por curso | `cursoId` | Todas las materias del curso |
| Ambos | Ambos distintos de cero | Prevalece curso del desplegable «Por curso» si está cargado; materia del combo si aplica |

Cálculo solo si `cursoId > 0` **o** se eligió materia en el primer combo.

### Filtros — por estudiante

| Control | Propiedad | Efecto |
|---------|-----------|--------|
| Por curso | `cursoId` | Todos los alumnos del curso |
| Por estudiante | `legajoId` | Un alumno |

Cálculo solo si alguno de los dos > 0.

### Filtros — por docente

| Control | Propiedad | Efecto |
|---------|-----------|--------|
| Por docente | `profesorId` | Materias asignadas vía `ppc` |

Cálculo solo si `profesorId > 0`.

## Gráficos

- **Chart.js 4** + **chartjs-plugin-datalabels**, empaquetados en Vite (`resources/js/estadistica-charts.js`, importado desde `app.js`).
- Datos en `<textarea hidden data-se-estad-chart-json>` por canvas (evita corrupción del JSON en morph de Livewire).
- Inicialización: `MutationObserver` + hooks `livewire:initialized`, `morph.updated`, `commit`.
- Tipos: barras horizontales apiladas (% por fila) y tortas/donas de resumen.
- CSS: paneles `[data-se-estad-chart-panel]` con `opacity` hasta clase `se-estad-chart-panel--ready` (`resources/css/app.css`).

**Requisito de despliegue:** `npm run build` y subir `public/build/`; no depender de CDN externo.

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Índice | `app/Livewire/Estadistica/RendimientoEscolarIndex.php` |
| Por materia | `app/Livewire/Estadistica/PorMateria.php` |
| Por docente | `app/Livewire/Estadistica/PorDocente.php` |
| Por estudiante | `app/Livewire/Estadistica/PorEstudiante.php` |
| Autorización / contexto | `app/Livewire/Estadistica/Concerns/RequiresPermisoEstadisticaRendimiento.php` |
| Lógica de aprobación | `app/Support/Estadistica/AprobacionEstadistica.php` |
| Consultas auxiliares (cursos, inasistencias, previas, %) | `app/Support/Estadistica/EstadisticaRendimientoConsulta.php` |
| Vistas | `resources/views/livewire/estadistica/*.blade.php` |
| Gráficos JS | `resources/js/estadistica-charts.js` |
| Canvas partial | `resources/views/livewire/estadistica/partials/chart-canvas.blade.php` |
| Sidebar | `resources/views/layouts/partials/sidebar-grupo-estadisticas.blade.php` |
| Rutas | `routes/web.php` (`estadistica.rendimiento*`) |
| Permiso catálogo | `PermisosIaCatalog::ESTADISTICA_RENDIMIENTO_ESCOLAR` (65) |
| SQL / migración permiso | `database/sql/permiso_ia_orden_65_estadistica_rendimiento.sql`, migración homónima |

## Qué no hacer / reglas de negocio

1. **No calcular al entrar sin filtros** — evita recorrer todo el colegio (~cientos de alumnos × materias).
2. **No usar `COUNT` de filas de `inasistencias`** para totales; usar **`SUM(cantidad)`** y excluir tipo 5.
3. **No calcular promedios** de calificaciones aquí; solo conteos de aprobación por vía (regla global de promedios en `docs/05-preferencias-y-convenciones.md` §7).
4. **No mostrar el módulo fuera de secundario** ni sin permiso 65.
5. **No mezclar niveles:** consultas fijan `idNivel = 3` en matrícula/materias.
6. **No incluir matrículas** con `idCondiciones <> 1`.
7. Vista docente: unión con `ppc` — si un docente no tiene materias asignadas en PPC, no aparece.
8. Los gráficos **no** deben depender de scripts inline por vista; mantener el init centralizado en `estadistica-charts.js`.

## Checklist al modificar

- [ ] ¿Sigue exigiendo filtro activo antes de consultar?
- [ ] ¿`AprobacionEstadistica` sigue alineado con criterio de boletines / carga secundario (bloques `ic*`, nota 7)?
- [ ] ¿Inasistencias con `SUM(cantidad)` y `tipo <> 5`?
- [ ] ¿Filtros y listados respetan `schoolCtx()->idTerlec` e `idNivel` secundario?
- [ ] ¿Permiso 65 en ruta **y** en `autorizarEstadisticaRendimiento()`?
- [ ] ¿Gráficos probados tras morph Livewire (cambio de filtro) con assets Vite compilados?
- [ ] ¿Tablas anchas con scroll horizontal `justify-start` (no centrar bajo sidebar)?
- [ ] ¿Sin PDF nuevo salvo pedido explícito (y entonces TCPDF)?
