<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Estadísticas</p>
                <h2 class="text-2xl font-bold tracking-tight">Estadística por Sexo y Curso</h2>
                <p class="text-sm text-white/80">
                    Solo regulares
                    @if ($datos['nivel'] !== '' || $datos['ano'] !== '')
                        · {{ $datos['nivel'] }}@if ($datos['ano'] !== '') · Ciclo {{ $datos['ano'] }}@endif
                    @endif
                </p>
            </div>
            <a href="{{ route('estadistica.sexoCurso.pdf') }}"
               target="_blank"
               rel="noopener"
               class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl border border-white/25 bg-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/20">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Imprimir PDF
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        @if ($datos['catalogo_vacio'])
            <p class="border-b border-accent-200 bg-accent-50 px-4 py-3 text-sm text-neutral-700">
                El colegio no tiene sexos cargados en el catálogo. Los alumnos figuran en Sin dato.
            </p>
        @endif

        @if ($datos['filas'] === [])
            <p class="px-4 py-10 text-center text-sm text-neutral-500">No hay cursos en este ciclo y nivel.</p>
        @else
            <div class="w-full overflow-x-auto">
                <table class="se-matriz-list-tabla">
                    <thead>
                        <tr>
                            <th scope="col" class="text-left whitespace-nowrap">Año</th>
                            <th scope="col" class="text-left whitespace-nowrap">Nivel</th>
                            <th scope="col" class="text-left whitespace-nowrap">Curso y sección</th>
                            @foreach ($datos['columnas'] as $columna)
                                <th scope="col" class="text-center whitespace-nowrap">{{ $columna['etiqueta'] }}</th>
                            @endforeach
                            <th scope="col" class="text-center whitespace-nowrap">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datos['filas'] as $fila)
                            <tr>
                                <td class="whitespace-nowrap">{{ $datos['ano'] }}</td>
                                <td class="whitespace-nowrap">{{ $datos['nivel'] }}</td>
                                <td class="whitespace-nowrap font-medium">{{ $fila['curso'] }}</td>
                                @foreach ($datos['columnas'] as $columna)
                                    <td class="text-center tabular-nums">{{ $fila['conteos'][$columna['clave']] ?? 0 }}</td>
                                @endforeach
                                <td class="text-center font-semibold tabular-nums">{{ $fila['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="border border-gray-300 bg-neutral-200 px-2 py-1 text-left text-[11px] font-semibold text-neutral-800">
                                Total acumulado ({{ count($datos['filas']) }}) — Suma
                            </td>
                            @foreach ($datos['columnas'] as $columna)
                                <td class="border border-gray-300 bg-neutral-200 px-2 py-1 text-center text-[11px] font-semibold tabular-nums text-neutral-800">
                                    {{ $datos['totales'][$columna['clave']] ?? 0 }}
                                </td>
                            @endforeach
                            <td class="border border-gray-300 bg-neutral-200 px-2 py-1 text-center text-[11px] font-semibold tabular-nums text-neutral-800">
                                {{ $datos['total_general'] }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
