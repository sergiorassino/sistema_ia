<div class="se-page se-page--ancho-completo min-w-0">
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

    <div class="se-card mt-6 min-w-0 overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="text-sm text-neutral-700">
                Alumnos regulares en el ciclo lectivo activo, con calificaciones
                <code class="text-xs">apro = 1</code> y <code class="text-xs">condAdeuda = TM</code>.
                En TM1–TM6 y Nota se pueden cargar notas del 1 al 10, la <strong>a</strong> de ausente, o los textos <strong>Aprob</strong> y <strong>Reprob</strong>
                (desplegable o teclado). Flechas y Enter pasan de campo. Se guardan al salir de cada celda.
            </p>
        </div>

        <div class="flex flex-col gap-4 border-b border-accent-200 bg-accent-50 px-5 py-4 lg:flex-row lg:items-end">
            <div class="grid min-w-0 w-full grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label for="tm-buscar" class="form-label">Buscar alumno</label>
                    <input id="tm-buscar"
                           type="search"
                           wire:model.live.debounce.300ms="busqueda"
                           class="form-input mt-1.5"
                           placeholder="Apellido o nombre…"
                           autocomplete="off">
                </div>
                <div>
                    <label for="tm-curso" class="form-label">Curso de la materia adeudada</label>
                    <select id="tm-curso" wire:model.live="filtroCurso" class="form-select mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($opcionesCurso as $cursoOpt)
                            <option value="{{ $cursoOpt }}">{{ $cursoOpt }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="tm-curso-act" class="form-label">Curso actual</label>
                    <select id="tm-curso-act" wire:model.live="filtroCursoActual" class="form-select mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($opcionesCursoActual as $cursoActOpt)
                            <option value="{{ $cursoActOpt }}">{{ $cursoActOpt }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
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
        </div>

        @if ($totalSinFiltro === 0)
            <div class="px-6 py-12 text-center">
                <p class="text-sm text-neutral-600">No hay registros con condición TM en este nivel.</p>
            </div>
        @elseif ($totalFilas === 0)
            <div class="px-6 py-12 text-center">
                <p class="text-sm text-neutral-600">Ningún registro coincide con la búsqueda o los filtros.</p>
            </div>
        @else
            <div class="w-full min-w-0 overflow-x-hidden px-3 py-2">
                <table class="se-tm-grid w-full divide-y divide-accent-200 text-sm">
                    <thead class="bg-white">
                        <tr>
                            <th class="se-tm-col-est px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Estudiante</th>
                            <th class="se-tm-col-ano px-1 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Año</th>
                            <th class="se-tm-col-curso px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso</th>
                            <th class="se-tm-col-mat px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Materia</th>
                            @foreach (['tm1' => 'TM1', 'tm2' => 'TM2', 'tm3' => 'TM3', 'tm4' => 'TM4', 'tm5' => 'TM5', 'tm6' => 'TM6', 'tmNota' => 'Nota'] as $campoEnc => $lbl)
                                <th class="{{ $campoEnc === 'tmNota' ? 'se-tm-col-nota-final' : 'se-tm-col-nota' }} px-0.5 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">{{ $lbl }}</th>
                            @endforeach
                            <th class="se-tm-col-acta px-1 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500" title="Acta de compromiso">Acta</th>
                            <th class="se-tm-col-curso-act px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso actual</th>
                            <th class="se-tm-col-prof px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Profesor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white" data-se-tm-tbody>
                        @foreach ($filas as $fila)
                            @php $id = (int) $fila['id']; @endphp
                            <tr class="hover:bg-accent-50/80" wire:key="tm-row-{{ $id }}">
                                <td class="se-tm-col-est px-2 py-2 font-medium text-neutral-800" title="{{ $fila['estudiante'] }}">{{ $fila['estudiante'] }}</td>
                                <td class="se-tm-col-ano px-1 py-2 text-center tabular-nums text-neutral-700">{{ $fila['ano_lectivo'] }}</td>
                                <td class="se-tm-col-curso px-2 py-2 text-neutral-700" title="{{ $fila['curso'] }}">{{ $fila['curso'] }}</td>
                                <td class="se-tm-col-mat px-2 py-2 text-neutral-700" title="{{ $fila['materia'] }}">{{ $fila['materia'] }}</td>
                                @foreach (['tm1', 'tm2', 'tm3', 'tm4', 'tm5', 'tm6', 'tmNota'] as $campo)
                                    <td class="{{ $campo === 'tmNota' ? 'se-tm-col-nota-final' : 'se-tm-col-nota' }} px-0.5 py-1">
                                        @include('livewire.calificaciones-primario.partials.celda-nota-permitida', [
                                            'id' => 'se-tm-'.$id.'-'.$campo,
                                            'value' => $ediciones[$id][$campo] ?? '',
                                            'wireKey' => 'tm-inp-'.$id.'-'.$campo,
                                            'inputClass' => 'rounded-lg border border-accent-200 px-0.5 py-1 text-center text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
                                            'notasPermitidasActiva' => true,
                                            'notasPermitidasLista' => $opcionesTm,
                                            'incluirOpcionVacia' => true,
                                            'soloLectura' => false,
                                            'menuClass' => 'se-tm-nota-picker-menu',
                                        ])
                                    </td>
                                @endforeach
                                <td class="se-tm-col-acta px-1 py-2 text-center">
                                    <a href="{{ route('examenes.tercer-materia.acta-compromiso.pdf', $id) }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center justify-center rounded-lg p-1.5 text-primary-700 transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                       title="Imprimir acta de compromiso">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                        <span class="sr-only">Acta de compromiso</span>
                                    </a>
                                </td>
                                <td class="se-tm-col-curso-act px-2 py-2 text-neutral-700" title="{{ $fila['curso_actual'] }}">{{ $fila['curso_actual'] !== '' ? $fila['curso_actual'] : '—' }}</td>
                                <td class="se-tm-col-prof px-2 py-2 text-neutral-600 text-xs" title="{{ $fila['profesor_actual'] }}">{{ $fila['profesor_actual'] !== '' ? $fila['profesor_actual'] : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@script
<script>
    $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
</script>
@endscript
