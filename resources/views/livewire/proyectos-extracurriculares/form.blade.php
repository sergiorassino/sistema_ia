<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Autogestión docente</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                        {{ $actividadId ? ($soloLectura ? 'Ver proyecto' : 'Editar proyecto') : 'Nuevo proyecto' }}
                    </h2>
                    <p class="text-sm text-white/80">Presentación a dirección · {{ schoolCtx()->nivelNombre() }}</p>
                </div>
                <a href="{{ route('portalDocente.proyectosExtracurriculares.index') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/25">
                    Volver al listado
                </a>
            </div>
        </section>

        <form wire:submit.prevent="guardar" class="space-y-6">
            <div class="se-card space-y-5 p-5 sm:p-6">
                <div>
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Tipo de registro</label>
                    <input type="text" value="{{ $tipoRegistroNombre }}" readonly
                           class="form-input w-full bg-accent-50 text-neutral-700">
                </div>

                <div>
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Actividad</label>
                    <input type="text" wire:model="nombre" maxlength="255"
                           @disabled($soloLectura)
                           class="form-input w-full" autocomplete="off">
                    @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Fechas y horarios</p>
                    <p class="mb-3 text-xs text-neutral-500">Un renglón por cada día. Puede agregar más de uno.</p>
                    @error('fechas') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    <div class="space-y-3">
                        @foreach ($fechas as $i => $fila)
                            <div class="grid gap-2 rounded-2xl border border-accent-200 bg-accent-50/40 p-3 sm:grid-cols-[1fr_7rem_7rem_auto]" wire:key="fecha-{{ $i }}">
                                <div>
                                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Fecha</label>
                                    <input type="date" wire:model="fechas.{{ $i }}.fecha" @disabled($soloLectura) class="form-input w-full">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Inicio</label>
                                    <input type="time" wire:model="fechas.{{ $i }}.hora_inicio" @disabled($soloLectura) class="form-input w-full">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Fin</label>
                                    <input type="time" wire:model="fechas.{{ $i }}.hora_fin" @disabled($soloLectura) class="form-input w-full">
                                </div>
                                @if (! $soloLectura)
                                    <div class="flex items-end">
                                        <button type="button" wire:click="quitarFecha({{ $i }})"
                                                class="inline-flex h-10 items-center rounded-xl bg-white px-3 text-xs font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                                            Quitar
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if (! $soloLectura)
                        <button type="button" wire:click="agregarFecha"
                                class="mt-3 inline-flex items-center rounded-xl bg-white px-3 py-2 text-xs font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50">
                            Agregar día
                        </button>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Lugar</label>
                        <input type="text" wire:model="lugar" maxlength="255" @disabled($soloLectura) class="form-input w-full" autocomplete="off">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Horario (resumen)</label>
                        <input type="text" wire:model="horario" maxlength="255" @disabled($soloLectura)
                               class="form-input w-full" placeholder="Se completa con los horarios de cada día si lo deja vacío">
                    </div>
                </div>
            </div>

            <div class="se-card space-y-4 p-5 sm:p-6">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Grupo involucrado</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="$set('tipo_grupo', 'cursos')" @disabled($soloLectura)
                            @class([
                                'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                'bg-primary-600 text-white shadow-sm' => $tipo_grupo === 'cursos',
                                'bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50' => $tipo_grupo !== 'cursos',
                            ])>
                        Cursos
                    </button>
                    <button type="button" wire:click="$set('tipo_grupo', 'alumnos')" @disabled($soloLectura)
                            @class([
                                'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                'bg-primary-600 text-white shadow-sm' => $tipo_grupo === 'alumnos',
                                'bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50' => $tipo_grupo !== 'alumnos',
                            ])>
                        Alumnos
                    </button>
                </div>

                @if ($tipo_grupo === 'cursos')
                    @error('idsCursos') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @if (! $soloLectura)
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="seleccionarTodosCursos"
                                    class="rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50">
                                Todos los cursos
                            </button>
                            <button type="button" wire:click="limpiarCursos"
                                    class="rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-neutral-600 ring-1 ring-accent-200 hover:bg-accent-50">
                                Limpiar
                            </button>
                        </div>
                    @endif
                    <div class="grid max-h-64 gap-2 overflow-y-auto rounded-2xl border border-accent-200 p-3 sm:grid-cols-2">
                        @foreach ($cursos as $curso)
                            @php $cid = (int) $curso->Id; @endphp
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50">
                                <input type="checkbox"
                                       value="{{ $cid }}"
                                       wire:click="toggleCurso({{ $cid }})"
                                       @checked(in_array($cid, $idsCursos, true))
                                       @disabled($soloLectura)
                                       class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-neutral-800">{{ $curso->nombreParaListado() }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    @error('idsAlumnos') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    <div class="flex flex-wrap gap-2">
                        @foreach ($alumnosElegidos as $al)
                            <span class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-3 py-1 text-xs font-semibold text-neutral-800 ring-1 ring-accent-200" wire:key="al-{{ $al['id'] }}">
                                {{ $al['label'] }}
                                @if (! $soloLectura)
                                    <button type="button" wire:click="quitarAlumno({{ $al['id'] }})" class="text-neutral-500 hover:text-red-700" aria-label="Quitar">×</button>
                                @endif
                            </span>
                        @endforeach
                    </div>
                    @if (! $soloLectura)
                        <button type="button" wire:click="abrirModalAlumnos"
                                class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50">
                            Elegir alumnos
                        </button>
                    @endif
                @endif
            </div>

            <div class="se-card space-y-4 p-5 sm:p-6">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Docentes</p>
                <div class="relative max-w-sm">
                    <input type="search" wire:model.live.debounce.300ms="filtroDocente"
                           placeholder="Filtrar docentes…" class="form-input w-full" autocomplete="off" @disabled($soloLectura)>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Docente a cargo</p>
                        @error('idsDocentesACargo') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                        <div class="max-h-56 space-y-1 overflow-y-auto rounded-2xl border border-accent-200 p-2">
                            @foreach ($docentes as $d)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50">
                                    <input type="checkbox" wire:click="toggleDocenteACargo({{ $d['id'] }})"
                                           @checked(in_array((int) $d['id'], $idsDocentesACargo, true))
                                           @disabled($soloLectura)
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm">{{ $d['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Otros docentes</p>
                        <div class="max-h-56 space-y-1 overflow-y-auto rounded-2xl border border-accent-200 p-2">
                            @foreach ($docentes as $d)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50">
                                    <input type="checkbox" wire:click="toggleOtroDocente({{ $d['id'] }})"
                                           @checked(in_array((int) $d['id'], $idsOtrosDocentes, true))
                                           @disabled($soloLectura)
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm">{{ $d['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="se-card space-y-4 p-5 sm:p-6">
                <div>
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Breve descripción</label>
                    <textarea wire:model="descripcion" rows="10" @disabled($soloLectura)
                              class="form-input w-full leading-relaxed"></textarea>
                    @error('descripcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Evaluación</label>
                    <textarea wire:model="evaluacion" rows="4" @disabled($soloLectura)
                              class="form-input w-full leading-relaxed"></textarea>
                </div>
            </div>

            @if (! $soloLectura)
                <div class="flex flex-wrap justify-end gap-2">
                    <a href="{{ route('portalDocente.proyectosExtracurriculares.index') }}"
                       class="inline-flex items-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="inline-flex items-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        {{ $actividadId ? 'Guardar cambios' : 'Presentar a dirección' }}
                    </button>
                </div>
            @endif
        </form>
    </div>

    @teleport('body')
        @if ($modalAlumnos)
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="ext-alumnos-title">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalAlumnos"></div>
                <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                        <h3 id="ext-alumnos-title" class="text-lg font-bold text-neutral-900">Elegir alumnos</h3>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                        <input type="search" wire:model.live.debounce.300ms="filtroAlumno"
                               placeholder="Apellido, nombre o DNI…" class="form-input mb-3 w-full" autocomplete="off">
                        <div class="space-y-1">
                            @forelse ($alumnosBusqueda as $al)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50">
                                    <input type="checkbox" wire:click="toggleAlumno({{ $al['id'] }})"
                                           @checked(in_array((int) $al['id'], $idsAlumnos, true))
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm">{{ $al['label'] }}</span>
                                    @if (($al['dni'] ?? '') !== '')
                                        <span class="text-xs tabular-nums text-neutral-500">{{ $al['dni'] }}</span>
                                    @endif
                                </label>
                            @empty
                                <p class="py-6 text-center text-sm text-neutral-500">Escriba para buscar alumnos regulares del ciclo.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="shrink-0 border-t border-accent-200 bg-accent-50 px-5 py-3 text-right">
                        <button type="button" wire:click="cerrarModalAlumnos"
                                class="inline-flex items-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Listo
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', (e) => window.seSwalExito(e.mensaje));
        $wire.on('se-swal-error', (e) => window.seSwalError(e.mensaje));
    </script>
    @endscript
</div>
