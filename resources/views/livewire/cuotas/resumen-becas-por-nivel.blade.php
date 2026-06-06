<div>
    <div class="se-page max-w-6xl mx-auto">
        <section class="se-hero mb-4">
            <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-0.5">
                    <p class="se-eyebrow">Becas</p>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Resumen de Becas por Nivel</h1>
                    <p class="text-xs text-white/75">
                        Ciclo lectivo {{ $ano }} · Matrículas activas con beca asignada
                    </p>
                </div>
                @if ($filas !== [])
                    <a href="{{ route('cuotas.resumen-becas-por-nivel.csv') }}"
                       class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M16 10V4a2 2 0 00-2-2H8a2 2 0 00-2 2v6"/>
                        </svg>
                        Exportar CSV
                    </a>
                @endif
            </div>
        </section>

        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <p class="text-sm text-neutral-700">
                    Cantidad de becas otorgadas por tipo y nivel pedagógico.
                    @if ($filas !== [])
                        Pulse sobre un número para ver el detalle de estudiantes.
                    @endif
                </p>
            </div>

            @if ($filas === [])
                <div class="px-4 py-8 text-center text-sm text-neutral-500 sm:px-5">
                    No hay tipos de beca registrados (excluyendo cuota entera).
                </div>
            @else
                <div class="w-full overflow-x-auto se-grid-angosta-wrap px-4 py-4 sm:px-5 sm:py-5">
                    <table class="w-max max-w-full text-sm">
                        <thead class="bg-accent-50">
                            <tr class="border-b border-accent-200">
                                <th scope="col" class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                    Nombre Beca
                                </th>
                                @foreach ($niveles as $nivel)
                                    <th scope="col" class="w-20 px-2 py-2.5 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                        {{ $nivel['nombre'] }}
                                    </th>
                                @endforeach
                                <th scope="col" class="w-20 px-2 py-2.5 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100">
                            @foreach ($filas as $fila)
                                <tr class="hover:bg-accent-50/60">
                                    <td class="px-4 py-2 font-medium text-neutral-800">
                                        {{ $fila['nombreBeca'] }}
                                    </td>
                                    @foreach ($niveles as $nivel)
                                        @php $cant = (int) ($fila['porNivel'][$nivel['id']] ?? 0); @endphp
                                        <td class="w-20 px-2 py-2 text-center text-base tabular-nums">
                                            @if ($cant > 0)
                                                <button type="button"
                                                        wire:click="abrirDetalle({{ $fila['idBeca'] }}, {{ $nivel['id'] }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="abrirDetalle"
                                                        class="inline-flex min-h-[2rem] min-w-[2.25rem] cursor-pointer items-center justify-center rounded-lg border-2 border-primary-400 bg-white px-2 py-1 text-base font-semibold text-primary-700 shadow-sm transition hover:border-primary-600 hover:bg-primary-50 hover:text-primary-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                        title="Ver detalle — {{ $fila['nombreBeca'] }} · {{ $nivel['nombre'] }}">
                                                    {{ $cant }}
                                                </button>
                                            @else
                                                <span class="text-base text-neutral-400">0</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    @php $totalFila = (int) $fila['total']; @endphp
                                    <td class="w-20 px-2 py-2 text-center text-base tabular-nums font-bold">
                                        @if ($totalFila > 0)
                                            <button type="button"
                                                    wire:click="abrirDetalle({{ $fila['idBeca'] }}, 0)"
                                                    wire:loading.attr="disabled"
                                                    wire:target="abrirDetalle"
                                                    class="inline-flex min-h-[2rem] min-w-[2.25rem] cursor-pointer items-center justify-center rounded-lg border-2 border-primary-500 bg-white px-2 py-1 text-base font-bold text-primary-700 shadow-sm transition hover:border-primary-700 hover:bg-primary-50 hover:text-primary-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                    title="Ver detalle — {{ $fila['nombreBeca'] }} · todos los niveles">
                                                {{ $totalFila }}
                                            </button>
                                        @else
                                            <span class="text-base font-bold text-neutral-400">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-accent-200 bg-accent-50/90">
                            <tr>
                                <td class="px-4 py-2.5 text-sm font-bold text-neutral-800">
                                    Total Acumulado — Suma
                                </td>
                                @foreach ($niveles as $nivel)
                                    <td class="w-20 px-2 py-2.5 text-center text-base font-bold tabular-nums text-neutral-800">
                                        {{ (int) ($totalesNivel[$nivel['id']] ?? 0) }}
                                    </td>
                                @endforeach
                                <td class="w-20 px-2 py-2.5 text-center text-base font-bold tabular-nums text-neutral-800">
                                    {{ (int) $totalGeneral }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($modalDetalleAbierto)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="modal-detalle-becas-titulo">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarDetalle"></div>

                <div class="relative z-10 my-auto flex w-full max-w-4xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),42rem)]">
                    <div class="flex shrink-0 items-start justify-between gap-3 border-b border-accent-200 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <h3 id="modal-detalle-becas-titulo" class="text-base font-semibold text-neutral-900">
                                Detalle de becas
                            </h3>
                            <p class="mt-0.5 text-sm text-neutral-600">{{ $detalleTitulo }}</p>
                            <p class="mt-1 text-xs text-neutral-500">
                                <span class="font-semibold tabular-nums">{{ $detalleTotalAlumnos }}</span>
                                {{ $detalleTotalAlumnos === 1 ? 'estudiante' : 'estudiantes' }}
                                en
                                <span class="font-semibold tabular-nums">{{ count($detalleGrupos) }}</span>
                                {{ count($detalleGrupos) === 1 ? 'curso' : 'cursos' }}
                            </p>
                        </div>
                        <button type="button"
                                wire:click="cerrarDetalle"
                                class="shrink-0 rounded-lg p-1.5 text-neutral-400 transition hover:bg-accent-50 hover:text-neutral-700"
                                aria-label="Cerrar">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto divide-y divide-accent-200">
                        @foreach ($detalleGrupos as $grupo)
                            <section wire:key="detalle-beca-curso-{{ md5($grupo['etiqueta']) }}">
                                <div class="sticky top-0 z-[1] flex items-center justify-between gap-3 border-b border-accent-200 bg-accent-50/95 px-4 py-2.5 backdrop-blur-sm sm:px-5">
                                    <p class="min-w-0 text-sm font-semibold text-primary-800">
                                        {{ $grupo['etiqueta'] }}
                                    </p>
                                    <span class="shrink-0 text-xs tabular-nums text-neutral-500">
                                        {{ $grupo['cantidad'] }}
                                        {{ $grupo['cantidad'] === 1 ? 'alumno' : 'alumnos' }}
                                    </span>
                                </div>
                                <table class="min-w-full text-sm">
                                    <thead class="bg-white">
                                        <tr class="border-b border-accent-100">
                                            <th scope="col" class="w-12 px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500 sm:px-5">#</th>
                                            <th scope="col" class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500 sm:px-4">Estudiante</th>
                                            <th scope="col" class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500 sm:px-4">DNI</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-accent-100">
                                        @foreach ($grupo['alumnos'] as $idx => $alumno)
                                            <tr class="hover:bg-accent-50/60">
                                                <td class="px-4 py-2 tabular-nums text-neutral-500 sm:px-5">{{ $idx + 1 }}</td>
                                                <td class="px-3 py-2 font-medium text-neutral-800 sm:px-4">{{ $alumno['alumno'] }}</td>
                                                <td class="px-3 py-2 tabular-nums text-neutral-700 sm:px-4">{{ $alumno['dni'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </section>
                        @endforeach
                    </div>

                    <div class="flex shrink-0 justify-end border-t border-accent-200 bg-accent-50/60 px-5 py-3 sm:px-6">
                        <button type="button" wire:click="cerrarDetalle" class="btn-secondary">Cerrar</button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
