<div class="se-page max-w-7xl">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Exámenes · Carga de notas</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Notas de examen</h2>
                @if (! empty($alumno))
                    <p class="text-sm text-white/90">
                        <span class="font-semibold">{{ $alumno['apellido'] }}, {{ $alumno['nombre'] }}</span>
                        @if ($alumno['dni'] !== '')
                            · DNI {{ $alumno['dni'] }}
                        @endif
                        @if ($alumno['curso'] !== '')
                            · {{ $alumno['curso'] }}
                        @endif
                    </p>
                    <p class="text-sm text-white/70">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() ?? '—' }}
                    </p>
                @endif
            </div>
            <a href="{{ route('examenes.materias-adeudadas.gestion') }}"
               wire:navigate
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al listado
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
            {{ session('success') }}
        </div>
    @endif

    @error('notas')
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    @if ($totalMaterias === 0)
        <div class="se-card mt-6 px-6 py-10 text-center">
            <p class="text-sm text-neutral-600">El alumno no tiene materias marcadas como adeudadas.</p>
        </div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start">
            <div class="se-card overflow-hidden p-0">
                <div class="border-b border-accent-200 bg-accent-50 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Materias adeudadas</p>
                    <p class="mt-1 text-sm text-neutral-600">
                        Seleccioná una materia para ver las rendiciones registradas.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-accent-200 text-sm">
                        <thead class="bg-white">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Asignatura</th>
                                <th scope="col" class="hidden px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500 sm:table-cell">Curso</th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Año</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100 bg-white">
                            @foreach ($materias as $fila)
                                @php
                                    $activa = $idCalificacionSeleccionada === $fila['id'];
                                @endphp
                                <tr wire:key="ma-notas-materia-{{ $fila['id'] }}"
                                    @class([
                                        'cursor-pointer transition',
                                        'bg-primary-50 ring-1 ring-inset ring-primary-200' => $activa,
                                        'hover:bg-accent-50/80' => ! $activa,
                                    ])
                                    wire:click="seleccionarMateria({{ $fila['id'] }})"
                                    role="button"
                                    tabindex="0"
                                    wire:keydown.enter="seleccionarMateria({{ $fila['id'] }})"
                                    aria-pressed="{{ $activa ? 'true' : 'false' }}">
                                    <td class="px-4 py-2.5 font-medium text-neutral-800">{{ $fila['materia'] }}</td>
                                    <td class="hidden px-4 py-2.5 text-neutral-700 sm:table-cell">{{ $fila['curso'] !== '' ? $fila['curso'] : '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-neutral-700">{{ $fila['ano_lectivo'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-accent-200 bg-white px-5 py-3">
                    <p class="text-xs text-neutral-500">
                        {{ $totalMaterias }} materia{{ $totalMaterias === 1 ? '' : 's' }} adeudada{{ $totalMaterias === 1 ? '' : 's' }}.
                    </p>
                </div>
            </div>

            <div class="se-card overflow-hidden p-0">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-accent-200 bg-accent-50 px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Rendiciones anteriores</p>
                        @if ($materiaSeleccionada)
                            <p class="mt-1 text-sm font-medium text-neutral-800">{{ $materiaSeleccionada['materia'] }}</p>
                            <p class="text-xs text-neutral-500">
                                Registros en <code class="rounded bg-white px-1">notasexamen</code> — solo consulta.
                            </p>
                        @else
                            <p class="mt-1 text-sm text-neutral-600">Seleccioná una materia del listado.</p>
                        @endif
                    </div>
                    @if ($materiaSeleccionada)
                        <button type="button"
                                wire:click="abrirModalNuevaNota"
                                class="btn-primary btn-sm shrink-0 whitespace-nowrap">
                            Cargar nueva nota
                        </button>
                    @endif
                </div>

                @if (! $materiaSeleccionada)
                    <div class="px-6 py-10 text-center">
                        <p class="text-sm text-neutral-600">Elegí una materia adeudada para ver el detalle.</p>
                    </div>
                @elseif ($totalHistorial === 0)
                    <div class="px-6 py-10 text-center">
                        <p class="text-sm text-neutral-600">Aún no hay rendiciones registradas para esta materia.</p>
                        <button type="button"
                                wire:click="abrirModalNuevaNota"
                                class="btn-primary btn-sm mt-4">
                            Cargar primera nota
                        </button>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-accent-200 text-sm">
                            <thead class="bg-white">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Fecha examen</th>
                                    <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Nota</th>
                                    <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Cond.</th>
                                    <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Libro</th>
                                    <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Folio</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-accent-100 bg-white">
                                @foreach ($historial as $registro)
                                    <tr class="bg-accent-50/30" wire:key="ma-notas-hist-{{ $registro['id'] }}">
                                        <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-neutral-800">{{ $registro['fecha'] }}</td>
                                        <td class="px-4 py-2.5 font-medium tabular-nums text-neutral-800">{{ $registro['nota'] !== '' ? $registro['nota'] : '—' }}</td>
                                        <td class="px-4 py-2.5 text-neutral-700">{{ $registro['condicion'] !== '' ? $registro['condicion'] : '—' }}</td>
                                        <td class="px-4 py-2.5 text-neutral-700">{{ $registro['libro'] !== '' ? $registro['libro'] : '—' }}</td>
                                        <td class="px-4 py-2.5 text-neutral-700">{{ $registro['folio'] !== '' ? $registro['folio'] : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-accent-200 bg-white px-5 py-3">
                        <p class="text-xs text-neutral-500">
                            {{ $totalHistorial }} rendición{{ $totalHistorial === 1 ? '' : 'es' }}.
                            Los registros anteriores no se pueden modificar desde esta pantalla.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($modalAbierto)
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="ma-notas-modal-titulo">
            <div class="absolute inset-0 bg-neutral-900/50 backdrop-blur-sm" wire:click="cerrarModal" aria-hidden="true"></div>

            <div class="relative z-10 w-full max-w-md rounded-2xl border border-accent-200 bg-white shadow-xl">
                <div class="border-b border-accent-200 px-5 py-4">
                    <h3 id="ma-notas-modal-titulo" class="text-base font-bold text-neutral-900">Nueva nota de examen</h3>
                    @if ($materiaSeleccionada)
                        <p class="mt-1 text-sm text-neutral-600">{{ $materiaSeleccionada['materia'] }}</p>
                    @endif
                </div>

                <form wire:submit="guardarNuevaNota" class="space-y-4 px-5 py-4">
                    <div>
                        <label for="ma-notas-fecha" class="form-label">Fecha del examen</label>
                        <input id="ma-notas-fecha"
                               type="date"
                               wire:model="fecha"
                               class="form-input @error('fecha') border-red-400 @enderror"
                               required>
                        @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ma-notas-nota" class="form-label">Nota</label>
                        <input id="ma-notas-nota"
                               type="text"
                               wire:model="nota"
                               maxlength="10"
                               class="form-input @error('nota') border-red-400 @enderror"
                               placeholder="Ej. 7"
                               required>
                        @error('nota') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ma-notas-cond" class="form-label">Condición de examen</label>
                        <select id="ma-notas-cond"
                                wire:model="condExamen"
                                class="form-input @error('condExamen') border-red-400 @enderror">
                            <option value="">— Sin especificar —</option>
                            @foreach ($condiciones as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                        @error('condExamen') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="ma-notas-libro" class="form-label">Libro</label>
                            <input id="ma-notas-libro"
                                   type="text"
                                   wire:model="libro"
                                   maxlength="10"
                                   class="form-input @error('libro') border-red-400 @enderror">
                            @error('libro') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="ma-notas-folio" class="form-label">Folio</label>
                            <input id="ma-notas-folio"
                                   type="text"
                                   wire:model="folio"
                                   maxlength="10"
                                   class="form-input @error('folio') border-red-400 @enderror">
                            @error('folio') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/80 -mx-5 -mb-4 mt-2 px-5 py-4 rounded-b-2xl">
                        <button type="button"
                                wire:click="cerrarModal"
                                class="btn-secondary">
                            Cancelar
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="guardarNuevaNota"
                                class="btn-primary">
                            <span wire:loading.remove wire:target="guardarNuevaNota">Guardar nota</span>
                            <span wire:loading wire:target="guardarNuevaNota">Guardando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
