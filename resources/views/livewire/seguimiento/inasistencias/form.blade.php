<div class="se-page max-w-4xl">
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
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Seguimiento</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $this->id ? 'Editar inasistencia' : 'Nueva inasistencia' }}</h2>
                <p class="text-sm text-white/75">Los campos marcados con * son obligatorios</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                @if ($m)
                    <x-nav-contexto-estudiante
                        destino="seguimiento.inasistencias"
                        :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::SEGUIMIENTO_INASISTENCIAS"
                        :curso="$m->idCursos"
                        :matricula="$m->id"
                        class="inline">
                        <span class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                            Cancelar
                        </span>
                    </x-nav-contexto-estudiante>
                @else
                    <a href="{{ route('seguimiento.inasistencias') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                        Cancelar
                    </a>
                @endif
                <button type="button" wire:click="save" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Guardar</span>
                    <span wire:loading wire:target="save">Guardando…</span>
                </button>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden p-6 sm:p-7">
        @if ($m)
            <div class="mb-6 rounded-2xl border border-accent-200 bg-accent-50/50 px-4 py-3">
                <p class="text-sm font-semibold text-neutral-900">{{ $m->legajo?->apellido }}, {{ $m->legajo?->nombre }}</p>
                <p class="mt-0.5 text-xs text-neutral-600">
                    {{ $m->curso?->nombreParaListado() ?? '—' }} · Año {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
        @endif

        <input type="hidden" wire:model="idMatricula">

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="form-label">Tipo de inasistencia *</label>
                <select wire:model.live="tipo" class="form-select mt-1.5 @error('tipo') border-red-400 @enderror">
                    <option value="">— Seleccione —</option>
                    @foreach ($tipos as $t)
                        <option value="{{ $t->id }}">{{ $t->concepto }}</option>
                    @endforeach
                </select>
                @error('tipo') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Fecha *</label>
                <input wire:model="fecha" type="date" class="form-input mt-1.5 @error('fecha') border-red-400 @enderror">
                @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Cantidad</label>
                <input wire:model="cantidad"
                       type="text"
                       inputmode="decimal"
                       maxlength="6"
                       class="form-input mt-1.5 @error('cantidad') border-red-400 @enderror">
                @error('cantidad') <p class="form-error">{{ $message }}</p> @enderror
                <p class="mt-1.5 text-xs text-neutral-500">Al elegir el tipo se sugiere la cantidad del catálogo.</p>
            </div>

            @if ($id)
                <div>
                    <label class="form-label">Justificación</label>
                    <select wire:model="just" class="form-select mt-1.5 @error('just') border-red-400 @enderror">
                        <option value="I">Injustificada</option>
                        <option value="J">Justificada</option>
                    </select>
                    @error('just') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <label class="form-label">Justificación</label>
                    <p class="mt-1.5 text-sm text-neutral-600">Injustificada (se puede justificar al editar el registro).</p>
                </div>
            @endif

            <div class="sm:col-span-2">
                <label class="form-label">Observaciones</label>
                <input wire:model="obs" type="text" maxlength="100" class="form-input mt-1.5 @error('obs') border-red-400 @enderror">
                @error('obs') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>
