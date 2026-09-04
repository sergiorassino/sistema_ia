# Módulo: Boletines (secundario) — Informe de progreso escolar

## Propósito

Impresión del **Informe de Progreso Escolar** del nivel secundario (grilla Eval 1–8, JIS, coloquios y Calific. Final). PDF oficial con firmas (Preceptor/a y Directivo), sin marca de agua.

Si el tenant activa el boletín EPQ (`CalificacionesSecundarioModulos::BOLETIN`), la pantalla redirige a ese módulo.

## Modalidades / variantes

- Layout único: `BoletinConsultaCalificacionesTcpdf` (A4 apaisado).
- Opción de impresión: **Mostrar promedios** / **No mostrar promedios** (`mostrarPromedios` = 1|0 en Livewire; no usar `bool` con `<select value="0">`) — la columna «Calific. Final» **siempre se dibuja**; con «No mostrar» la celda queda vacía. **No calcula ni borra** `calificaciones.calif`.

La misma clase TCPDF sirve a la **consulta de calificaciones** (Secretaría y portal familia: marca «SIN VALOR LEGAL», **sin firmas**) con `mostrarPromedios = true` siempre.

Textos compartidos con el boletín (también en consulta): línea `Apellido y Nombre: …    D.N.I.: …    Curso: …` y encabezado de columna **Calific. Final**. Las firmas (Preceptor/a y Directivo) **no** se dibujan en consulta.

## Actores y permisos

- Secretaría / staff: rutas `boletinesSecundario.*` (o EPQ `calificacionesSecundarioEpq.boletin*`) con auth y contexto escolar. **No** requieren permiso IA 71 (carga).

## Tablas y campos críticos

- Lectura vía `ConsultaCalificacionesAlumno` (`calificaciones.ic*`, `dic`, `feb`, `calif`, etc.).
- No escribe en BD.

## Flujo principal

1. UI `BoletinesSecundarioIndex`: curso + alumnos + selector de promedios.
2. POST individual (`BoletinSecundarioPdfController`) o lote (`BoletinSecundarioLotePdfController`) con `mostrar_promedios` (0/1).
3. TCPDF siempre dibuja la columna índice 13 («Calific. Final»); si `mostrar_promedios` es 0, la celda va vacía.

## Fuente de verdad

- Promedio mostrado: valor ya persistido en `calificaciones.calif` (no se recalcula en el PDF).

## Archivos clave

- Livewire: `app/Livewire/BoletinesSecundario/BoletinesSecundarioIndex.php`
- Vista: `resources/views/livewire/boletines-secundario/index.blade.php`
- PDF: `app/Support/BoletinesSecundario/BoletinConsultaCalificacionesTcpdf.php`
- Controllers: `BoletinSecundarioPdfController`, `BoletinSecundarioLotePdfController`
- Consulta (misma plantilla, sin firmas): `ConsultaCalificacionesSecundarioPdfController`, `Alumnos\CalificacionesController`
- Autogestión EPQ: `PortalFamiliaBoletinEpqSecundario`, `BoletinEpqSecundarioFamiliaPdfController`

## Qué no hacer / reglas de negocio

- El listado de alumnos del curso (pantalla y PDF en lote) se ordena con `OrdenAlfabeticoEstudiante` (collation española). No volver a `sortBy`/`<=>` sobre apellido en crudo: las tildes (Cáceres) quedarían fuera de lugar.
- No calcular promedios en el PDF.
- No borrar ni alterar `calif` al elegir «No mostrar promedios».
- No aplicar el ocultamiento a consulta de calificaciones ni a boletines de otros niveles (IPE, EPQ primario, etc.) salvo pedido explícito.
- En autogestión familia, `ento.verNotasOff` del nivel del estudiante debe impedir el PDF de consulta (estándar y EPQ) y mostrar `verOffMensaje`. El controlador tiene que devolver 403.

## Checklist al modificar

- [ ] Individual y lote reciben el mismo flag `mostrar_promedios`.
- [ ] Con «No mostrar», la grilla dibuja encabezado y celdas de Calific. Final **sin** el valor.
- [ ] Consulta / familia siguen mostrando promedio por defecto.
- [ ] Consulta muestra las mismas etiquetas de alumno y «Calific. Final» que el boletín, **sin** bloque de firmas.
- [ ] Con `verNotasOff` en el nivel secundario, el portal familia no abre la consulta (estándar ni EPQ): aviso + PDF 403.
- [ ] El listado del curso (y el lote) ordena con `OrdenAlfabeticoEstudiante` (Cáceres con las C).
