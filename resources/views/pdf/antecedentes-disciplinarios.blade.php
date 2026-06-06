<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 15mm 12mm 15mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; line-height: 1.25; }
        .titulo { font-weight: 700; text-align: center; text-transform: uppercase; margin: 2px 0 8px 0; font-size: 10.5pt; }
        .titulo-fecha { font-weight: 400; text-transform: none; }
        .alumno { font-weight: 700; text-transform: uppercase; margin: 0; }
        .curso { font-weight: 700; text-transform: uppercase; margin: 0; }
        .stamp { margin: 6px 0 10px 0; font-size: 9pt; }
        .evento { margin-top: 10px; font-size: 8pt; font-weight: 400; }
        .fecha { font-family: DejaVu Sans, sans-serif; font-size: 8pt; margin: 0; font-weight: 400; }
        .tipo { margin: 0; font-weight: 400; }
        .meta { margin: 0; font-weight: 400; }
        .motivo { margin: 0; font-weight: 400; }
        .sep { border-top: 1px solid #999; margin: 8px 0; width: 100%; }
    </style>
</head>
<body>
    @include('pdf.partials.header', ['header' => $pdfHeader ?? null])
    <p class="titulo">ANTECEDENTES DISCIPLINARIOS <span class="titulo-fecha">(al {{ $emitidoAl ?? '' }})</span></p>

    <p class="alumno">{{ $alumnoLinea }}@if(trim($dni) !== '') D.N.I. {{ $dni }}@endif</p>
    @if (trim($cursoLabel) !== '')
        <p class="curso">{{ $cursoLabel }}</p>
    @endif
    {{-- El modelo muestra un timestamp debajo, pero por pedido se informa la fecha en el título. --}}

    @forelse ($sanciones as $s)
        <div class="evento">
            <p class="fecha">{{ $s->fecha?->format('d/m/Y') ?? '—' }}</p>
            <p class="tipo">{{ $s->tipo?->tipo ?? ('#'.$s->idTipoSancion) }}</p>
            <p class="meta">
                Solicitada por: {{ $s->solipor ?: ($s->profesor?->nombre_completo ?? '—') }}
            </p>
            <p class="motivo">{{ $s->motivo ?? '—' }}</p>
        </div>
        <div class="sep"></div>
    @empty
        <p>No hay registros disciplinarios.</p>
    @endforelse
</body>
</html>

