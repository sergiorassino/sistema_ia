<div class="se-page max-w-3xl">
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
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $id ? 'Editar plan' : 'Nuevo plan' }}</h2>
                <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}</p>
                <p class="text-xs text-white/65">Campos con * son obligatorios.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('abm.planes') }}"
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

    <div class="se-card overflow-hidden p-6 sm:p-7">
        <p class="se-section-title mb-5">Datos del plan</p>
        <div class="space-y-5">
            <div>
                <label class="form-label">Plan *</label>
                <input wire:model="plan" type="text" maxlength="80" placeholder="Ej: Plan Común"
                       class="form-input mt-1.5 @error('plan') border-red-400 @enderror">
                @error('plan') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Abreviatura</label>
                <input wire:model="abrev" type="text" maxlength="15" placeholder="Ej: PC"
                       class="form-input mt-1.5 font-mono @error('abrev') border-red-400 @enderror">
                @error('abrev') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($id)
            <p class="mt-6 text-xs text-neutral-400">ID #{{ $id }}</p>
        @endif
    </div>
</div>
