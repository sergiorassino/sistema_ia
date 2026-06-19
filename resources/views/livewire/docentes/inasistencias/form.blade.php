<div class="se-page w-full min-w-0 max-w-4xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <p class="se-eyebrow">{{ $this->id ? 'Editar' : 'Nueva' }} inasistencia</p>
            <h2 class="text-2xl font-bold tracking-tight">{{ $profesor->apellido }} {{ $profesor->nombre }}</h2>
        </div>
    </section>

    <div class="se-card min-w-0 overflow-hidden">
        <form wire:submit.prevent="save" class="space-y-4 p-4 sm:p-5">
            <div class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="form-label">Tipo</label>
                    <select wire:model.live="inaLic" class="form-input mt-1 w-full max-w-md">
                        <option value="0">Inasistencia de un día</option>
                        <option value="1">Licencia</option>
                    </select>
                </div>

                <div class="sm:col-span-2 min-w-0">
                    <label class="form-label">Motivo *</label>
                    <select wire:model="idTipoInaDoc" class="form-input mt-1 w-full min-w-0">
                        <option value="">— Seleccionar —</option>
                        @foreach ($tipos as $t)
                            <option value="{{ $t->id }}">{{ $t->motivo }}</option>
                        @endforeach
                    </select>
                    @error('idTipoInaDoc') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                @if ($cargos->isNotEmpty())
                    <div class="sm:col-span-2 min-w-0">
                        <label class="form-label">Cargo *</label>
                        <select wire:model.live="idCargosXProfesor" class="form-input mt-1 w-full min-w-0">
                            <option value="">— Seleccionar —</option>
                            @foreach ($cargos as $c)
                                <option value="{{ $c['id'] }}">{{ $c['cargo'] }}</option>
                            @endforeach
                        </select>
                        @error('idCargosXProfesor') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="form-label">Nivel</label>
                    <input type="text" class="form-input mt-1 w-full max-w-[12rem] bg-accent-50" value="{{ $nivelNombre }}" readonly>
                </div>

                <div>
                    <label class="form-label">Fecha *</label>
                    <input type="date" wire:model.live="fecha" class="form-input mt-1 w-full max-w-[11rem]">
                    @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                @if ($inaLic === 1)
                    <div>
                        <label class="form-label">Fecha hasta (licencia) *</label>
                        <input type="date" wire:model.live="hasta" class="form-input mt-1 w-full max-w-[11rem]">
                        @error('hasta') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <label class="form-label">Cant. oblig. inasistidas *</label>
                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                        <input type="number" step="0.1" min="0" wire:model="cantObligIna" class="form-input w-full max-w-[7rem] tabular-nums">
                        @if ($obligacionesEsperadas)
                            <p class="text-xs text-neutral-600">
                                <span class="font-semibold uppercase tracking-wide text-neutral-500">{{ $obligacionesEsperadas['etiqueta'] }}:</span>
                                <span class="tabular-nums text-neutral-800">{{ $obligacionesEsperadas['detalle'] }}</span>
                            </p>
                        @elseif ($cargos->isNotEmpty() && ! (int) $idCargosXProfesor)
                            <p class="text-xs text-neutral-500">Seleccioná el cargo para ver las obligaciones del día o del período.</p>
                        @endif
                    </div>
                    @error('cantObligIna') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" wire:model="justif" class="rounded border-accent-300 text-primary-600">
                        Justificada
                    </label>
                </div>

                <div class="sm:col-span-2 min-w-0">
                    <label class="form-label">Observaciones</label>
                    <textarea wire:model="obs" rows="3" class="form-input mt-1 w-full min-w-0"></textarea>
                </div>
            </div>

            @if ($opcionesMateriaCurso->isNotEmpty())
                <div class="min-w-0 rounded-xl border border-accent-200 bg-accent-50/50 p-3 space-y-2">
                    <p class="text-sm font-semibold text-neutral-800">Distribución por materia y curso (opcional)</p>
                    <p class="text-xs text-neutral-600">Indicá cuántas obligaciones inasistidas corresponden a cada materia/curso. Podés usar «+ Agregar» para sumar más cursos afectados en el mismo registro.</p>

                    @foreach ($filasDetalle as $rowId)
                        <div wire:key="detalle-fila-{{ $rowId }}" class="flex flex-col gap-2 min-w-0 sm:flex-row sm:items-end sm:gap-2">
                            <div class="min-w-0 flex-1">
                                <select wire:model.live="detalleMateriaCurso.{{ $rowId }}" class="form-input w-full min-w-0 text-sm py-2">
                                    <option value="">— Materia / curso —</option>
                                    @foreach ($opcionesMateriaCurso as $op)
                                        <option value="{{ $op['value'] }}">{{ $op['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex shrink-0 items-end gap-2">
                                <div class="w-[4.5rem]">
                                    <label class="sr-only">Cantidad</label>
                                    <input type="number" step="0.01" min="0" wire:model.live="detalleCantidad.{{ $rowId }}"
                                           class="form-input w-full text-sm py-2 tabular-nums" placeholder="Cant.">
                                </div>
                                <button type="button" wire:click="removeDetalleFila({{ $rowId }})"
                                        class="btn-danger btn-sm h-[2.375rem] w-8 shrink-0 px-0" title="Quitar fila">×</button>
                            </div>
                        </div>
                    @endforeach

                    <button type="button" wire:click="addDetalleFila" wire:loading.attr="disabled" class="btn-secondary btn-sm">
                        + Agregar materia / curso
                    </button>
                </div>
            @endif

            <div class="flex flex-wrap justify-between gap-3 border-t border-accent-200 pt-3">
                <a href="{{ $urlVolver }}" class="btn-secondary">← Volver</a>
                <div class="flex flex-wrap gap-2">
                    @if ($this->id)
                        <button type="button" wire:click="delete" wire:confirm="¿Eliminar esta inasistencia y su detalle?"
                                class="btn-danger">Eliminar</button>
                    @endif
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Guardar</span>
                        <span wire:loading wire:target="save">Guardando…</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
