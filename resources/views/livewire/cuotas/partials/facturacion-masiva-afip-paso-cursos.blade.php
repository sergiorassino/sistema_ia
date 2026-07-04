@php
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Cuotas\GestionAranceles;
@endphp

<div class="se-card overflow-hidden">
    <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-neutral-700">
                Elija cursos por nivel y/o estudiantes individuales. Luego podrá revisar la vista previa antes de emitir el comprobante.
            </p>
            @if ($cursos->isNotEmpty())
                <span class="se-pill shrink-0 tabular-nums">
                    {{ $cantidadSeleccionados }} curso(s)
                    · {{ $cantidadAlumnosSeleccionados }} individual(es)
                </span>
            @endif
        </div>
    </div>

    <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
        <label for="filtro-cursos-fact-afip" class="form-label">Buscar curso</label>
        <input id="filtro-cursos-fact-afip"
               type="search"
               wire:model.live.debounce.300ms="filtroCursos"
               placeholder="Nivel o nombre del curso…"
               class="form-input max-w-xl" />
        <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" wire:click="seleccionarTodosCursos"
                    class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 hover:bg-accent-50">
                Todos
            </button>
            <button type="button" wire:click="quitarTodosCursos"
                    class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-accent-50">
                Ninguno
            </button>
        </div>
    </div>

    @if ($cursosPorNivel === [])
        <div class="px-4 py-6 text-sm text-neutral-600 sm:px-5">
            @if (trim($filtroCursos) !== '')
                Ningún curso coincide con la búsqueda.
            @else
                No hay cursos para mostrar.
            @endif
        </div>
    @else
        @php $cantidadNiveles = count($cursosPorNivel); @endphp
        <div class="max-h-[min(65dvh,36rem)] overflow-y-auto px-3 py-2 sm:px-4">
            <div @class(['mx-auto w-full max-w-md' => $cantidadNiveles === 1, 'overflow-x-auto' => $cantidadNiveles > 1])>
                <div @class(['grid w-full gap-3' => $cantidadNiveles === 1, 'grid min-w-full gap-3' => $cantidadNiveles > 1])
                     @if ($cantidadNiveles > 1) style="grid-template-columns: repeat({{ $cantidadNiveles }}, minmax(12.5rem, 1fr));" @endif>
                    @foreach ($cursosPorNivel as $bloqueNivel)
                        <section wire:key="nivel-afip-{{ $bloqueNivel['idNivel'] }}"
                                 class="flex min-w-0 flex-col rounded-lg border border-accent-200/90 bg-accent-50/20">
                            <div class="border-b border-accent-200/80 px-2.5 py-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-primary-800">
                                    {{ $bloqueNivel['nivelNombre'] }}
                                    <span class="font-normal text-neutral-500 tabular-nums">
                                        ({{ $bloqueNivel['seleccionados'] }}/{{ $bloqueNivel['total'] }})
                                    </span>
                                </p>
                                <div class="mt-1.5 flex flex-col gap-1">
                                    <button type="button" wire:click="marcarNivel({{ $bloqueNivel['idNivel'] }})"
                                            class="inline-flex w-full justify-center rounded-md border border-accent-200 bg-white px-2 py-1 text-xs font-semibold text-primary-800 hover:bg-accent-50">
                                        Marcar nivel
                                    </button>
                                    <button type="button" wire:click="quitarNivel({{ $bloqueNivel['idNivel'] }})"
                                            class="inline-flex w-full justify-center rounded-md border border-accent-200 bg-white px-2 py-1 text-xs font-semibold text-neutral-600 hover:bg-accent-50">
                                        Quitar nivel
                                    </button>
                                </div>
                            </div>
                            <ul class="min-h-0 flex-1 list-none divide-y divide-accent-100/80 px-2 py-1">
                                @foreach ($bloqueNivel['cursos'] as $cursoItem)
                                    <li wire:key="curso-afip-{{ $cursoItem['id'] }}">
                                        <label class="flex cursor-pointer items-center gap-2 py-1.5 hover:bg-accent-50/70">
                                            <input type="checkbox" wire:model.live="cursosSeleccionados"
                                                   value="{{ $cursoItem['id'] }}"
                                                   class="h-4 w-4 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                            <span class="min-w-0 flex-1 text-sm text-neutral-800">{{ $cursoItem['etiqueta'] }}</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="border-t border-accent-200 bg-white px-4 py-4 sm:px-5">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Estudiantes individuales</p>
        <p class="mt-1 text-xs text-neutral-600">
            Busque por apellido, nombre o DNI y agregue uno o varios alumnos sin depender de la selección de cursos.
        </p>
        <label for="buscar-alumno-fact-afip" class="form-label mt-3">Buscar estudiante</label>
        <input id="buscar-alumno-fact-afip"
               type="search"
               wire:model.live.debounce.400ms="buscarAlumno"
               placeholder="Apellido y nombre, apellido, nombre o DNI…"
               autocomplete="off"
               class="form-input max-w-xl" />

        @if (trim($buscarAlumno) === '')
            <p class="mt-2 text-xs text-neutral-500">Ingrese un criterio para ver resultados.</p>
        @elseif ($legajosBusqueda === null || $legajosBusqueda->isEmpty())
            <p class="mt-2 text-xs text-neutral-600">No se encontraron estudiantes con ese criterio.</p>
        @else
            <ul class="mt-3 max-h-52 space-y-1.5 overflow-y-auto rounded-xl border border-accent-200 bg-accent-50/30 p-2 sm:p-2.5">
                @foreach ($legajosBusqueda as $legajo)
                    @php
                        $datos = GestionAranceles::datosListadoBusqueda($legajo);
                        $nombreCompleto = trim($legajo->apellido.', '.$legajo->nombre);
                        $yaSeleccionado = isset($idsAlumnosSeleccionados[(int) $legajo->id]);
                    @endphp
                    <li wire:key="buscar-alumno-afip-{{ $legajo->id }}"
                        class="rounded-xl border border-transparent bg-white/70 px-2.5 py-2 transition hover:border-accent-200 hover:bg-white hover:shadow-sm">
                        <div class="flex min-w-0 items-center gap-2">
                            <p class="min-w-0 truncate text-sm font-semibold text-neutral-800" title="{{ $nombreCompleto }}">
                                {!! CuotasFormato::resaltarTerminoBusqueda($nombreCompleto, $buscarAlumno) !!}
                            </p>
                            @if ($yaSeleccionado)
                                <span class="inline-flex shrink-0 items-center rounded-lg bg-primary-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-700 ring-1 ring-primary-200/80">
                                    Agregado
                                </span>
                            @else
                                <button type="button"
                                        wire:click="agregarAlumno({{ (int) $legajo->id }})"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-primary-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Agregar
                                </button>
                            @endif
                        </div>
                        <p class="mt-0.5 truncate text-[11px] text-neutral-500">
                            DNI {{ CuotasFormato::formatearDni($legajo->dni) }}
                            · {{ $datos['curso'] !== '' ? $datos['curso'] : 'Sin curso actual' }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($cantidadAlumnosSeleccionados > 0)
            <div class="mt-3">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Individuales seleccionados</p>
                <div class="mt-2 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                    @foreach ($alumnosSeleccionados as $chipAlumno)
                        <span wire:key="alumno-sel-afip-{{ $chipAlumno['id'] }}"
                              class="inline-flex max-w-full items-center gap-1 rounded-lg border border-primary-200 bg-white px-2 py-1 text-xs font-medium text-neutral-800">
                            <span class="truncate">{{ $chipAlumno['label'] }}</span>
                            <button type="button" wire:click="quitarAlumno({{ (int) $chipAlumno['id'] }})"
                                    class="shrink-0 text-neutral-400 hover:text-red-600" title="Quitar">×</button>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if ($cantidadSeleccionados > 0)
        <div class="border-t border-accent-200 bg-accent-50/40 px-4 py-3 sm:px-5">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Seleccionados</p>
            <div class="mt-2 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                @foreach ($cursosSeleccionadosResumen as $chip)
                    <span class="inline-flex max-w-full items-center gap-1 rounded-lg border border-primary-200 bg-white px-2 py-1 text-xs font-medium text-neutral-800">
                        <span class="truncate">{{ $chip['label'] }}</span>
                        <button type="button" wire:click="quitarCurso({{ $chip['id'] }})"
                                class="shrink-0 text-neutral-400 hover:text-red-600" title="Quitar">×</button>
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    @error('alcanceEstudiantes')
        <p class="px-4 pb-3 text-sm text-red-600 sm:px-5">{{ $message }}</p>
    @enderror

    <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
        <button type="button"
                wire:click="armarVistaPrevia"
                wire:loading.attr="disabled"
                wire:target="armarVistaPrevia"
                @disabled(! $puedeContinuarAlumnos)
                class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="armarVistaPrevia">Ver vista previa</span>
            <span wire:loading wire:target="armarVistaPrevia">Calculando…</span>
        </button>
    </div>
</div>
