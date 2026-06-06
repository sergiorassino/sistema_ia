<div class="se-page">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
             class="se-soft-card flex items-start gap-3 border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Profesores por curso · ppc</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Asignación docente por materia</h2>
                <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}</p>
                <p class="text-xs text-white/65 max-w-xl">
                    Asignatura y curso (izquierda). Docentes dados de alta como tal (tipo distinto de «Sin Rol»): agregar y quitar vínculos en <strong class="font-semibold">ppc</strong>.
                </p>
            </div>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 sm:flex-row sm:items-end">
        <div class="w-full max-w-xl">
            <label for="se-ppc-curso" class="form-label">Curso</label>
            <select id="se-ppc-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full">
                <option value="">— Seleccione curso —</option>
                @foreach ($cursos as $c)
                    @php
                        $label = trim((string) ($c->cursec ?? ''));
                        $extra = collect([$c->c ?? null, $c->s ?? null, $c->turnoClase?->nombre ?? null])
                            ->filter(fn ($v) => $v !== null && trim((string) $v) !== '')
                            ->implode(' ');
                        $display = $label !== '' ? $label : ('Curso ' . $c->Id);
                    @endphp
                    <option value="{{ $c->Id }}">
                        {{ $c->Id }} — {{ $display }}{{ $extra !== '' ? ' · ' . $extra : '' }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    {{--
    Panel depuración SQL — desactivado con ProfesoresPorMateriaIndex (§10 docs). wire:click a consultaEjecutadaClic requiere la propiedad Livewire activa.

    @if (($consultaEjecutadaClic ?? '') !== '')
        <div class="se-card overflow-hidden border-dashed border-primary-400/40 bg-accent-50/60">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-accent-200 px-4 py-2.5">
                <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-600">
                    Consultas SQL tras el clic en materia (depuración)
                </h3>
                <button type="button" wire:click="$set('consultaEjecutadaClic', null)"
                        class="rounded-lg border border-accent-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-neutral-600 hover:bg-accent-50">
                    Ocultar
                </button>
            </div>
            <pre class="max-h-80 overflow-auto whitespace-pre-wrap break-words px-4 py-3 font-mono text-[11px] leading-relaxed text-neutral-800">{{ $consultaEjecutadaClic }}</pre>
        </div>
    @endif
    --}}

    <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
        {{-- Materias por curso --}}
        <div class="lg:col-span-5">
            <div class="se-card overflow-hidden">
                <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3">
                    <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-500">Asignaturas del curso</h3>
                </div>
                @if (! $cursoId)
                    <p class="px-4 py-8 text-center text-sm text-neutral-500">Seleccione un curso para listar materias.</p>
                @elseif ($materias->isEmpty())
                    <p class="px-4 py-8 text-center text-sm text-neutral-500">No hay materias para este curso en el ciclo lectivo.</p>
                @else
                    <div class="w-full overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                            <tr class="border-b border-accent-100 bg-white text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                                <th class="px-3 py-2.5 w-16">Ord</th>
                                <th class="px-3 py-2.5">Materia</th>
                                <th class="px-3 py-2.5 w-28 text-right">Docentes</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-accent-100 bg-white">
                            @foreach ($materias as $mat)
                                @php
                                    $isActive = (int) ($selectedMateriaId ?? 0) === (int) $mat->id;
                                    $nDoc = (int) ($countsAsignaciones[(int) $mat->id] ?? 0);
                                @endphp
                                <tr wire:key="mat-{{ $mat->id }}"
                                    wire:click="selectMateria({{ (int) $mat->id }})"
                                    role="button"
                                    tabindex="0"
                                    @class([
                                        'cursor-pointer transition-colors hover:bg-accent-50/90',
                                        'bg-primary-50 ring-2 ring-inset ring-primary-400/30' => $isActive,
                                    ])>
                                    <td class="px-3 py-2 font-mono text-neutral-600">{{ (int) $mat->ord }}</td>
                                    <td class="px-3 py-2 text-neutral-800">
                                        <span class="font-medium">{{ $mat->materia }}</span>
                                        @if (trim((string) ($mat->abrev ?? '')) !== '')
                                            <span class="ml-2 text-neutral-400">({{ $mat->abrev }})</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <span class="se-pill text-[11px] tabular-nums">{{ $nDoc }}</span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Profesores asignados --}}
        <div class="lg:col-span-7">
            <div class="se-card overflow-hidden">
                <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3">
                    <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-500">
                        Docentes asignados · ppc
                    </h3>
                    @if ($selectedMateria)
                        <p class="mt-1 text-sm font-medium text-neutral-800">{{ $selectedMateria->materia }}</p>
                    @endif
                </div>

                @if (! $selectedMateriaId || ! $selectedMateria)
                    <p class="px-4 py-8 text-center text-sm text-neutral-500">Seleccione una materia a la izquierda.</p>
                @else
                    @if (tienePermiso(\App\Support\PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO))
                        <div class="border-b border-accent-100 bg-white px-4 py-4">
                            <p class="form-label">Agregar docente</p>
                            <div class="mt-1.5 flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div class="min-w-0 flex-1">
                                    <label for="se-ppc-nuevo-prof" class="form-label">Docente</label>
                                    <select id="se-ppc-nuevo-prof" wire:model="nuevoProfesorId" class="form-select mt-1.5 w-full @error('nuevoProfesorId') ring-2 ring-red-400 @enderror">
                                        <option value="">— Elegir docente —</option>
                                        @foreach ($elegiblesParaSelect as $prof)
                                            <option value="{{ (int) $prof->id }}">
                                                {{ trim(((string) $prof->apellido).', '.((string) $prof->nombre)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('nuevoProfesorId')
                                        <div class="mt-1 text-[11px] text-red-700">{{ $message }}</div>
                                    @enderror
                                    @error('selectedMateriaId')
                                        <div class="mt-1 text-[11px] text-red-700">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="min-w-0 flex-1">
                                    <label for="se-ppc-nuevo-siturev" class="form-label">Situación de revista</label>
                                    <select id="se-ppc-nuevo-siturev" wire:model="nuevaSituRevisId" class="form-select mt-1.5 w-full @error('nuevaSituRevisId') ring-2 ring-red-400 @enderror">
                                        <option value="">— Elegir situación —</option>
                                        @foreach ($situacionesRevista as $sr)
                                            <option value="{{ (int) $sr->id }}">{{ $sr->sitRev }}</option>
                                        @endforeach
                                    </select>
                                    @error('nuevaSituRevisId')
                                        <div class="mt-1 text-[11px] text-red-700">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="button" wire:click="agregarProfesor"
                                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                                    Asignar
                                </button>
                            </div>
                            @if ($elegiblesParaSelect->isEmpty())
                                <p class="mt-2 text-xs text-neutral-500">No hay docentes disponibles (todos están asignados o no cumplen el criterio de tipo).</p>
                            @endif
                        </div>
                    @endif

                    @if ($asignados->isEmpty())
                        <p class="px-4 py-8 text-center text-sm text-neutral-500">Sin docentes en esta materia.</p>
                    @else
                        <div class="w-full overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                <tr class="border-b border-accent-100 bg-white text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                                    <th class="px-4 py-2.5">Apellido y nombre</th>
                                    <th class="px-4 py-2.5 w-60">Situación de revista</th>
                                    @if (tienePermiso(\App\Support\PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO))
                                        <th class="px-4 py-2.5 w-36 text-right">Acción</th>
                                    @endif
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-accent-100 bg-white">
                                @foreach ($asignados as $row)
                                    <tr wire:key="ppc-{{ $row->ppcId }}" class="hover:bg-accent-50/60 transition-colors">
                                        <td class="px-4 py-2.5 text-neutral-800">
                                            {{ trim(((string) $row->apellido).', '.((string) $row->nombre)) }}
                                            <span class="ml-2 font-mono text-xs text-neutral-400">#{{ (int) $row->idProfesor }}</span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            @if (tienePermiso(\App\Support\PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO))
                                                <select
                                                    class="form-select w-full text-xs"
                                                    x-on:change="$wire.actualizarSituacionRevista({{ (int) $row->ppcId }}, $event.target.value)">
                                                    <option value="" @if ((int) $row->idSituRevis === 0) selected @endif>— Sin definir —</option>
                                                    @foreach ($situacionesRevista as $sr)
                                                        <option value="{{ (int) $sr->id }}" @if ((int) $row->idSituRevis === (int) $sr->id) selected @endif>
                                                            {{ $sr->sitRev }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="text-neutral-700">
                                                    {{ trim((string) ($row->sitRev ?? '')) !== '' ? $row->sitRev : '—' }}
                                                </span>
                                            @endif
                                        </td>
                                        @if (tienePermiso(\App\Support\PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO))
                                            <td class="px-4 py-2.5 text-right">
                                                <button type="button" wire:click="confirmarQuitarProfesor({{ (int) $row->ppcId }})"
                                                        class="inline-flex items-center rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50">
                                                    Quitar
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @if ($showConfirmQuitar)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-neutral-900/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                            <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="mb-1 text-base font-semibold text-neutral-800">Quitar asignación</h3>
                            <p class="text-sm text-neutral-600">
                                ¿Quitar al docente de la materia?
                                @if ($quitarPpcInfo !== '')
                                    <span class="mt-1 block font-semibold text-neutral-800">{{ $quitarPpcInfo }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/70 px-6 py-4">
                    <button type="button" wire:click="cerrarConfirmQuitar" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="quitarProfesor" wire:loading.attr="disabled" class="btn-danger">
                        <span wire:loading.remove wire:target="quitarProfesor">Quitar</span>
                        <span wire:loading wire:target="quitarProfesor">Quitando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
