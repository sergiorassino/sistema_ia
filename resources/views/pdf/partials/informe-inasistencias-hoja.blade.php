@php
    $ano = $ano ?? now()->year;
    $alumnoLinea = $alumnoLinea ?? '';
    $dni = $dni ?? '';
    $cursoLabel = $cursoLabel ?? '';
    $fechaDesde = $fechaDesde ?? '';
    $fechaHasta = $fechaHasta ?? '';
    $filtroFechasActivo = $filtroFechasActivo ?? false;
    $inasistencias = $inasistencias ?? collect();
    $totalesCatalogo = $totalesCatalogo ?? [];
@endphp
<div class="cabecera-informe">
    @include('pdf.partials.header', ['header' => $pdfHeader ?? null])
    <p class="titulo-informe">Informe de inasistencias — {{ $ano }}</p>
    <p class="alumno">
        {{ $alumnoLinea }}@if($dni !== '') — {{ $dni }}@endif
    </p>
    @if ($cursoLabel !== '')
        <p class="curso">{{ $cursoLabel }}</p>
    @endif
    @if ($filtroFechasActivo)
        <p class="periodo">Período: {{ $fechaDesde }} — {{ $fechaHasta }}</p>
    @endif
</div>

<table class="detalle" cellspacing="0" cellpadding="0">
    <thead>
        <tr>
            <th class="col-fecha">Fecha</th>
            <th class="col-cant">Cantidad</th>
            <th class="col-tipo">Tipo</th>
            <th class="col-just">Just. / Injus.</th>
            <th class="col-obs">Observaciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($inasistencias as $i)
            @php
                $just = strtoupper(trim((string) ($i->just ?? '')));
                $codigoJust = $just === 'J' ? 'J' : 'I';
            @endphp
            <tr>
                <td class="col-fecha">{{ $i->fecha?->format('d/m/Y') ?? '—' }}</td>
                <td class="col-cant">
                    @if ($i->cantidad !== null)
                        {{ number_format((float) $i->cantidad, 2, ',', '') }}
                    @else
                        —
                    @endif
                </td>
                <td class="col-tipo">{{ $i->etiquetaTipo() }}</td>
                <td class="col-just">{{ $codigoJust }}</td>
                <td class="col-obs">{{ trim((string) ($i->obs ?? '')) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="text-align:center;">Sin inasistencias registradas en el período.</td>
            </tr>
        @endforelse
    </tbody>
</table>

@if ($totalesCatalogo !== [])
    <div class="totales">
        @foreach ($totalesCatalogo as $tarjeta)
            <p>
                <span class="label">{{ $tarjeta['concepto'] }}:</span>
                {{ number_format((float) $tarjeta['total'], 2, ',', '') }}
            </p>
        @endforeach
    </div>
@endif

<div class="firmas">
    <table cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <div class="linea"></div>
                <p class="etiqueta">Firma del Preceptor/a</p>
            </td>
            <td>
                <div class="linea"></div>
                <p class="etiqueta">Firma Responsable</p>
            </td>
        </tr>
    </table>
</div>
