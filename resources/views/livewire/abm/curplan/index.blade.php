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

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Planes y cursos modelo</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Cursos modelo (CurPlan)</h2>
                <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}</p>
                <p class="text-xs text-white/65">Definición base por plan; los cursos del año se arman desde «Cursos / grados / salas».</p>
            </div>
            <a href="{{ route('abm.curplan.create') }}"
               class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                + Nuevo curso modelo
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden p-2 sm:p-3">
        <div class="w-full overflow-x-auto">
            <div class="flex justify-start">
                <div class="gf min-w-[640px]">
                    <div class="gf-head">
                        <div class="gf-th w-36">Plan</div>
                        <div class="gf-th flex-1 min-w-[16rem]">Curso modelo</div>
                        <div class="gf-th-right w-48">Acciones</div>
                    </div>

                    @forelse ($curplanes as $c)
                        <div class="gf-row gf-row-hover">
                            <div class="gf-td w-36">
                                <div class="font-medium text-neutral-900">{{ $c->plan?->abrev ?: ('#' . $c->idPlan) }}</div>
                                <div class="truncate text-xs text-neutral-500">{{ $c->plan?->plan }}</div>
                            </div>
                            <div class="gf-td flex-1 min-w-[16rem] font-medium text-neutral-900">{{ $c->curPlanCurso }}</div>
                            <div class="gf-td-actions w-48">
                                <a href="{{ route('abm.curplan.edit', ['id' => $c->id]) }}" class="btn-secondary btn-sm">Editar</a>
                                <button type="button" wire:click="confirmDelete({{ $c->id }})" class="btn-danger btn-sm">Eliminar</button>
                            </div>
                        </div>
                    @empty
                        <div class="gf-empty">No hay cursos modelo registrados.</div>
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
