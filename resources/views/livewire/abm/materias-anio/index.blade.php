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
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Asignaturas del año</h2>
                <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}</p>
            </div>
            @if (! $creating)
                <button type="button" wire:click="startCreate"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                    + Nueva materia
                </button>
            @endif
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 sm:flex-row sm:items-end">
        <div class="w-full max-w-xl">
            <label for="se-mat-curso" class="form-label">Curso</label>
            <select id="se-mat-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full">
                <option value="">— Seleccione curso —</option>
                @foreach ($cursos as $c)
                    @php
                        $label = trim((string) ($c->cursec ?? ''));
                        $turnoEtq = $c->turnoClase?->nombre;
                        $extra = collect([$c->c ?? null, $c->s ?? null, $turnoEtq])->filter(fn ($v) => $v !== null && trim((string) $v) !== '')->implode(' ');
                        $display = $label !== '' ? $label : ('Curso ' . $c->Id);
                    @endphp
                    <option value="{{ $c->Id }}">
                        {{ $c->Id }} — {{ $display }}{{ $extra !== '' ? ' · ' . $extra : '' }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="se-card overflow-hidden p-2 sm:p-3">
        <div class="gf gf-materias-anio gf-vcenter" wire:key="materias-anio-curso-{{ (int) ($cursoId ?? 0) }}">
            <div class="gf-head">
                <div class="gf-th gf-ma-col-id" title="id">ID</div>
                <div class="gf-th gf-ma-col-ord" title="ord">Ord</div>
                <div class="gf-th gf-ma-col-fk-xs" title="idNivel">Niv</div>
                <div class="gf-th gf-ma-col-fk" title="idCursos">Curso</div>
                <div class="gf-th gf-ma-col-fk-xs" title="idTerlec">Ter</div>
                <div class="gf-th gf-ma-col-fk" title="idCurPlan">CPl</div>
                <div class="gf-th gf-ma-col-fk" title="idMatPlan">MPl</div>
                <div class="gf-th gf-ma-col-materia">Materia</div>
                <div class="gf-th gf-ma-col-abrev">Abrev</div>
                @if ($tieneEsInstitucional)
                    <div class="gf-th gf-ma-col-flag" title="Materia extracurricular (boletín IPE San José, máx. 2 por curso)">Extr.</div>
                @endif
                @if ($tieneInfoCalif)
                    <div class="gf-th gf-ma-col-flag" title="La materia aparece en síntesis y calificaciones del informe">Inf.</div>
                @endif
                <div class="gf-th-right gf-ma-col-acciones">Acciones</div>
            </div>

            @if ($creating)
                <div class="gf-row gf-row-hover bg-amber-50/40" wire:key="materias-anio-create-row">
                    <div class="gf-td gf-ma-col-id font-mono text-neutral-500">—</div>

                    <div class="gf-td gf-ma-col-ord">
                        <input type="text" inputmode="numeric" maxlength="3" wire:model.defer="create.ord"
                               class="gf-inline font-mono text-neutral-700 @error('create.ord') ring-2 ring-red-400 @enderror">
                        @error('create.ord') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="gf-td gf-ma-col-fk-xs">
                        <div class="font-mono text-neutral-700" title="idNivel">{{ $create['idNivel'] ?? '—' }}</div>
                        @error('create.idNivel') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="gf-td gf-ma-col-fk">
                        <select wire:model.defer="create.idCursos"
                                class="gf-inline-select font-mono text-neutral-700 @error('create.idCursos') ring-2 ring-red-400 @enderror">
                            <option value="">—</option>
                            @foreach ($cursos as $c)
                                <option value="{{ $c->Id }}">{{ $c->Id }}</option>
                            @endforeach
                        </select>
                        @error('create.idCursos') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="gf-td gf-ma-col-fk-xs">
                        <div class="font-mono text-neutral-700" title="idTerlec">{{ $create['idTerlec'] ?? '—' }}</div>
                        @error('create.idTerlec') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="gf-td gf-ma-col-fk">
                        <select wire:model.live="create.idCurPlan"
                                class="gf-inline-select font-mono text-neutral-700 @error('create.idCurPlan') ring-2 ring-red-400 @enderror">
                            <option value="">—</option>
                            @foreach ($curplanes as $cp)
                                <option value="{{ $cp->id }}">{{ $cp->id }}</option>
                            @endforeach
                        </select>
                        @error('create.idCurPlan') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="gf-td gf-ma-col-fk">
                        @php $cpId = (int) ($create['idCurPlan'] ?? 0); @endphp
                        <select wire:model.defer="create.idMatPlan"
                                class="gf-inline-select font-mono text-neutral-700 @error('create.idMatPlan') ring-2 ring-red-400 @enderror">
                            <option value="">—</option>
                            @foreach (($matplanesByCurplan[$cpId] ?? collect()) as $mp)
                                <option value="{{ $mp->id }}">{{ $mp->id }}</option>
                            @endforeach
                        </select>
                        @error('create.idMatPlan') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="gf-td gf-ma-col-materia">
                        <input type="text" maxlength="70" wire:model.defer="create.materia"
                               class="gf-inline text-neutral-700 @error('create.materia') ring-2 ring-red-400 @enderror">
                        @error('create.materia') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="gf-td gf-ma-col-abrev">
                        <input type="text" maxlength="5" wire:model.defer="create.abrev"
                               class="gf-inline font-mono text-neutral-700 @error('create.abrev') ring-2 ring-red-400 @enderror">
                        @error('create.abrev') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                    </div>

                    @if ($tieneEsInstitucional)
                        <div class="gf-td gf-ma-col-flag">
                            <label class="inline-flex items-center justify-center" title="Extracurricular">
                                <input type="checkbox" wire:model.defer="create.esInstitucional"
                                       class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                            </label>
                        </div>
                    @endif

                    @if ($tieneInfoCalif)
                        <div class="gf-td gf-ma-col-flag">
                            <label class="inline-flex items-center justify-center" title="Al informe">
                                <input type="checkbox" wire:model.defer="create.infoCalif"
                                       class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                            </label>
                        </div>
                    @endif

                    <div class="gf-td-actions gf-ma-col-acciones">
                        <button type="button" wire:click="saveCreate" wire:loading.attr="disabled" class="btn-primary btn-sm">
                            <span wire:loading.remove wire:target="saveCreate">Guardar</span>
                            <span wire:loading wire:target="saveCreate">…</span>
                        </button>
                        <button type="button" wire:click="cancelCreate" class="btn-secondary btn-sm">Cancelar</button>
                    </div>
                </div>
            @endif

            @forelse ($materias as $m)
                <div class="gf-row gf-row-hover" wire:key="materia-anio-row-{{ $m->id }}">
                    <div class="gf-td gf-ma-col-id font-mono text-neutral-600">{{ $m->id }}</div>

                    <div class="gf-td gf-ma-col-ord">
                        @if ($editingId === $m->id)
                            <input type="text" inputmode="numeric" maxlength="3"
                                   wire:model.defer="draft.{{ $m->id }}.ord"
                                   class="gf-inline font-mono text-neutral-700 @error('draft.'.$m->id.'.ord') ring-2 ring-red-400 @enderror">
                            @error('draft.'.$m->id.'.ord') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="font-mono text-neutral-700">{{ $m->ord }}</div>
                        @endif
                    </div>

                    <div class="gf-td gf-ma-col-fk-xs">
                        <div class="font-mono text-neutral-700" title="idNivel">{{ $m->idNivel }}</div>
                    </div>

                    <div class="gf-td gf-ma-col-fk">
                        @if ($editingId === $m->id)
                            <select wire:model.defer="draft.{{ $m->id }}.idCursos"
                                    class="gf-inline-select font-mono text-neutral-700 @error('draft.'.$m->id.'.idCursos') ring-2 ring-red-400 @enderror">
                                @foreach ($cursos as $c)
                                    <option value="{{ $c->Id }}">{{ $c->Id }}</option>
                                @endforeach
                            </select>
                            @error('draft.'.$m->id.'.idCursos') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="font-mono text-neutral-700" title="idCursos">{{ $m->idCursos }}</div>
                        @endif
                    </div>

                    <div class="gf-td gf-ma-col-fk-xs">
                        <div class="font-mono text-neutral-700" title="idTerlec">{{ $m->idTerlec }}</div>
                    </div>

                    <div class="gf-td gf-ma-col-fk">
                        @if ($editingId === $m->id)
                            <select wire:model.live="draft.{{ $m->id }}.idCurPlan"
                                    class="gf-inline-select font-mono text-neutral-700 @error('draft.'.$m->id.'.idCurPlan') ring-2 ring-red-400 @enderror">
                                @foreach ($curplanes as $cp)
                                    <option value="{{ $cp->id }}">{{ $cp->id }}</option>
                                @endforeach
                            </select>
                            @error('draft.'.$m->id.'.idCurPlan') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="font-mono text-neutral-700" title="idCurPlan">{{ $m->idCurPlan }}</div>
                        @endif
                    </div>

                    <div class="gf-td gf-ma-col-fk">
                        @if ($editingId === $m->id)
                            @php $cpId = (int) ($draft[$m->id]['idCurPlan'] ?? 0); @endphp
                            <select wire:model.defer="draft.{{ $m->id }}.idMatPlan"
                                    class="gf-inline-select font-mono text-neutral-700 @error('draft.'.$m->id.'.idMatPlan') ring-2 ring-red-400 @enderror">
                                @foreach (($matplanesByCurplan[$cpId] ?? collect()) as $mp)
                                    <option value="{{ $mp->id }}">{{ $mp->id }}</option>
                                @endforeach
                            </select>
                            @error('draft.'.$m->id.'.idMatPlan') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="font-mono text-neutral-700" title="idMatPlan">{{ $m->idMatPlan }}</div>
                        @endif
                    </div>

                    <div class="gf-td gf-ma-col-materia">
                        @if ($editingId === $m->id)
                            <input type="text" maxlength="70"
                                   wire:model.defer="draft.{{ $m->id }}.materia"
                                   class="gf-inline text-neutral-700 @error('draft.'.$m->id.'.materia') ring-2 ring-red-400 @enderror">
                            @error('draft.'.$m->id.'.materia') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="text-neutral-800" title="{{ $m->materia }}">{{ $m->materia }}</div>
                        @endif
                    </div>

                    <div class="gf-td gf-ma-col-abrev">
                        @if ($editingId === $m->id)
                            <input type="text" maxlength="5"
                                   wire:model.defer="draft.{{ $m->id }}.abrev"
                                   class="gf-inline font-mono text-neutral-700 @error('draft.'.$m->id.'.abrev') ring-2 ring-red-400 @enderror">
                            @error('draft.'.$m->id.'.abrev') <div class="text-[10px] text-red-700 mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="font-mono text-neutral-700">{{ $m->abrev ?: '—' }}</div>
                        @endif
                    </div>

                    @if ($tieneEsInstitucional)
                        <div class="gf-td gf-ma-col-flag">
                            @if ($editingId === $m->id)
                                <label class="inline-flex items-center justify-center" title="Extracurricular">
                                    <input type="checkbox" wire:model.defer="draft.{{ $m->id }}.esInstitucional"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                </label>
                            @else
                                <label class="inline-flex cursor-pointer items-center justify-center" title="Marcar como materia extracurricular (máx. 2 en el boletín IPE)">
                                    <input type="checkbox"
                                           wire:key="materia-anio-instit-{{ $m->id }}-{{ (int) ($m->esInstitucional ?? 0) }}"
                                           wire:click="toggleEsInstitucional({{ $m->id }})"
                                           @checked((int) ($m->esInstitucional ?? 0) === 1)
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                </label>
                            @endif
                        </div>
                    @endif

                    @if ($tieneInfoCalif)
                        <div class="gf-td gf-ma-col-flag">
                            @if ($editingId === $m->id)
                                <label class="inline-flex items-center justify-center" title="Al informe">
                                    <input type="checkbox" wire:model.defer="draft.{{ $m->id }}.infoCalif"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                </label>
                            @else
                                <label class="inline-flex cursor-pointer items-center justify-center" title="Marcar si la materia aparece en síntesis y calificaciones del informe">
                                    <input type="checkbox"
                                           wire:key="materia-anio-info-calif-{{ $m->id }}-{{ (int) ($m->infoCalif ?? 0) }}"
                                           wire:click="toggleInfoCalif({{ $m->id }})"
                                           @checked((int) ($m->infoCalif ?? 0) === 1)
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                </label>
                            @endif
                        </div>
                    @endif

                    <div class="gf-td-actions gf-ma-col-acciones">
                        @if ($editingId === $m->id)
                            <button type="button" wire:click="saveRow({{ $m->id }})" wire:loading.attr="disabled" class="btn-primary btn-sm">
                                <span wire:loading.remove wire:target="saveRow({{ $m->id }})">Guardar</span>
                                <span wire:loading wire:target="saveRow({{ $m->id }})">…</span>
                            </button>
                            <button type="button" wire:click="cancelEdit" class="btn-secondary btn-sm">Cancelar</button>
                        @else
                            <button type="button"
                                    wire:click="syncFromMatplan({{ $m->id }})"
                                    wire:loading.attr="disabled"
                                    class="btn-secondary btn-sm"
                                    title="Copiar nombre, abreviatura y orden desde la materia modelo (matplan)">
                                <span wire:loading.remove wire:target="syncFromMatplan({{ $m->id }})">Matplan</span>
                                <span wire:loading wire:target="syncFromMatplan({{ $m->id }})">…</span>
                            </button>
                            <button type="button" wire:click="startEdit({{ $m->id }})" class="btn-secondary btn-sm">Editar</button>
                            <button type="button" wire:click="confirmDelete({{ $m->id }})" class="btn-danger btn-sm">Eliminar</button>
                        @endif
                    </div>
                </div>
            @empty
                @unless ($creating)
                    <div class="gf-empty">No hay materias registradas para el año lectivo actual.</div>
                @endunless
            @endforelse
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

