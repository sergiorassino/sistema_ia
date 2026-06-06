<div class="se-page max-w-xl mx-auto">
    @if (session('success'))
        <div class="mb-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">{{ session('error') }}</div>
    @endif

    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Gestión de aranceles</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Editar cuota</h1>
                @if ($encabezado)
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/90 sm:text-sm">
                        {{ $encabezado['apellido'] }} {{ $encabezado['nombre'] }}
                    </p>
                    <p class="text-xs text-white/75">
                        {{ trim((string) ($registro?->cuota?->nombre ?? '')) }}
                    </p>
                @endif
            </div>
            <a href="{{ route('cuotas.estudiante') }}"
               wire:navigate
               class="inline-flex shrink-0 items-center justify-center rounded-lg border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20">
                Volver
            </a>
        </div>
    </section>

    <form wire:submit="guardar" class="se-card se-cuota-form overflow-hidden p-4 sm:p-5">
        <p class="se-section-title text-center">Datos de la cuota</p>

        <div class="se-cuota-form-body space-y-2.5">
            <div class="se-cuota-form-fechas">
                <div>
                    <label class="form-label" for="venc1">Venc 1</label>
                    <input id="venc1" type="date" wire:model="venc1" class="form-input">
                    @error('venc1') <p class="form-error text-center">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="venc2">Venc 2</label>
                    <input id="venc2" type="date" wire:model="venc2" class="form-input">
                    @error('venc2') <p class="form-error text-center">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="nueVenc">Venc. actualizado</label>
                    <input id="nueVenc" type="date" wire:model="nueVenc" class="form-input">
                    @error('nueVenc') <p class="form-error text-center">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="se-cuota-form-importes">
                <div>
                    <label class="form-label" for="importe">Importe base</label>
                    <input id="importe" type="text" wire:model.live="importe"
                           class="form-input w-full tabular-nums text-right">
                    @error('importe') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="becaLectura">Beca</label>
                    <input id="becaLectura" type="text" readonly
                           value="{{ $becaEtiqueta !== '' ? $becaEtiqueta : '—' }}"
                           class="form-input w-full"
                           tabindex="-1" aria-readonly="true">
                </div>
                <div>
                    <label class="form-label" for="bonificacion">Bonificación</label>
                    <input id="bonificacion" type="text" wire:model.live="bonificacion"
                           class="form-input w-full tabular-nums text-right">
                    @error('bonificacion') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="interes">Interés</label>
                    <input id="interes" type="text" wire:model.live="interes"
                           class="form-input w-full tabular-nums text-right">
                    @error('interes') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="pagado">Pagado</label>
                    <input id="pagado" type="text" wire:model.live="pagado"
                           class="form-input w-full tabular-nums text-right">
                    @error('pagado') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="saldo">Saldo</label>
                    <input id="saldo" type="text" readonly wire:model="faltapa"
                           class="form-input w-full tabular-nums text-right font-semibold text-primary-800"
                           tabindex="-1" aria-readonly="true">
                </div>
            </div>

            <div class="mx-auto max-w-md">
                <label class="form-label" for="obs">Observaciones</label>
                <textarea id="obs" wire:model="obs" rows="2" class="form-input w-full"></textarea>
                @error('obs') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mx-auto mt-5 flex w-full max-w-md flex-col gap-2">
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                <a href="{{ route('cuotas.estudiante') }}"
                   wire:navigate
                   class="inline-flex items-center justify-center rounded-lg border border-accent-200 bg-white px-4 py-1.5 text-xs font-semibold text-primary-700 hover:bg-accent-50">
                    Cancelar
                </a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-5 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="guardar">Guardar</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>

            @if ($puedeEliminar)
                <div class="flex justify-center border-t border-accent-200 pt-4">
                    <button type="button"
                            wire:loading.attr="disabled"
                            x-on:click="window.seSwalConfirmar('¿Eliminar esta cuota generada? Esta acción no se puede deshacer.', 'Confirmar eliminación', { confirmButtonText: 'Sí, eliminar' }).then((ok) => { if (ok) $wire.eliminarCuota(); })"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-300 bg-white px-4 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-60">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span wire:loading.remove wire:target="eliminarCuota">Eliminar cuota</span>
                        <span wire:loading wire:target="eliminarCuota">Eliminando…</span>
                    </button>
                </div>
            @elseif ($motivoNoEliminable !== null && $motivoNoEliminable !== 'Cuota no encontrada.')
                <p class="text-center text-[11px] text-neutral-500">{{ $motivoNoEliminable }}</p>
            @endif
        </div>
    </form>

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            $wire.on('se-swal-error', (event) => {
                const mensaje = mensajeDeEvento(event, 'No se pudo completar la operación.');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                }
            });
        })();
    </script>
    @endscript
</div>
