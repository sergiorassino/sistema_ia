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
                <input id="ma-buscar"
                       type="search"
                       wire:model.live.debounce.300ms="buscar"
                       class="form-input mt-1.5 w-full max-w-md"
                       placeholder="Apellido, nombre o DNI (mín. 2 caracteres)"
                       autocomplete="off">
            </div>
            <span class="se-pill tabular-nums">{{ $totalAlumnos }} alumno{{ $totalAlumnos === 1 ? '' : 's' }}</span>
        </div>

        <div class="se-card mt-6 overflow-hidden p-0">
            <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Alumnos del legajo</p>
                <p class="text-sm text-neutral-600">
                    Matrícula activa en el ciclo lectivo del contexto. Use el icono de carga manual para registrar adeudos por alumno.
                </p>
            </div>

            @if ($totalAlumnos === 0)
                <div class="px-6 py-12 text-center">
                    <p class="text-sm text-neutral-600">
                        @if (strlen(trim($buscar)) >= 2)
                            No hay alumnos que coincidan con la búsqueda.
                        @else
                            No hay alumnos matriculados en este ciclo lectivo.
                        @endif
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
                                        ])
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        @include('livewire.examenes.partials.materias-adeudadas-accion', [
                                            'titulo' => 'Inscribir',
                                            'descripcion' => 'Inscribir al alumno a rendir exámenes',
                                            'icono' => 'inscribir',
                                            'navDestino' => 'examenes.materias-adeudadas.gestion.inscribir',
                                            'idLegajos' => $alumno['idLegajos'],
                                        ])
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        @include('livewire.examenes.partials.materias-adeudadas-accion', [
                                            'titulo' => 'Carga de notas',
                                            'descripcion' => 'Cargar notas de los exámenes',
                                            'icono' => 'notas',
                                            'navDestino' => 'examenes.materias-adeudadas.gestion.notas',
                                            'idLegajos' => $alumno['idLegajos'],
                                        ])
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        @include('livewire.examenes.partials.materias-adeudadas-accion', [
                                            'titulo' => 'Historial de exámenes',
                                            'descripcion' => 'Historial de materias adeudadas, rendiciones y notas',
                                            'icono' => 'historial',
                                            'navDestino' => 'examenes.materias-adeudadas.gestion.historial',
                                            'idLegajos' => $alumno['idLegajos'],
                                        ])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
