<div class="se-page max-w-7xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Estadísticas</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Estadísticas por edad</h1>
                <p class="text-xs text-white/75">
                    {{ $datos['nivel'] ?? schoolCtx()->nivelNombre() }} · Ciclo {{ $ano }} · Solo regulares
                </p>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-accent-200 px-4 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-5">
            <div class="w-full sm:max-w-xs">
                <label for="fecha-estadistica-edad" class="form-label">Fecha de cálculo</label>
                <input id="fecha-estadistica-edad"
                       type="date"
                       wire:model.live="fecha"
                       class="form-input tabular-nums" />
            </div>
            @if ($pdfUrl !== '#')
                <a href="{{ $pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir (PDF)
                </a>
            @endif
        </div>

        @if ($datos === null)
            <p class="px-4 py-8 text-center text-sm text-neutral-600 sm:px-5">Indique una fecha de cálculo válida.</p>
        @elseif ($datos['cursos'] === [])
            <p class="px-4 py-8 text-center text-sm text-neutral-600 sm:px-5">No hay cursos en este nivel para el ciclo lectivo.</p>
        @else
            <div class="border-b border-accent-200 bg-accent-50/70 px-4 py-2.5 sm:px-5">
                <p class="text-sm text-neutral-700">
                    Edad cumplida al {{ $datos['fechaTexto'] }}. Cada columna es un curso de {{ $datos['nivel'] }}.
                </p>
            </div>
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <table class="min-w-full shrink-0 border-collapse text-[11px] text-neutral-800">
                        <thead>
                            <tr class="bg-accent-50 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                <th class="sticky left-0 z-10 border border-accent-200 bg-accent-50 px-2 py-1.5 text-left">Edad</th>
                                @foreach ($datos['cursos'] as $curso)
                                    <th class="border border-accent-200 px-1.5 py-1.5 text-center whitespace-nowrap">{{ $curso['etiqueta'] }}</th>
                                @endforeach
                                <th class="border border-accent-200 px-2 py-1.5 text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datos['filas'] as $fila)
                                @php $esTotal = $fila['clave'] === \App\Support\Estadistica\EstadisticaPorEdadDatos::TOTAL; @endphp
                                <tr @class(['bg-accent-100/70 font-semibold' => $esTotal])>
                                    <th @class([
                                        'sticky left-0 z-10 border border-accent-200 px-2 py-1 text-left font-semibold whitespace-nowrap',
                                        'bg-accent-100/70' => $esTotal,
                                        'bg-white' => ! $esTotal,
                                    ])>{{ $fila['etiqueta'] }}</th>
                                    @foreach ($datos['cursos'] as $curso)
                                        @php $valor = (int) ($fila['celdas'][$curso['id']] ?? 0); @endphp
                                        <td @class([
                                            'border border-accent-200 px-1.5 py-1 text-center tabular-nums',
                                            'font-semibold text-neutral-900' => $valor > 0 || $esTotal,
                                            'text-neutral-400' => $valor === 0 && ! $esTotal,
                                        ])>{{ $valor }}</td>
                                    @endforeach
                                    <td class="border border-accent-200 bg-accent-50/80 px-2 py-1 text-center font-semibold tabular-nums">{{ $fila['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
