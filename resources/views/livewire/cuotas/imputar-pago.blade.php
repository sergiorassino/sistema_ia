@php
    use App\Support\Cuotas\CuotasFormato;
@endphp

<div class="se-page max-w-xl mx-auto"
     x-on:cuotas-imputar-pago-abrir-comprobante.window="window.open($event.detail.url, '_blank')"
     x-data="{
        showDatosCuota: false,
        order: ['saldoAPagar', 'porcent', 'fechaPago', 'obs'],
        focusById(id) {
            const el = document.getElementById(id);
            if (el) { el.focus(); el.select?.(); }
        },
        move(fromId, delta) {
            const i = this.order.indexOf(fromId);
            if (i === -1) return;
            const next = this.order[i + delta];
            if (next) this.focusById(next);
        },
        onKey(e, id) {
            if (e.key === 'Enter') { e.preventDefault(); this.move(id, +1); return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); this.move(id, +1); return; }
            if (e.key === 'ArrowUp') { e.preventDefault(); this.move(id, -1); return; }
        },
     }">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Gestión de aranceles</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Imputar pago</h1>
                @if ($encabezado)
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/90 sm:text-sm">
                        {{ $encabezado['apellido'] }} {{ $encabezado['nombre'] }}
                    </p>
                    @if ($registro)
                        <p class="text-xs text-white/75">
                            {{ trim((string) ($registro->cuota?->nombre ?? '')) }}
                        </p>
                    @endif
                @endif
            </div>
            <a href="{{ route('cuotas.estudiante') }}"
               wire:navigate
               class="inline-flex shrink-0 items-center justify-center rounded-lg border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20">
                Volver
            </a>
        </div>
    </section>

    <form wire:submit="guardar" class="se-card overflow-hidden p-4 sm:p-5 space-y-5">
        <div class="space-y-2.5">
            <p class="se-section-title text-center">Medio de pago</p>

            <div class="flex flex-wrap justify-center gap-2">
                @foreach ($mediosPago as $medio)
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50">
                        <input type="radio"
                               wire:model.live="idCuotastipopago"
                               value="{{ $medio->id }}"
                               class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                        <span class="whitespace-nowrap">{{ $medio->tipoPago }}</span>
                    </label>
                @endforeach
            </div>
            @error('idCuotastipopago') <p class="form-error text-center">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2.5">
            <p class="se-section-title text-center">Importe a imputar</p>

            <div class="grid gap-3">
                <div>
                    <label class="form-label" for="saldoAPagar">Saldo a pagar</label>
                    <input id="saldoAPagar"
                           type="text"
                           wire:model.live="saldoAPagar"
                           x-on:keydown="onKey($event, 'saldoAPagar')"
                           class="form-input w-full tabular-nums text-right font-semibold">
                    @error('saldoAPagar') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label text-[11px] leading-tight" for="porcent">{{ $etiquetaPorcent }}</label>
                        <input id="porcent"
                               type="text"
                               wire:model.live="porcent"
                               x-on:keydown="onKey($event, 'porcent')"
                               class="form-input w-full tabular-nums text-right">
                        @error('porcent') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">{{ $etiquetaImporteAjuste }}</label>
                        <input type="text"
                               readonly
                               value="{{ $interesImporte }}"
                               class="form-input w-full tabular-nums text-right bg-accent-50"
                               tabindex="-1"
                               aria-readonly="true">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">A pagar</label>
                        <input type="text"
                               readonly
                               value="{{ $aPagar }}"
                               class="form-input w-full tabular-nums text-right bg-primary-50 font-bold text-primary-900"
                               tabindex="-1"
                               aria-readonly="true">
                    </div>
                    <div>
                        <label class="form-label" for="fechaPago">Fecha pago</label>
                        <input id="fechaPago"
                               type="date"
                               wire:model.live="fechaPago"
                               x-on:keydown="onKey($event, 'fechaPago')"
                               class="form-input w-full">
                        @error('fechaPago') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="grid gap-3">
                <label class="inline-flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-accent-200 bg-accent-50/40 px-4 py-3 text-xs font-semibold text-neutral-800">
                    <span class="min-w-0">
                        Aviso de pago
                        <span class="mt-0.5 block text-[10px] font-medium text-neutral-500">
                            Cupón pagado y aún sin impacto SIRO
                        </span>
                    </span>
                    <input type="checkbox"
                           wire:model.live="avisoPago"
                           class="h-5 w-10 rounded-full border-accent-300 text-primary-600 focus:ring-primary-500">
                </label>
            </div>

            <div class="mx-auto max-w-md">
                <label class="form-label" for="obs">Observaciones</label>
                <textarea id="obs"
                          wire:model.live="obs"
                          x-on:keydown="onKey($event, 'obs')"
                          rows="2"
                          class="form-input w-full"></textarea>
                @error('obs') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($registro)
            <div class="rounded-xl border border-accent-200 bg-white">
                <button type="button"
                        x-on:click="showDatosCuota = !showDatosCuota"
                        class="w-full rounded-xl px-4 py-3 text-left text-xs font-semibold text-primary-700 hover:bg-accent-50">
                    <span x-text="showDatosCuota ? 'Ocultar datos de la cuota' : 'Ver datos de la cuota'"></span>
                </button>

                <div x-show="showDatosCuota" x-cloak class="border-t border-accent-200 p-4">
                    <dl class="grid gap-2 text-xs">
                        <div><dt class="text-neutral-500">Cuota</dt><dd class="font-semibold uppercase">{{ $registro->cuota?->nombre }}</dd></div>
                        <div><dt class="text-neutral-500">Curso</dt><dd class="uppercase">{{ $registro->curso?->nombreParaListado() }}</dd></div>
                        <div><dt class="text-neutral-500">Venc 1</dt><dd>{{ CuotasFormato::formatearFecha($registro->venc1) }}</dd></div>
                        <div><dt class="text-neutral-500">Venc 2</dt><dd>{{ CuotasFormato::formatearFecha($registro->venc2) }}</dd></div>
                        <div><dt class="text-neutral-500">Venc 3</dt><dd>{{ CuotasFormato::formatearFecha($registro->venc3) }}</dd></div>
                        <div><dt class="text-neutral-500">Importe</dt><dd class="tabular-nums">{{ CuotasFormato::formatearImporte($registro->importe) }}</dd></div>
                        <div><dt class="text-neutral-500">Pagado</dt><dd class="tabular-nums">{{ CuotasFormato::formatearImporte($registro->pagado) }}</dd></div>
                        <div><dt class="text-neutral-500">Faltaba</dt><dd class="tabular-nums font-bold">{{ CuotasFormato::formatearImporte($registro->faltapa) }}</dd></div>
                    </dl>
                </div>
            </div>
        @endif

        <div class="mx-auto flex w-full max-w-md flex-col-reverse gap-2 sm:flex-row sm:justify-center">
            <a href="{{ route('cuotas.estudiante') }}"
               wire:navigate
               class="inline-flex items-center justify-center rounded-lg border border-accent-200 bg-white px-4 py-1.5 text-xs font-semibold text-primary-700 hover:bg-accent-50">
                Cancelar
            </a>
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-5 py-1.5 text-xs font-semibold text-white hover:bg-primary-700">
                Registrar pago
            </button>
        </div>
    </form>
</div>
