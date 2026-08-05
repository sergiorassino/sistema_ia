@php
    $ano = $ano ?? now()->year;
    $alumnoLinea = $alumnoLinea ?? '';
    $dni = $dni ?? '';
    $cursoLabel = $cursoLabel ?? '';
    $fechaDesde = $fechaDesde ?? '';
    $fechaHasta = $fechaHasta ?? '';
    $filtroFechasActivo = $filtroFechasActivo ?? false;
    $inasistencias = $inasistencias ?? collect();
    $resumen = $resumen ?? null;
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

@if ($resumen)
    <div class="totales">
        <p><span class="label">Inasistencias justificadas:</span> {{ $resumen->formatear($resumen->justificadas) }}</p>
        <p><span class="label">Inasistencias injustificadas:</span> {{ $resumen->formatear($resumen->injustificadas) }}</p>
        <p><span class="label">Llegadas tarde 1/4:</span> {{ $resumen->formatear($resumen->llegadasTardeCuarto) }}</p>
        <p><span class="label">Llegadas tarde 1/2:</span> {{ $resumen->formatear($resumen->llegadasTardeMedio) }}</p>
        <p><span class="label">Retiro anticipado:</span> {{ $resumen->formatear($resumen->retirosAnticipados) }}</p>
        <p><span class="label">Total de inasistencias:</span> {{ $resumen->formatear($resumen->totalClase()) }}</p>
        <p><span class="label">Inasistencias a educación física:</span> {{ $resumen->formatear($resumen->educacionFisica) }}</p>
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
