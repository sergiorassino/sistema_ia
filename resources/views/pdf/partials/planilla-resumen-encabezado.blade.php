{{-- Encabezado institucional + títulos de la planilla resumen (por curso en PDF multi-curso). --}}
@include('pdf.partials.header', ['header' => $pdfHeader ?? null])

<p class="titulo">
    Planilla resumen
    @if (! empty($ano))
        — {{ $ano }}
    @endif
    — calificaciones parciales
</p>
<p class="subtitulo">Ambas etapas · Mejor nota por módulo (incluye recuperatorios)</p>
@if (trim((string) ($cursoLabel ?? '')) !== '')
    <p class="meta-curso">{{ $cursoLabel }}</p>
@endif
<p class="leyenda">
    Líneas 1 y 2: módulos 1 a 8. Línea 3: JIS 1, JIS 2 y promedio anual. Línea 4: coloquios dic. y feb.
    Fondo gris: módulo aprobado con recuperatorio. Texto rojo: mejor nota inferior a 7.
    Línea 5: Nº Rep., Inas., Amon., Ed.Fi. (inas. a educación física), Prom.Gral. (solo si hay promedio en todas las materias), Previas.
</p>
