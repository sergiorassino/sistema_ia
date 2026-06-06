<div class="se-page max-w-7xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Exámenes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Gestión de tercer materia</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo actual {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
        </div>
    </section>

    @error('guardar')
        <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    <div class="se-card mt-6 overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="text-sm text-neutral-700">
                Alumnos regulares en el ciclo lectivo activo, con calificaciones
                <code class="text-xs">apro = 1</code> y <code class="text-xs">condAdeuda = TM</code>.
                Las notas TM se guardan al salir de cada campo. El curso actual y el docente corresponden al ciclo lectivo activo en sesión.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-b border-accent-200 bg-accent-50 px-5 py-4">
            <span class="se-pill tabular-nums">{{ $totalFilas }} registro{{ $totalFilas === 1 ? '' : 's' }}</span>
            @if ($totalFilas > 0)
                <a href="{{ $pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-primary-500 bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir grilla
                </a>
            @endif
        </div>

        @if ($totalFilas === 0)
            <div class="px-6 py-12 text-center">
                <p class="text-sm text-neutral-600">No hay registros con condición TM en este nivel.</p>
            </div>
        @else
            <div class="w-full overflow-x-auto">
                <table class="min-w-[1100px] w-full divide-y divide-accent-200 text-sm">
                    <thead class="bg-white">
                        <tr>
                            <th class="min-w-[10rem] px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Estudiante</th>
                            <th class="w-14 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Año</th>
                            <th class="min-w-[6rem] px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso</th>
                            <th class="min-w-[8rem] px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Materia</th>
                            @foreach (['TM1', 'TM2', 'TM3', 'TM4', 'TM5', 'TM6', 'Nota'] as $lbl)
                                <th class="w-14 px-1 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">{{ $lbl }}</th>
                            @endforeach
                            <th class="w-12 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500" title="Acta de compromiso">Acta</th>
                            <th class="min-w-[5rem] px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso actual</th>
                            <th class="min-w-[8rem] px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Profesor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @foreach ($filas as $fila)
                            @php $id = (int) $fila['id']; @endphp
                            <tr class="hover:bg-accent-50/80" wire:key="tm-row-{{ $id }}">
                                <td class="px-3 py-2 font-medium text-neutral-800">{{ $fila['estudiante'] }}</td>
                                <td class="px-2 py-2 text-center tabular-nums text-neutral-700">{{ $fila['ano_lectivo'] }}</td>
                                <td class="px-3 py-2 text-neutral-700">{{ $fila['curso'] }}</td>
                                <td class="px-3 py-2 text-neutral-700">{{ $fila['materia'] }}</td>
                                @foreach (['tm1', 'tm2', 'tm3', 'tm4', 'tm5', 'tm6', 'tmNota'] as $campo)
                                    <td class="px-1 py-1">
                                        <input type="text"
                                               value="{{ $ediciones[$id][$campo] ?? '' }}"
                                               wire:blur="guardarCampoTm({{ $id }}, '{{ $campo }}', $event.target.value)"
                                               wire:loading.attr="disabled"
                                               wire:target="guardarCampoTm"
                                               maxlength="20"
                                               class="w-full rounded-lg border border-accent-200 px-1.5 py-1 text-center text-xs tabular-nums focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:opacity-60"
                                               aria-label="{{ strtoupper($campo) }} — {{ $fila['estudiante'] }}">
                                    </td>
                                @endforeach
                                <td class="px-2 py-2 text-center">
                                    <a href="{{ route('examenes.tercer-materia.acta-compromiso.pdf', $id) }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center justify-center rounded-lg p-2 text-primary-700 transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                       title="Imprimir acta de compromiso">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                        <span class="sr-only">Acta de compromiso</span>
                                    </a>
                                </td>
                                <td class="px-3 py-2 text-neutral-700">{{ $fila['curso_actual'] !== '' ? $fila['curso_actual'] : '—' }}</td>
                                <td class="px-3 py-2 text-neutral-600 text-xs">{{ $fila['profesor_actual'] !== '' ? $fila['profesor_actual'] : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
