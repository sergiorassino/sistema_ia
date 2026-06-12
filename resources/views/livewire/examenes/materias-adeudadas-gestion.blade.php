<div class="se-page max-w-7xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Exámenes · Secundario</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Gestión de materias adeudadas</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() ?? '—' }}
                </p>
            </div>
        </div>
    </section>

    <livewire:examenes.materias-adeudadas-preparacion-panel
        modulo="gestion"
        wire:key="prep-panel-gestion" />

    @if (! $esSecundario)
        <div class="se-card mt-6 px-6 py-10 text-center">
            <p class="text-sm font-semibold text-neutral-800">Nivel no compatible</p>
            <p class="mt-2 text-sm text-neutral-600">
                Este módulo está disponible con el contexto de gestión en <strong>Secundario</strong>.
                Cambiá el nivel en el selector superior e ingresá nuevamente.
            </p>
        </div>
    @elseif ($preparacionLista ?? false)
        <div class="se-toolbar mt-6 flex-col !items-stretch gap-4 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="ma-buscar" class="form-label">Buscar alumno</label>
                <div class="relative mt-1.5 max-w-md">
                    <input id="ma-buscar"
                           type="search"
                           wire:model.live.debounce.500ms="buscar"
                           x-on:focus="$event.target.select()"
                           x-on:click="$event.target.select()"
                           class="form-input w-full pr-10"
                           placeholder="Apellido, nombre o DNI (mín. {{ $minCharsBusqueda }} caracteres)"
                           autocomplete="off"
                           title="Al volver del detalle se mantiene la búsqueda. Hacé clic o escribí para reemplazarla.">
                    <div wire:loading.delay.shortest wire:target="buscar"
                         class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                        <svg class="h-4 w-4 animate-spin text-primary-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            @if ($alumnos !== null)
                <span class="se-pill tabular-nums">{{ $totalAlumnos }} alumno{{ $totalAlumnos === 1 ? '' : 's' }}</span>
            @endif
        </div>

        <div class="se-card mt-6 overflow-hidden p-0">
            <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Alumnos del legajo</p>
                <p class="text-sm text-neutral-600">
                    Busque entre todos los alumnos que cursaron secundario en la institución. Use el icono de carga manual para registrar adeudos por alumno.
                </p>
            </div>

            @if ($alumnos === null)
                <div class="px-6 py-12 text-center">
                    <p class="text-sm text-neutral-600">
                        Ingrese al menos {{ $minCharsBusqueda }} caracteres (apellido, nombre o DNI) para buscar en el historial de secundario.
                    </p>
                </div>
            @elseif ($totalAlumnos === 0)
                <div class="px-6 py-12 text-center">
                    <p class="text-sm text-neutral-600">
                        No hay alumnos que coincidan con la búsqueda.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-accent-200 text-sm">
                        <thead class="bg-white">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Apellido</th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Nombre</th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">DNI</th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso</th>
                                <th scope="col" class="min-w-[7rem] px-3 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Carga manual</th>
                                <th scope="col" class="min-w-[6rem] px-3 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Inscribir</th>
                                <th scope="col" class="min-w-[7rem] px-3 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Carga de notas</th>
                                <th scope="col" class="min-w-[8rem] px-3 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Historial de exámenes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100 bg-white">
                            @foreach ($alumnos as $alumno)
                                <tr class="hover:bg-accent-50/80" wire:key="ma-alumno-{{ $alumno['idLegajos'] }}">
                                    <td class="whitespace-nowrap px-4 py-2.5 font-medium text-neutral-800">{{ $alumno['apellido'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-neutral-800">{{ $alumno['nombre'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-neutral-700">{{ $alumno['dni'] !== '' ? $alumno['dni'] : '—' }}</td>
                                    <td class="px-4 py-2.5 text-neutral-700">{{ $alumno['curso'] !== '' ? $alumno['curso'] : '—' }}</td>
                                    <td class="px-3 py-2.5 text-center">
                                        @include('livewire.examenes.partials.materias-adeudadas-accion', [
                                            'titulo' => 'Carga manual',
                                            'descripcion' => 'Cargar o borrar materias adeudadas',
                                            'icono' => 'carga',
                                            'navDestino' => 'examenes.materias-adeudadas.gestion.carga',
                                            'idLegajos' => $alumno['idLegajos'],
                                            'buscarListado' => $buscar,
                                        ])
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        @include('livewire.examenes.partials.materias-adeudadas-accion', [
                                            'titulo' => 'Inscribir',
                                            'descripcion' => 'Inscribir al alumno a rendir exámenes',
                                            'icono' => 'inscribir',
                                            'navDestino' => 'examenes.materias-adeudadas.gestion.inscribir',
                                            'idLegajos' => $alumno['idLegajos'],
                                            'buscarListado' => $buscar,
                                        ])
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        @include('livewire.examenes.partials.materias-adeudadas-accion', [
                                            'titulo' => 'Carga de notas',
                                            'descripcion' => 'Cargar notas de los exámenes',
                                            'icono' => 'notas',
                                            'navDestino' => 'examenes.materias-adeudadas.gestion.notas',
                                            'idLegajos' => $alumno['idLegajos'],
                                            'buscarListado' => $buscar,
                                        ])
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        @include('livewire.examenes.partials.materias-adeudadas-accion', [
                                            'titulo' => 'Historial de exámenes',
                                            'descripcion' => 'Historial de materias adeudadas, rendiciones y notas',
                                            'icono' => 'historial',
                                            'navDestino' => 'examenes.materias-adeudadas.gestion.historial',
                                            'idLegajos' => $alumno['idLegajos'],
                                            'buscarListado' => $buscar,
                                        ])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($alumnos->hasPages())
                    <div class="border-t border-accent-200 bg-white px-4 py-3">
                        {{ $alumnos->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
