<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Comunicaciones</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Preferencias de comunicación</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ studentCtx()->nivelNombre() }} · Ciclo lectivo {{ studentCtx()->terlecAno() }}
                    </p>
                </div>
            </div>
            <a href="{{ route('alumnos.comunicaciones.index') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Bandeja
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="se-section-title">Contacto y medios</p>
            <p class="mt-1 text-sm text-neutral-600">
                @if (config('comunicaciones.alumno_ui_medios_preferencia'))
                    Elegí cómo y a qué responsables les llegan los comunicados de la escuela.
                @else
                    Elegí a qué responsables les llegan los comunicados de la escuela.
                @endif
            </p>
        </div>

        <div class="space-y-6 border-t border-accent-100 bg-accent-50/30 p-5 sm:p-6">
            <div>
                <p class="form-label">Responsable de recibir comunicados</p>
                <p class="mt-1 text-xs text-neutral-500">
                    Marcá uno o más. Si no marcás ninguno, se usa el primer dato de contacto disponible en el legajo.
                </p>

                <div class="mt-3 space-y-3" role="group" aria-label="Responsables de recibir comunicados">
                    @foreach ($vinculos as $val => $label)
                        <label class="flex cursor-pointer select-none items-start gap-3 rounded-2xl border border-accent-200 bg-white p-4 shadow-sm transition hover:bg-accent-50/60">
                            <input type="checkbox"
                                   id="pref-vinculo-{{ $val }}"
                                   wire:model="vinculosContacto"
                                   value="{{ $val }}"
                                   class="mt-0.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500"/>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-neutral-900">{{ $label }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('vinculosContacto')
                    <p class="form-error mt-1">{{ $message }}</p>
                @enderror
                @error('vinculosContacto.*')
                    <p class="form-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            @if (config('comunicaciones.alumno_ui_medios_preferencia'))
                <div>
                    <p class="form-label">Medios de aviso</p>
                    <p class="mt-1 text-xs text-neutral-500">
                        Elegí los medios que querés usar. La bandeja del portal siempre está disponible.
                    </p>

                    <div class="mt-3 space-y-3">
                        <label class="flex cursor-pointer select-none items-start gap-3 rounded-2xl border border-accent-200 bg-white p-4 shadow-sm transition hover:bg-accent-50/60">
                            <input type="checkbox"
                                   wire:model="push"
                                   class="mt-0.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500"/>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-neutral-900">Notificaciones push</p>
                                <p class="mt-1 text-xs leading-relaxed text-neutral-500">
                                    En el navegador o en la aplicación instalada. Requiere activarlas en el dispositivo.
                                </p>
                            </div>
                        </label>

                        <label class="flex cursor-pointer select-none items-start gap-3 rounded-2xl border border-accent-200 bg-white p-4 shadow-sm transition hover:bg-accent-50/60">
                            <input type="checkbox"
                                   wire:model="email"
                                   class="mt-0.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500"/>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-neutral-900">Correo electrónico</p>
                                <p class="mt-1 text-xs leading-relaxed text-neutral-500">
                                    Se envía al email del legajo según los responsables seleccionados arriba.
                                </p>
                            </div>
                        </label>

                        <label class="flex cursor-pointer select-none items-start gap-3 rounded-2xl border border-accent-200 bg-white p-4 shadow-sm transition hover:bg-accent-50/60">
                            <input type="checkbox"
                                   wire:model="whatsapp"
                                   class="mt-0.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500"/>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-neutral-900">WhatsApp</p>
                                <p class="mt-1 text-xs leading-relaxed text-neutral-500">
                                    Al celular registrado en el legajo. El envío puede ser manual por la institución.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>
            @endif

            <div class="flex justify-end border-t border-accent-200 pt-2">
                <button type="button"
                        wire:click="guardar"
                        wire:loading.attr="disabled"
                        class="btn-primary disabled:opacity-60">
                    <span wire:loading wire:target="guardar" class="mr-2 inline-flex">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </span>
                    Guardar preferencias
                </button>
            </div>
        </div>
    </div>
</div>
