# Módulo: Gestión de Morosos

## Propósito

Filtrar familias/estudiantes con cuotas vencidas al 2.º vencimiento y generar el PDF de **listado de deuda** o la **notificación de deuda**. Menú de Administración → Gestión de mora.

## Modalidades / variantes

Ninguna por tenant. El alcance de nivel sigue `SchoolAlcancePedagogico` (el módulo solo se habilita en Administración, que ve todos los niveles pedagógicos).

## Actores y permisos

Menú de Administración (`layouts/administracion`). Permiso `permisos_ia` orden **64** (`PermisosIaCatalog::ADMIN_MORA_GESTION_MOROSOS`). Gate: `PermisosMora::puedeGestionMorosos()`.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|-------|
| `cuotasgeneradas` | `faltapa`, `importe`, `venc1`, `venc2`, `idTerlec`, `idCursos`, `idCuotas`, `idCuotasbecas`, `idLegajos` | Solo saldo > 0 e importe > 0. Mora = `venc2` anterior a la fecha de cálculo. |
| `cursos` | `idNivel` | El filtro **Nivel** usa el nivel del curso de la cuota (cualquier ciclo). |
| `legajos` / `familias` / `matricula` | familia, estudiante, fuera de colegio | «Fuera de colegio» mira matrícula del ciclo activo. |
| `cuotas` / `cuotasbecas` / `terlec` | plantilla, beca, año de la cuota | |

## Flujo principal

1. Fecha de cálculo (intereses y total a pagar en el PDF).
2. Activar cada filtro opcional con su casilla (**nivel**, familia, estudiante, vencimientos, excluir cuotas, cursos, cantidad de cuotas, fuera de colegio, año lectivo, becados).
3. Generar **Listado de Deuda** o **Notificación de Deuda**. Si no hay registros: aviso SweetAlert, no se abre el PDF.

Sin filtros opcionales: familias con cuotas vencidas al 2.º vencimiento y saldo > 0.

## Filtro por nivel

- Casilla **Nivel** + selector (Inicial, Primario, Secundario, …).
- Si está activo, hay que elegir un nivel válido del alcance.
- Aplica a `cursos.idNivel` de la cuota generada (no solo al ciclo activo).
- Con nivel activo, el selector de **Cursos (ciclo activo)** se acota a ese nivel; si cambia el nivel, se quitan cursos incompatibles.

## Fuente de verdad

Consulta: `GestionMorososConsulta` + `GestionMorososFiltros::aplicarAConsulta`. PDFs: `ListadoMorososDatos` / `NotificacionDeudaDatos` (TCPDF). Pedido en caché: `GestionMorososPdfPedido` (token opaco en la URL).

## Archivos clave

- `app/Livewire/Mora/GestionMorososIndex.php`
- `app/Support/Mora/GestionMorososFiltros.php`
- `app/Support/Mora/GestionMorososConsulta.php`
- `resources/views/livewire/mora/gestion-morosos-index.blade.php`
- PDF listado: `ListadoMorososPdfController` + `ListadoMorososTcpdf`
- PDF notificación: `NotificacionDeudaPdfController`

## Qué no hacer / reglas de negocio

- No filtrar mora por `venc2` como si fuera el rango «1º venc.» (ese rango es `venc1`).
- No calcular promedios (módulo de cuotas).
- URLs de PDF con `{ref}` opaco, no IDs de familia/legajo.

## Checklist al modificar

- [ ] Casilla marcada exige valor válido (nivel, cursos, fechas, etc.).
- [ ] Filtro nivel valida IDs contra `nivelesParaSelector()` y aplica `whereHas('curso', idNivel)`.
- [ ] Cursos del selector respetan el nivel elegido.
- [ ] Listado y notificación usan la misma consulta de filtros.
