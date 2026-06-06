<div class="se-page max-w-6xl">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Planes y cursos modelo</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $id ? 'Editar curso modelo' : 'Nuevo curso modelo' }}</h2>
                <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}</p>
                <p class="text-xs text-white/65">Campos marcados como obligatorios deben completarse antes de guardar.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('abm.curplan') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                    Volver
                </a>
                <button type="button" wire:click="save" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Guardar</span>
                    <span wire:loading wire:target="save">Guardando…</span>
                </button>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden p-5 sm:p-6">
        <div class="mb-5 flex flex-col gap-1 border-b border-accent-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="se-section-title">Curso y materias del plan</p>
                @if ($id)
                    <p class="mt-1 text-xs text-neutral-500">ID #{{ $id }}</p>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-accent-200 bg-accent-50/40 p-4 sm:p-5">
        <p class="mb-4 text-[11px] font-bold uppercase tracking-[0.12em] text-neutral-500">Datos del curso modelo (CurPlan)</p>
        {{-- Datos del curso modelo --}}
        <div class="gf w-full">
            <div class="gf-row @error('idPlan') gf-cell-err @enderror">
                <div class="gf-label gf-label-req w-40">Plan</div>
                <div class="gf-cell @error('idPlan') gf-cell-err @enderror">
                    <select wire:model="idPlan" class="gf-select @error('idPlan') gf-select-err @enderror">
                        <option value="">— Seleccione —</option>
                        @foreach ($planes as $p)
                            <option value="{{ $p->id }}">
                                {{ $p->abrev ? ($p->abrev . ' · ') : '' }}{{ $p->plan }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            @error('idPlan')
                <div class="gf-error-row">
                    <div class="gf-error-spacer w-40"></div>
                    <div class="gf-error-msg">{{ $message }}</div>
                </div>
            @enderror

            <div class="gf-row @error('curPlanCurso') gf-cell-err @enderror">
                <div class="gf-label gf-label-req w-40">Curso modelo</div>
                <div class="gf-cell @error('curPlanCurso') gf-cell-err @enderror">
                    <input wire:model="curPlanCurso" type="text" maxlength="30"
                           placeholder="Ej: PRIMERO (PRIM)"
                           class="gf-input @error('curPlanCurso') gf-input-err @enderror">
                </div>
            </div>
            @error('curPlanCurso')
                <div class="gf-error-row">
                    <div class="gf-error-spacer w-40"></div>
                    <div class="gf-error-msg">{{ $message }}</div>
                </div>
            @enderror
        </div>
        </div>

        {{-- Materias del plan (MatPlan) --}}
        <div class="mt-6 border-t border-accent-100 pt-6">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="se-section-title">Materias asociadas</p>
                    <p class="mt-1 text-xs text-neutral-500">Tabla <span class="font-mono text-neutral-600">matplan</span></p>
                </div>
                <button type="button" wire:click="openMateriaCreate" class="btn-secondary btn-sm" @disabled(! $id)>
                    + Agregar
                </button>
            </div>

            @if (! $id)
                <div class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3">
                    Primero guardá el curso modelo para poder cargar materias.
                </div>
            @else
                <div class="w-full overflow-x-auto rounded-xl border border-accent-200">
                    <div class="flex justify-start">
                        <div class="gf min-w-[900px] w-full">
                            <div class="gf-head">
                                <div class="gf-th w-20">Ord</div>
                                <div class="gf-th flex-1 min-w-[18rem]">Materia</div>
                                <div class="gf-th w-24">Abrev</div>
                                <div class="gf-th w-28">CodGE</div>
                                <div class="gf-th w-28">CodGE2</div>
                                <div class="gf-th w-28">CodGE3</div>
                                <div class="gf-th-right w-52">Acciones</div>
                            </div>

                            @forelse ($materias as $m)
                                <div class="gf-row gf-row-hover">
                                    <div class="gf-td w-20 font-mono text-neutral-700">
                                        @if ($matEditingId === $m->id)
                                            <input wire:model.defer="matDraft.ord" type="text" inputmode="numeric" maxlength="3"
                                                   class="gf-inline font-mono text-neutral-700 @error('matDraft.ord') ring-2 ring-red-400 @enderror">
                                            @error('matDraft.ord') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                        @else
                                            {{ $m->ord }}
                                        @endif
                                    </div>
                                    <div class="gf-td flex-1 min-w-[18rem] text-neutral-800">
                                        @if ($matEditingId === $m->id)
                                            <input wire:model.defer="matDraft.matPlanMateria" type="text" maxlength="70"
                                                   class="gf-inline text-neutral-800 @error('matDraft.matPlanMateria') ring-2 ring-red-400 @enderror">
                                            @error('matDraft.matPlanMateria') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                        @else
                                            <div class="font-medium">{{ $m->matPlanMateria }}</div>
                                        @endif
                                    </div>
                                    <div class="gf-td w-24 font-mono text-xs text-neutral-700">
                                        @if ($matEditingId === $m->id)
                                            <input wire:model.defer="matDraft.abrev" type="text" maxlength="5"
                                                   class="gf-inline font-mono text-neutral-700 @error('matDraft.abrev') ring-2 ring-red-400 @enderror">
                                            @error('matDraft.abrev') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                        @else
                                            {{ $m->abrev }}
                                        @endif
                                    </div>
                                    <div class="gf-td w-28 font-mono text-xs text-neutral-700">
                                        @if ($matEditingId === $m->id)
                                            <input wire:model.defer="matDraft.codGE" type="text" maxlength="15"
                                                   class="gf-inline font-mono text-neutral-700 @error('matDraft.codGE') ring-2 ring-red-400 @enderror">
                                            @error('matDraft.codGE') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                        @else
                                            {{ $m->codGE }}
                                        @endif
                                    </div>
                                    <div class="gf-td w-28 font-mono text-xs text-neutral-700">
                                        @if ($matEditingId === $m->id)
                                            <input wire:model.defer="matDraft.codGE2" type="text" maxlength="15"
                                                   class="gf-inline font-mono text-neutral-700 @error('matDraft.codGE2') ring-2 ring-red-400 @enderror">
                                            @error('matDraft.codGE2') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                        @else
                                            {{ $m->codGE2 }}
                                        @endif
                                    </div>
                                    <div class="gf-td w-28 font-mono text-xs text-neutral-700">
                                        @if ($matEditingId === $m->id)
                                            <input wire:model.defer="matDraft.codGE3" type="text" maxlength="15"
                                                   class="gf-inline font-mono text-neutral-700 @error('matDraft.codGE3') ring-2 ring-red-400 @enderror">
                                            @error('matDraft.codGE3') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                        @else
                                            {{ $m->codGE3 }}
                                        @endif
                                    </div>

                                    <div class="gf-td-actions w-52 whitespace-nowrap">
                                        @if ($matEditingId === $m->id)
                                            <button type="button" wire:click="saveMateriaRow" wire:loading.attr="disabled" class="btn-primary btn-sm">
                                                <span wire:loading.remove wire:target="saveMateriaRow">Guardar</span>
                                                <span wire:loading wire:target="saveMateriaRow">…</span>
                                            </button>
                                            <button type="button" wire:click="cancelMateriaEdit" class="btn-secondary btn-sm">Cancelar</button>
                                        @else
                                            <button type="button" wire:click="openMateriaEdit({{ $m->id }})" class="btn-secondary btn-sm">Editar</button>
                                            <button type="button" wire:click="confirmDeleteMateria({{ $m->id }})" class="btn-danger btn-sm">Eliminar</button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="gf-empty">No hay materias asociadas.</div>
                            @endforelse

                            {{-- Fila de creación --}}
                            @if ($matEditingId === 0)
                                <div class="gf-row bg-accent-50/70" wire:key="matplan-create-row-{{ $id }}">
                                    <div class="gf-td w-20 font-mono text-neutral-700">
                                        <input wire:model.defer="matDraft.ord" type="text" inputmode="numeric" maxlength="3"
                                               class="gf-inline font-mono text-neutral-700 @error('matDraft.ord') ring-2 ring-red-400 @enderror"
                                               placeholder="0">
                                        @error('matDraft.ord') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="gf-td flex-1 min-w-[18rem] text-neutral-800">
                                        <input wire:model.defer="matDraft.matPlanMateria" type="text" maxlength="70"
                                               class="gf-inline text-neutral-800 @error('matDraft.matPlanMateria') ring-2 ring-red-400 @enderror"
                                               placeholder="Nueva materia…">
                                        @error('matDraft.matPlanMateria') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="gf-td w-24 font-mono text-xs text-neutral-700">
                                        <input wire:model.defer="matDraft.abrev" type="text" maxlength="5"
                                               class="gf-inline font-mono text-neutral-700 @error('matDraft.abrev') ring-2 ring-red-400 @enderror"
                                               placeholder="Abrev">
                                        @error('matDraft.abrev') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="gf-td w-28 font-mono text-xs text-neutral-700">
                                        <input wire:model.defer="matDraft.codGE" type="text" maxlength="15"
                                               class="gf-inline font-mono text-neutral-700 @error('matDraft.codGE') ring-2 ring-red-400 @enderror"
                                               placeholder="CodGE">
                                        @error('matDraft.codGE') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="gf-td w-28 font-mono text-xs text-neutral-700">
                                        <input wire:model.defer="matDraft.codGE2" type="text" maxlength="15"
                                               class="gf-inline font-mono text-neutral-700 @error('matDraft.codGE2') ring-2 ring-red-400 @enderror"
                                               placeholder="CodGE2">
                                        @error('matDraft.codGE2') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="gf-td w-28 font-mono text-xs text-neutral-700">
                                        <input wire:model.defer="matDraft.codGE3" type="text" maxlength="15"
                                               class="gf-inline font-mono text-neutral-700 @error('matDraft.codGE3') ring-2 ring-red-400 @enderror"
                                               placeholder="CodGE3">
                                        @error('matDraft.codGE3') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="gf-td-actions w-52 whitespace-nowrap">
                                        <button type="button" wire:click="saveMateriaRow" wire:loading.attr="disabled" class="btn-primary btn-sm">
                                            <span wire:loading.remove wire:target="saveMateriaRow">Agregar</span>
                                            <span wire:loading wire:target="saveMateriaRow">…</span>
                                        </button>
                                        <button type="button" wire:click="cancelMateriaEdit" class="btn-secondary btn-sm">Cancelar</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Confirm delete materia --}}
    @if ($showMatConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-2xl border border-accent-200 bg-white shadow-xl" @click.stop>
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                            <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="mb-1 text-base font-semibold text-neutral-900">Confirmar eliminación</h3>
                            <p class="text-sm text-neutral-600">{{ $matDeleteInfo }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/60 px-6 py-4">
                    <button type="button" wire:click="$set('showMatConfirm', false)" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="deleteMateria" wire:loading.attr="disabled" class="btn-danger">
                        <span wire:loading.remove wire:target="deleteMateria">Eliminar</span>
                        <span wire:loading wire:target="deleteMateria">Eliminando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

