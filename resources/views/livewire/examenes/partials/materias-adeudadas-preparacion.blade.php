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
                       aria-live="polite">
                        {{ $mensajeRecalculo }}
                    </p>
                @endif

                <button type="button"
                        wire:click="confirmarPreparacionMateriasAdeudadas"
                        wire:loading.attr="disabled"
                        wire:target="confirmarPreparacionMateriasAdeudadas"
                        class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-60">
                    <span wire:loading.remove wire:target="confirmarPreparacionMateriasAdeudadas">Recalcular condiciones y continuar</span>
                    <span wire:loading wire:target="confirmarPreparacionMateriasAdeudadas">Procesando…</span>
                </button>
            </div>
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
