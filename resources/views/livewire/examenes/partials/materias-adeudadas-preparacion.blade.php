@if (($siempreMostrarSelectsPreparacion ?? false) || ($mostrarFormularioPreparacion ?? false))
    <div class="se-card mt-6 px-5 py-6 sm:px-8">
        <p class="se-section-title">Preparación de exámenes</p>
        <p class="mt-2 text-sm text-neutral-700">
            @if ($siempreMostrarSelectsPreparacion ?? false)
                Elegí el turno de examen y el año lectivo, luego confirmá para recalcular las condiciones de las materias adeudadas
                (<code class="text-xs">condAdeuda</code> e <code class="text-xs">inscri</code>).
            @else
                Cada vez que ingresás a este módulo desde el menú, indicá el turno de examen y el año lectivo
                y confirmá para recalcular las condiciones de las materias adeudadas
                (<code class="text-xs">condAdeuda</code> e <code class="text-xs">inscri</code>).
            @endif
        </p>

        <div wire:key="prep-form-{{ ($siempreMostrarSelectsPreparacion ?? false) ? 'gestion' : 'listado' }}"
             class="mt-6 grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2"
             role="group"
             aria-label="Preparación de exámenes">
            <div>
                <label for="prep-turno" class="form-label">Turno de examen</label>
                <select id="prep-turno"
                        wire:model.live="idTurnoPreparacion"
                        class="form-select mt-1.5 w-full @error('idTurnoPreparacion') border-red-400 @enderror">
                    <option value="0">— Seleccionar —</option>
                    @foreach ($turnosDisponibles ?? [] as $t)
                        @php
                            $label = trim((string) ($t->turno ?? ''));
                            if ($label === '') {
                                $label = trim((string) ($t->nturno ?? ''));
                            }
                            if ($label === '') {
                                $label = 'Turno #'.$t->id;
                            }
                        @endphp
                        <option value="{{ $t->id }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('idTurnoPreparacion')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="prep-terlec" class="form-label">Año lectivo del turno</label>
                <select id="prep-terlec"
                        wire:model.live="idTerlecPreparacion"
                        class="form-select mt-1.5 w-full @error('idTerlecPreparacion') border-red-400 @enderror">
                    <option value="0">— Seleccionar —</option>
                    @foreach ($terlecsDisponibles ?? [] as $t)
                        <option value="{{ $t->id }}">{{ $t->ano }}</option>
                    @endforeach
                </select>
                @error('idTerlecPreparacion')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2 space-y-3">
                @if (! empty($mensajeRecalculo) && ($siempreMostrarSelectsPreparacion ?? false))
                    <p class="rounded-xl border border-accent-200 bg-accent-50/90 px-4 py-3 text-sm text-neutral-800"
                       role="status"
                       aria-live="polite"
                       wire:loading.remove
                       wire:target="confirmarPreparacionMateriasAdeudadas">
                        {{ $mensajeRecalculo }}
                    </p>
                @endif

                <div wire:loading.flex
                     wire:target="confirmarPreparacionMateriasAdeudadas"
                     class="items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-900"
                     role="status"
                     aria-live="assertive"
                     aria-busy="true">
                    <span class="inline-block h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-primary-200 border-t-primary-700" aria-hidden="true"></span>
                    <span class="font-semibold uppercase tracking-wide">Procesando…</span>
                    <span class="text-primary-800/80">Recalculando condiciones. Esperá un momento.</span>
                </div>

                <button type="button"
                        wire:click="confirmarPreparacionMateriasAdeudadas"
                        wire:loading.attr="disabled"
                        wire:target="confirmarPreparacionMateriasAdeudadas"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                    <svg wire:loading wire:target="confirmarPreparacionMateriasAdeudadas"
                         class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="confirmarPreparacionMateriasAdeudadas">Recalcular condiciones y continuar</span>
                    <span wire:loading wire:target="confirmarPreparacionMateriasAdeudadas">Procesando…</span>
                </button>
            </div>
        </div>
    </div>

    <div wire:loading.flex
         wire:target="confirmarPreparacionMateriasAdeudadas"
         class="fixed inset-0 z-[100] items-center justify-center bg-neutral-900/45 px-4 backdrop-blur-sm"
         role="alertdialog"
         aria-busy="true"
         aria-label="Procesando">
        <div class="max-w-sm rounded-2xl bg-white px-6 py-5 text-center shadow-xl ring-1 ring-black/5">
            <div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-2 border-primary-200 border-t-primary-600" aria-hidden="true"></div>
            <p class="text-sm font-bold uppercase tracking-wide text-neutral-800">Procesando…</p>
            <p class="mt-2 text-sm text-neutral-600">
                Recalculando condiciones de materias adeudadas. No cierres esta ventana.
            </p>
        </div>
    </div>
@elseif ($preparacionLista ?? false)
    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl border border-accent-200 bg-accent-50/80 px-4 py-3 text-sm text-neutral-800">
        <span class="se-pill">
            Turno: {{ $etiquetaTurnoPreparacion ?? '—' }}
            · Año lectivo {{ $anoTerlecPreparacion ?? '—' }}
        </span>
        @if (! empty($mensajeRecalculo))
            <span class="text-neutral-600">{{ $mensajeRecalculo }}</span>
        @endif
        <button type="button"
                wire:click="cambiarPreparacionMateriasAdeudadas"
                class="ml-auto text-sm font-semibold text-primary-700 hover:text-primary-900">
            Cambiar turno / año
        </button>
    </div>
@endif
