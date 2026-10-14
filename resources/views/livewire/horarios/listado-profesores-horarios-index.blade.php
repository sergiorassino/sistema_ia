<div>
    <div class="se-page max-w-7xl">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Horarios</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Listado de Profesores con Horarios</h2>
                    <p class="text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
                @if ($pdfUrl)
                    <a class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50"
                       target="_blank"
                       rel="noopener noreferrer"
                       href="{{ $pdfUrl }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Imprimir PDF
                    </a>
                @endif
            </div>
        </section>

        <div class="se-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-accent-200 bg-white px-5 py-3">
                <p class="text-sm text-neutral-700">
                    Docentes con materia y curso asignados. Cada columna muestra el horario en que asiste ese día.
                </p>
                @if ($resultado['ok'] ?? false)
                    <span class="se-pill tabular-nums">
                        {{ (int) ($resultado['cantidadDocentes'] ?? 0) === 1 ? '1 docente' : ((int) ($resultado['cantidadDocentes'] ?? 0)).' docentes' }}
                    </span>
                @endif
            </div>

            @if (! empty($resultado['error']))
                <p class="px-5 py-6 text-center text-sm text-red-700">{{ $resultado['error'] }}</p>
            @else
                <div class="w-full overflow-x-auto">
                    <div class="flex justify-start px-4 py-3">
                        <table class="min-w-[72rem] border-collapse text-left text-[12px] text-neutral-800">
                            <thead>
                                <tr class="bg-accent-50 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                    <th scope="col" class="min-w-[12rem] border border-accent-200 px-2 py-2 text-left">Docente</th>
                                    @foreach ($dias as $num => $etiqueta)
                                        <th scope="col" class="min-w-[9rem] border border-accent-200 px-2 py-2 text-center">{{ $etiqueta }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($resultado['filas'] ?? []) as $i => $fila)
                                    <tr wire:key="lph-fila-{{ $i }}" class="hover:bg-accent-50/70">
                                        <td class="border border-accent-200 px-2 py-1.5 align-middle font-medium">{{ $fila['docente'] }}</td>
                                        @foreach ($dias as $num => $etiqueta)
                                            <td class="border border-accent-200 px-2 py-1.5 text-center align-middle tabular-nums leading-snug text-neutral-700">
                                                {{ $fila['dias'][$num] ?? '—' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 1 + count($dias) }}" class="border border-accent-200 px-3 py-8 text-center text-sm text-neutral-500">
                                            No hay docentes con materia, curso y horario cargado en este ciclo.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
