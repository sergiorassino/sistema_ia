# Módulo: IPE (Informe de Progreso Escolar) — primario

## Propósito

PDF del boletín / informe de progreso escolar del nivel primario, con selector de etapa o boletín único según la implementación del tenant.

## Modalidades / variantes

Clave: `tenant.boletin_primario.ipe_implementacion` (via `tenantBoletinPrimarioIpeImplementacion()`).

| Clave | Layout | Etapa | Notas |
|-------|--------|-------|-------|
| `estandar` | A4 vertical | 1ª / 2ª | Layout base NSSC |
| `sanjose` | A4 apaisado | Única | Matriz por columnas |
| `montecristo` | A4 apaisado | 1ª / 2ª | Síntesis + extracurriculares |
| `caixalsf` | A4 vertical | 1ª / 2ª | Ciclo en subtítulo; 16 materias fijas; inasistencias desde `matricula` |

Despacho: `BoletinIpePrimarioGenerador`.

## Actores y permisos

- Secretaría: autenticado con contexto escolar; **no** requiere permiso IA 71 (carga). Rutas `calificacionesPrimario.boletinIpe*`.
- Docentes: portal docente si el tenant habilita el ítem (sin `permisos_ia`).
- Autogestión familia: `autogestion.boletin_ipe_primario.habilitado` + nivel primario.

## Tablas y campos críticos

- `matricula`: `obs1`, `obs2`, `obsAnual`; en `caixalsf` también `just1`/`just2`/`inju1`/`inju2`.
- `calificaciones`: `ic01`, `ic02`, `ic03` (+ campos extra en otras variantes).
- `materias.ord`, `cursos.c` (ciclo 1–6 → PRIMER/SEGUNDO/TERCER CICLO en `caixalsf`).

## Flujo principal

1. UI `BoletinIpeIndex` (curso + alumnos + etapa si aplica).
2. Controllers PDF individual/lote → `BoletinIpePrimarioGenerador`.
3. Portal familia: `BoletinIpePrimarioPdfController` + `PortalFamiliaBoletinIpe`.

## Archivos clave

- Generador: `app/Support/CalificacionesPrimario/BoletinIpePrimarioGenerador.php`
- Caixal SF: `BoletinIpeCaixalsfDatos.php`, `BoletinIpeCaixalsfTcpdf.php`
- Tenant: `config/tenants/caixalsf.php` → `ipe_implementacion = caixalsf`

## Qué no hacer

- No calcular promedios en el PDF (solo leer notas guardadas).
- No ramificar por `tenantSlug()`; usar `ipe_implementacion`.
- PDFs nuevos solo TCPDF + Arial (`TcpdfFuenteArial`); párrafos justificados con `TcpdfMultiCellJustificado`.
- En autogestión familia, `ento.verNotasOff` del nivel primario debe impedir el PDF (`BoletinIpePrimarioPdfController`) y mostrar `verOffMensaje`.

## Checklist al modificar

- [ ] Actualizar match en `BoletinIpePrimarioGenerador` y `usaSelectorEtapa` / `usaBoletinUnico`.
- [ ] Activar clave en `config/tenants/{slug}.php`.
- [ ] Probar etapa 1 y 2 (o boletín único) en secretaría y, si aplica, portal familia.
- [ ] Con `verNotasOff` en primario, el portal familia no abre el IPE (aviso + PDF 403).
