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
                <p class="se-eyebrow">Seguimiento de gabinete</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $this->id ? 'Editar registro' : 'Nuevo registro' }}</h2>
                <p class="text-sm text-white/75">Los campos marcados con * son obligatorios</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                @if ($this->id)
                    <button type="button"
                            x-on:click="seSwalConfirmar('¿Confirma borrar este registro? Esta acción no se puede deshacer.', 'Confirmar borrado').then(ok => ok && $wire.delete())"
                            wire:loading.attr="disabled"
                            wire:target="delete"
                            class="inline-flex items-center justify-center rounded-xl border border-red-300/80 bg-red-500/90 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-600 disabled:opacity-60">
                        <span wire:loading.remove wire:target="delete">Borrar</span>
                        <span wire:loading wire:target="delete">Borrando…</span>
                    </button>
                @endif
                @if ($m)
                    <x-nav-contexto-estudiante
                        destino="seguimiento.gabinete.alumno"
                        :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::SEGUIMIENTO_GABINETE"
                        :curso="$m->idCursos"
                        :matricula="$m->id"
                        class="inline">
                        <span class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                            Cancelar
                        </span>
                    </x-nav-contexto-estudiante>
                @else
                    <a href="{{ route('seguimiento.gabinete') }}"
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
                <label class="form-label">Tipo de seguimiento *</label>
                <select wire:model="idTipoSancion" class="form-select mt-1.5 @error('idTipoSancion') border-red-400 @enderror">
                    <option value="">— Seleccione —</option>
                    @foreach ($tipos as $t)
                        <option value="{{ $t->id }}">{{ $t->tipo }}</option>
                    @endforeach
                </select>
                @error('idTipoSancion') <p class="form-error">{{ $message }}</p> @enderror
                @if ($tipos->isEmpty())
                    <p class="mt-1.5 text-xs text-amber-800">No hay tipos en <code class="text-[10px]">gabinetetipo</code>.</p>
                @endif
            </div>

            <div>
                <label class="form-label">Fecha *</label>
                <input wire:model="fecha" type="date" class="form-input mt-1.5 @error('fecha') border-red-400 @enderror">
                @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Solicitud</label>
                <input wire:model="solipor" type="text" maxlength="500" class="form-input mt-1.5 @error('solipor') border-red-400 @enderror">
                @error('solipor') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Motivo</label>
                <textarea wire:model="motivo" rows="4" class="form-input mt-1.5 resize-y leading-relaxed @error('motivo') border-red-400 @enderror"></textarea>
                @error('motivo') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Asistentes</label>
                <textarea wire:model="asistentes" rows="3" class="form-input mt-1.5 resize-y leading-relaxed @error('asistentes') border-red-400 @enderror"></textarea>
                @error('asistentes') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Conclusión</label>
                <textarea wire:model="conclusion" rows="4" class="form-input mt-1.5 resize-y leading-relaxed @error('conclusion') border-red-400 @enderror"></textarea>
                @error('conclusion') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>

@script
<script>
    $wire.on('se-swal-error', (e) => { window.seSwalError?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
</script>
@endscript
