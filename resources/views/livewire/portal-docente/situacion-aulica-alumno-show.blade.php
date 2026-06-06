<div class="mx-auto w-full max-w-6xl space-y-6">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4500)"
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
                <p class="se-eyebrow">Situación áulica</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ $matricula->legajo?->apellido }}, {{ $matricula->legajo?->nombre }}
                </h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ $matricula->curso?->nombreParaListado() ?? '—' }}
                    · Año {{ schoolCtx()->terlecAno() }}
                    · Solo registros de su autoría
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('portalDocente.cuadernoSeguimiento.registro', ['curso' => $cursoId, 'materia' => $materiaId]) }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver al listado
                </a>
                @if (! $mostrarFormNuevo)
                    <button type="button" wire:click="abrirFormNuevo"
                            class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-100">
                        + Nuevo registro
                    </button>
                @endif
            </div>
        </div>
    </section>

    @if ($mostrarFormNuevo)
        <div class="se-card overflow-hidden p-6 sm:p-7">
            <div class="mb-5 flex items-center justify-between gap-3">
                <h3 class="text-lg font-semibold text-neutral-900">Nuevo registro</h3>
                <button type="button" wire:click="cerrarFormNuevo" class="text-sm font-medium text-neutral-500 hover:text-neutral-800">
                    Cancelar
                </button>
            </div>
            <p class="mb-5 text-xs text-neutral-500">
                Una vez guardado, el registro no podrá editarse. Se notificará al preceptor del curso por COMUNICACIONES (sin correo ni push).
            </p>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="form-label">Tipo</label>
                    <p class="mt-1.5 rounded-xl border border-accent-200 bg-accent-50/80 px-4 py-2.5 text-sm font-medium text-neutral-800">
                        {{ $tipoLabel }}
                    </p>
                </div>

                <div>
                    <label class="form-label">Fecha *</label>
                    <input wire:model="fecha" type="date" class="form-input mt-1.5 @error('fecha') border-red-400 @enderror">
                    @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label">Motivo *</label>
                    <textarea wire:model="motivo" rows="4" class="form-input mt-1.5 @error('motivo') border-red-400 @enderror"></textarea>
                    @error('motivo') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" wire:click="cerrarFormNuevo" class="btn-secondary">Cancelar</button>
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading.remove wire:target="guardar">Guardar registro</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>
        </div>
    @endif

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="text-sm font-semibold text-neutral-900">Registros del año lectivo {{ schoolCtx()->terlecAno() }}</p>
            <p class="mt-0.5 text-xs text-neutral-500">Solo los aplicados por usted. No se pueden modificar después de guardados.</p>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-accent-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Fecha</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Tipo</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-accent-100 bg-white">
                    @forelse ($sanciones as $s)
                        <tr class="transition-colors hover:bg-accent-50/60">
                            <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $s->fecha?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $s->tipo?->tipo ?? $tipoLabel }}</td>
                            <td class="px-4 py-3">
                                <div class="line-clamp-3 max-w-md">{{ $s->motivo ?? '—' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-12 text-center text-sm text-neutral-500">
                                Sin registros suyos para este alumno en el año lectivo actual.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
