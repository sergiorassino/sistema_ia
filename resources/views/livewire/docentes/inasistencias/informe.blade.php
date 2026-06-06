<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="se-eyebrow">Informe bimestral</p>
                <h2 class="text-2xl font-bold tracking-tight">{{ $profesor->apellido }} {{ $profesor->nombre }}</h2>
                <p class="text-sm text-white/80">{{ $bimestreInfo['titulo'] }} {{ $anio }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('docentes.inasistencias') }}" class="btn-secondary !border-white/30 !bg-white/10 !text-white">← Listado</a>
                <a href="{{ route('docentes.inasistencias.show', $profesor->id) }}" class="btn-secondary !border-white/30 !bg-white/10 !text-white">Todas las inasistencias</a>
                <a href="{{ route('docentes.inasistencias.create', ['idProfesor' => $profesor->id, 'retorno' => 'informe', 'bimestre' => $bimestre, 'anio' => $anio]) }}"
                   class="btn-primary">+ Nueva</a>
                <a href="{{ route('docentes.inasistencias.informe.pdf', ['idProfesor' => $profesor->id, 'bimestre' => $bimestre, 'anio' => $anio]) }}"
                   target="_blank" rel="noopener" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary-700">PDF</a>
            </div>
        </div>
    </section>

    <div class="se-card mb-6 p-4">
        <div class="flex flex-wrap gap-4 text-sm">
            <span><strong>Total:</strong> {{ \App\Support\InasistenciasDocentes::formatearCantidad($resumen['total']) }}</span>
            <span><strong>Justificadas:</strong> {{ \App\Support\InasistenciasDocentes::formatearCantidad($resumen['justificadas']) }}</span>
            <span><strong>Injustificadas:</strong> {{ \App\Support\InasistenciasDocentes::formatearCantidad($resumen['injustificadas']) }}</span>
            <span><strong>Máx. faltas:</strong> {{ (int) $resumen['maxFaltasPosibles'] }}</span>
            <span class="{{ $resumen['tieneFaltasDescuento'] ? 'text-red-700 font-semibold' : '' }}">
                <strong>A descuento:</strong> {{ (int) $resumen['totalDescuento'] }}
            </span>
        </div>
    </div>

    <div class="se-card overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-accent-50">
                <tr>
                    <th class="table-header">Tipo</th>
                    <th class="table-header">Cargo</th>
                    <th class="table-header">Nivel</th>
                    <th class="table-header">Fecha</th>
                    <th class="table-header">Hasta</th>
                    <th class="table-header text-center">Oblig.</th>
                    <th class="table-header text-center">Just.</th>
                    <th class="table-header">Motivo</th>
                    <th class="table-header"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-accent-200">
                @forelse ($inasistencias as $i)
                    <tr class="hover:bg-accent-50/60">
                        <td class="table-cell">{{ $i->inaLic ? 'Licencia' : 'Día' }}</td>
                        <td class="table-cell">{{ $i->nombreCargo ?? '—' }}</td>
                        <td class="table-cell">{{ $i->nivel?->nivel ?? '—' }}</td>
                        <td class="table-cell font-mono">{{ $i->fecha?->format('d/m/Y') }}</td>
                        <td class="table-cell font-mono">{{ $i->hasta?->format('d/m/Y') ?? '—' }}</td>
                        <td class="table-cell text-center">{{ $i->cantObligIna }}</td>
                        <td class="table-cell text-center">{{ (int) $i->justif === 1 ? 'Sí' : 'No' }}</td>
                        <td class="table-cell">{{ $i->tipo?->motivo }}</td>
                        <td class="table-cell text-right">
                            <a href="{{ route('docentes.inasistencias.edit', ['idProfesor' => $profesor->id, 'id' => $i->id, 'retorno' => 'informe', 'bimestre' => $bimestre, 'anio' => $anio]) }}"
                               class="btn-secondary btn-sm">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="table-cell py-8 text-center text-neutral-500">
                            No hay inasistencias en este bimestre.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
