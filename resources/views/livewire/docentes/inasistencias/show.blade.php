<div class="se-page">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="se-soft-card border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="se-eyebrow">Inasistencias docente</p>
                <h2 class="text-2xl font-bold tracking-tight">{{ $profesor->apellido }} {{ $profesor->nombre }}</h2>
                <p class="text-sm text-white/80">DNI {{ number_format((float) $profesor->dni, 0, ',', '.') }} · Año {{ $anoLectivo }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('docentes.inasistencias.create', $profesor->id) }}" class="btn-primary">+ Nueva inasistencia</a>
                <a href="{{ route('docentes.inasistencias') }}" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">← Volver</a>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-accent-50">
                    <tr>
                        <th class="table-header">Tipo</th>
                        <th class="table-header">Cargo</th>
                        <th class="table-header">Nivel</th>
                        <th class="table-header">Fecha</th>
                        <th class="table-header">Hasta</th>
                        <th class="table-header text-center">Oblig. inasist.</th>
                        <th class="table-header text-center">Justif.</th>
                        <th class="table-header">Motivo</th>
                        <th class="table-header">Observaciones</th>
                        <th class="table-header w-24"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-accent-200">
                    @forelse ($inasistencias as $i)
                        <tr class="hover:bg-accent-50/60">
                            <td class="table-cell">
                                <span class="se-pill {{ $i->inaLic ? 'bg-neutral-200' : 'bg-primary-50 text-primary-800' }}">
                                    {{ $i->inaLic ? 'Licencia' : 'Día' }}
                                </span>
                            </td>
                            <td class="table-cell">{{ $i->nombreCargo ?? '—' }}</td>
                            <td class="table-cell">{{ $i->nivel?->nivel ?? schoolCtx()->nivelNombre() }}</td>
                            <td class="table-cell font-mono">{{ $i->fecha?->format('d/m/Y') ?? '—' }}</td>
                            <td class="table-cell font-mono">{{ $i->hasta?->format('d/m/Y') ?? '—' }}</td>
                            <td class="table-cell text-center tabular-nums">{{ \App\Support\InasistenciasDocentes::formatearCantidad($i->cantObligIna) }}</td>
                            <td class="table-cell text-center">{{ (int) $i->justif === 1 ? 'Sí' : 'No' }}</td>
                            <td class="table-cell">{{ $i->tipo?->motivo ?? '—' }}</td>
                            <td class="table-cell text-neutral-600">{{ \Illuminate\Support\Str::limit($i->obs, 50) }}</td>
                            <td class="table-cell text-right">
                                <a href="{{ route('docentes.inasistencias.edit', ['idProfesor' => $profesor->id, 'id' => $i->id]) }}" class="btn-secondary btn-sm">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="table-cell py-10 text-center text-neutral-500">
                                Aún no hay inasistencias cargadas para este año.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
