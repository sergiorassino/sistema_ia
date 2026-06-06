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
                <p class="se-eyebrow">Cursos del año</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Cursos / grados / salas</h2>
                <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}</p>
            </div>
            <button type="button" wire:click="createQuick"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                + Nuevo curso
            </button>
        </div>
    </section>

    <div class="se-card overflow-hidden p-2 sm:p-3">
        <div class="w-full overflow-x-auto">
            <div class="flex justify-start">
                <div class="gf min-w-[1180px]">
            <div class="gf-head">
                <div class="gf-th w-24">ID</div>
                <div class="gf-th w-20">Ord</div>
                <div class="gf-th w-44">Curso modelo (CurPlan)</div>
                <div class="gf-th w-28">Año (Terlec)</div>
                <div class="gf-th w-36">Nivel</div>
                <div class="gf-th w-48">Sección (cursec)</div>
                <div class="gf-th w-16">C</div>
                <div class="gf-th w-16">S</div>
                <div class="gf-th w-36">Turno</div>
                <div class="gf-th-right w-52">Acciones</div>
            </div>

            @forelse ($cursos as $c)
                <div class="gf-row gf-row-hover">
                    <div class="gf-td w-24 font-mono text-neutral-600">{{ $c->Id }}</div>
                    {{-- orden --}}
                    <div class="gf-td w-20">
                        @if ($editingId === $c->Id)
                            <input type="text" inputmode="numeric" maxlength="3"
                                   wire:model.defer="draft.{{ $c->Id }}.orden"
                                   class="gf-inline font-mono text-neutral-700 @error('draft.'.$c->Id.'.orden') ring-2 ring-red-400 @enderror">
                            @error('draft.'.$c->Id.'.orden') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="font-mono text-neutral-600">{{ $c->orden ?? '—' }}</div>
                        @endif
                    </div>

                    {{-- idCurPlan (mostrar valor relacionado) --}}
                    <div class="gf-td w-44">
                        @if ($editingId === $c->Id)
                            <select wire:model.defer="draft.{{ $c->Id }}.idCurPlan"
                                    class="gf-inline-select text-neutral-800 @error('draft.'.$c->Id.'.idCurPlan') ring-2 ring-red-400 @enderror">
                                @foreach ($curplanes as $cp)
                                    <option value="{{ $cp->id }}">
                                        {{ $cp->plan?->abrev ? ($cp->plan->abrev . ' · ') : '' }}{{ $cp->curPlanCurso }}
                                    </option>
                                @endforeach
                            </select>
                            @error('draft.'.$c->Id.'.idCurPlan') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                            <div class="text-[10px] text-neutral-400 mt-1">Al cambiarlo se re-crean las materias del año.</div>
                        @else
                            <div class="font-medium text-neutral-800 truncate">{{ $c->curplan?->curPlanCurso ?? ('#'.$c->idCurPlan) }}</div>
                            <div class="text-xs text-neutral-500 truncate">{{ $c->curplan?->plan?->abrev ?: $c->curplan?->plan?->plan }}</div>
                        @endif
                    </div>

                    {{-- idTerlec (mostrar año) --}}
                    <div class="gf-td w-28">
                        @if ($editingId === $c->Id)
                            <select wire:model.defer="draft.{{ $c->Id }}.idTerlec"
                                    class="gf-inline-select font-mono text-neutral-700 @error('draft.'.$c->Id.'.idTerlec') ring-2 ring-red-400 @enderror">
                                @foreach ($terlecs as $t)
                                    <option value="{{ $t->id }}">{{ $t->ano }}</option>
                                @endforeach
                            </select>
                            @error('draft.'.$c->Id.'.idTerlec') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="font-mono text-neutral-700">{{ $c->terlec?->ano ?? $c->idTerlec }}</div>
                        @endif
                    </div>

                    {{-- idNivel (mostrar nombre) --}}
                    <div class="gf-td w-36">
                        @if ($editingId === $c->Id)
                            <select wire:model.defer="draft.{{ $c->Id }}.idNivel"
                                    class="gf-inline-select text-neutral-800 @error('draft.'.$c->Id.'.idNivel') ring-2 ring-red-400 @enderror">
                                @foreach ($niveles as $n)
                                    <option value="{{ $n->id }}">
                                        {{ $n->abrev ? ($n->abrev . ' · ') : '' }}{{ $n->nivel }}
                                    </option>
                                @endforeach
                            </select>
                            @error('draft.'.$c->Id.'.idNivel') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="text-neutral-800 truncate">{{ $c->nivel?->nivel ?? ('#'.$c->idNivel) }}</div>
                        @endif
                    </div>

                    {{-- cursec --}}
                    <div class="gf-td w-48">
                        @if ($editingId === $c->Id)
                            <input type="text" maxlength="30"
                                   wire:model.defer="draft.{{ $c->Id }}.cursec"
                                   class="gf-inline font-mono text-neutral-700 @error('draft.'.$c->Id.'.cursec') ring-2 ring-red-400 @enderror">
                            @error('draft.'.$c->Id.'.cursec') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="font-mono text-neutral-700">{{ $c->cursec ?? '—' }}</div>
                        @endif
                    </div>

                    {{-- c --}}
                    <div class="gf-td w-16">
                        @if ($editingId === $c->Id)
                            <input type="text" maxlength="1"
                                   wire:model.defer="draft.{{ $c->Id }}.c"
                                   class="gf-inline font-mono text-neutral-700 @error('draft.'.$c->Id.'.c') ring-2 ring-red-400 @enderror">
                            @error('draft.'.$c->Id.'.c') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="font-mono text-neutral-700">{{ $c->c ?? '—' }}</div>
                        @endif
                    </div>

                    {{-- s --}}
                    <div class="gf-td w-16">
                        @if ($editingId === $c->Id)
                            <input type="text" maxlength="1"
                                   wire:model.defer="draft.{{ $c->Id }}.s"
                                   class="gf-inline font-mono text-neutral-700 @error('draft.'.$c->Id.'.s') ring-2 ring-red-400 @enderror">
                            @error('draft.'.$c->Id.'.s') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="font-mono text-neutral-700">{{ $c->s ?? '—' }}</div>
                        @endif
                    </div>

                    {{-- turno (catálogo turnos_clase) --}}
                    <div class="gf-td w-40">
                        @if ($editingId === $c->Id)
                            <select wire:model.defer="draft.{{ $c->Id }}.idTurnoClase"
                                    class="gf-inline-select text-neutral-800 @error('draft.'.$c->Id.'.idTurnoClase') ring-2 ring-red-400 @enderror">
                                <option value="">—</option>
                                @foreach ($turnosClase as $tc)
                                    <option value="{{ $tc->id }}">{{ $tc->nombre }}</option>
                                @endforeach
                            </select>
                            @error('draft.'.$c->Id.'.idTurnoClase') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="text-neutral-700">{{ $c->turnoClase?->nombre ?? '—' }}</div>
                        @endif
                    </div>

                    <div class="gf-td-actions w-52 whitespace-nowrap">
                        @if ($editingId === $c->Id)
                            <button type="button" wire:click="saveRow({{ $c->Id }})" wire:loading.attr="disabled" class="btn-primary btn-sm">
                                <span wire:loading.remove wire:target="saveRow({{ $c->Id }})">Guardar</span>
                                <span wire:loading wire:target="saveRow({{ $c->Id }})">…</span>
                            </button>
                            <button type="button" wire:click="cancelEdit" class="btn-secondary btn-sm">Cancelar</button>
                        @else
                            <button type="button" wire:click="startEdit({{ $c->Id }})" class="btn-secondary btn-sm">Editar</button>
                            <button type="button" wire:click="confirmDelete({{ $c->Id }})" class="btn-danger btn-sm">Eliminar</button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="gf-empty">No hay cursos registrados para el año lectivo actual.</div>
            @endforelse
                </div>
            </div>
        </div>
    </div>

    @if ($showConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-2xl border border-accent-200 bg-white shadow-xl" @click.stop>
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        @if ($deleteId)
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                                <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                            </div>
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100">
                                <svg class="h-5 w-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
                                </svg>
                            </div>
                        @endif
                        <div>
                            <h3 class="mb-1 text-base font-semibold text-neutral-900">
                                {{ $deleteId ? 'Confirmar eliminación' : 'No se puede eliminar' }}
                            </h3>
                            <p class="text-sm text-neutral-600">{{ $deleteInfo }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/60 px-6 py-4">
                    <button type="button" wire:click="$set('showConfirm', false)" class="btn-secondary">
                        {{ $deleteId ? 'Cancelar' : 'Cerrar' }}
                    </button>
                    @if ($deleteId)
                        <button type="button" wire:click="delete" wire:loading.attr="disabled" class="btn-danger">
                            <span wire:loading.remove wire:target="delete">Eliminar</span>
                            <span wire:loading wire:target="delete">Eliminando…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

