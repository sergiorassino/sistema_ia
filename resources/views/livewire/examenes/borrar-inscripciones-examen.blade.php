<div class="mx-auto w-full max-w-3xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Exámenes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Borrar todas las inscripciones a examen</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
            {{ session('success') }}
        </div>
    @endif

    @error('borrado')
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    <div class="se-card space-y-5 p-5 sm:p-6">
        <div class="space-y-3 text-sm text-neutral-600">
            <p class="font-medium text-amber-900">
                La operación no se puede deshacer desde esta pantalla. Revise el conteo antes de confirmar.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-accent-200 bg-accent-50/60 px-4 py-3">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Inscriptos actuales</span>
            <span class="se-pill text-primary-800 text-base font-bold tabular-nums">{{ $pendientes }}</span>
            <button type="button"
                    wire:click="refrescarConteo"
                    wire:loading.attr="disabled"
                    class="btn-secondary btn-sm ml-auto">
                <span wire:loading.remove wire:target="refrescarConteo">Actualizar conteo</span>
                <span wire:loading wire:target="refrescarConteo">Actualizando…</span>
            </button>
        </div>

        @if ($ultimasAfectadas !== null)
            <p class="text-sm text-neutral-600">
                Última ejecución en esta sesión: <strong>{{ $ultimasAfectadas }}</strong> fila(s) actualizada(s).
            </p>
        @endif

        <div class="flex flex-wrap gap-3 border-t border-accent-200 pt-4">
            <button type="button"
                    wire:click="abrirConfirmacion"
                    wire:loading.attr="disabled"
                    @disabled($pendientes === 0)
                    class="btn-danger @if($pendientes === 0) opacity-50 cursor-not-allowed @endif">
                Borrar todas las inscripciones
            </button>
        </div>
    </div>

    @if ($showConfirm)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-neutral-900/60 p-4 backdrop-blur-sm"
             role="dialog"
             aria-modal="true"
             aria-labelledby="confirm-borrar-inscri-titulo">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
                            <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 id="confirm-borrar-inscri-titulo" class="mb-1 text-base font-semibold text-neutral-800">
                                Confirmar borrado de inscripciones
                            </h3>
                            <p class="text-sm text-neutral-600">
                                Se anularán <span class="font-semibold text-neutral-800">{{ $pendientes }}</span>
                                inscripción(es) a examen en toda la tabla calificaciones
                                (campo <code class="text-xs">inscri</code> pasará de 1 a 0).
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/70 px-6 py-4">
                    <button type="button" wire:click="cerrarConfirmacion" class="btn-secondary">Cancelar</button>
                    <button type="button"
                            wire:click="ejecutarBorrado"
                            wire:loading.attr="disabled"
                            class="btn-danger">
                        <span wire:loading.remove wire:target="ejecutarBorrado">Confirmar borrado</span>
                        <span wire:loading wire:target="ejecutarBorrado">Procesando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
