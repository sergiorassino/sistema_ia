# Módulo: Boletines (secundario) — Informe de progreso escolar

## Propósito

Impresión del **Informe de Progreso Escolar** del nivel secundario (grilla Eval 1–8, JIS, coloquios y opcionalmente Prom. Final). PDF oficial con firmas, sin marca de agua.

Si el tenant activa el boletín EPQ (`CalificacionesSecundarioModulos::BOLETIN`), la pantalla redirige a ese módulo.

## Modalidades / variantes

- Layout único: `BoletinConsultaCalificacionesTcpdf` (A4 apaisado).
- Opción de impresión: **Mostrar promedios** / **No mostrar promedios** — solo oculta o muestra la columna «Prom. Final» en el PDF; **no calcula ni borra** `calificaciones.calif`.

La misma clase TCPDF sirve a la **consulta de calificaciones** (marca de agua, sin firmas) y al portal familia; ahí el promedio se muestra siempre (default `mostrarPromedios = true`).

## Actores y permisos

- Secretaría / staff: rutas `boletinesSecundario.*` con auth y contexto escolar.

## Tablas y campos críticos

- Lectura vía `ConsultaCalificacionesAlumno` (`calificaciones.ic*`, `dic`, `feb`, `calif`, etc.).
- No escribe en BD.

## Flujo principal

1. UI `BoletinesSecundarioIndex`: curso + alumnos + selector de promedios.
2. POST individual (`BoletinSecundarioPdfController`) o lote (`BoletinSecundarioLotePdfController`) con `mostrar_promedios` (0/1).
3. TCPDF dibuja o omite la columna índice 13 («Prom. Final»).

## Fuente de verdad

- Promedio mostrado: valor ya persistido en `calificaciones.calif` (no se recalcula en el PDF).

## Archivos clave

- Livewire: `app/Livewire/BoletinesSecundario/BoletinesSecundarioIndex.php`
- Vista: `resources/views/livewire/boletines-secundario/index.blade.php`
- PDF: `app/Support/BoletinesSecundario/BoletinConsultaCalificacionesTcpdf.php`
- Controllers: `BoletinSecundarioPdfController`, `BoletinSecundarioLotePdfController`

## Qué no hacer / reglas de negocio

- No calcular promedios en el PDF.
- No borrar ni alterar `calif` al elegir «No mostrar promedios».
- No aplicar el ocultamiento a consulta de calificaciones ni a boletines de otros niveles (IPE, EPQ primario, etc.) salvo pedido explícito.

## Checklist al modificar

- [ ] Individual y lote reciben el mismo flag `mostrar_promedios`.
- [ ] Con «No mostrar», la grilla no dibuja encabezado ni celdas de Prom. Final.
- [ ] Consulta / familia siguen mostrando promedio por defecto.
