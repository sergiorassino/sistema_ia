<div class="se-page"
     x-on:cooperadora-abrir-pdf.window="window.open($event.detail.url, '_blank', 'noopener,noreferrer')">
  @php
      $esOrigenEstudiantes = $modo === 'origen_estudiantes';
  @endphp

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cooperadora</p>
                @if ($esOrigenEstudiantes)
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Ingreso — Origen estudiantes</h2>
                    <p class="text-sm text-white/80">
                        Cuotas, uniformes y aportes vinculados a alumnos. Puede cargar ítems de varios hermanos en un mismo recibo.
                    </p>
                @else
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Ingreso — Otros orígenes</h2>
                    <p class="text-sm text-white/80">
                        Canon de cantina, donaciones y otros ingresos sin vínculo con estudiantes.
                    </p>
                @endif
            </div>
            <a href="{{ route('cooperadora.ingresos') }}" class="btn-secondary shrink-0">Volver</a>
        </div>
    </section>

    <div class="se-card {{ $esOrigenEstudiantes ? 'max-w-6xl' : 'max-w-3xl' }}">
        @if ($esOrigenEstudiantes)
            <div class="space-y-3 border-b border-accent-100 p-5 sm:p-6">
                <livewire:cooperadora.buscar-alumno-ingreso
                    :id-legajo-activo="$idLegajo"
                    wire:key="buscar-alumno-ingreso-{{ $idLegajo ?? 0 }}" />

                @if ($legajoSel)
                    <div class="flex flex-col gap-3 rounded-2xl border border-primary-200 bg-primary-50/40 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-primary-700">Alumno en carga</p>
                            <p class="text-sm font-semibold text-neutral-800">{{ $legajoSel->apellido }}, {{ $legajoSel->nombre }}</p>
                            @if ($etiquetaCurso)
                                <p class="text-xs text-neutral-600">Curso: {{ $etiquetaCurso }}</p>
                            @endif
                        </div>
                        <button type="button" wire:click="confirmarAlumnoActual" class="btn-secondary shrink-0">
                            Confirmar y agregar otro alumno
                        </button>
                    </div>
                @endif

                @if (count($alumnosEnRecibo) > 0)
                    <div>
                        <p class="se-label">Alumnos incluidos en este recibo</p>
                        <ul class="mt-1 flex flex-wrap gap-2">
                            @foreach ($alumnosEnRecibo as $alumno)
                                <li class="se-pill">
                                    {{ $alumno['nombre'] }}
                                    @if ($alumno['curso'])
                                        <span class="text-neutral-500">· {{ $alumno['curso'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <form wire:submit="guardar" class="space-y-5 p-5 sm:p-6">

            @if (! $esOrigenEstudiantes)
                <div class="rounded-2xl border border-accent-200 bg-accent-50/50 p-4 sm:p-5 space-y-4">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">Datos del recibo</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="se-label">Pagador / entidad</label>
                            <input type="text" wire:model="pagadorNombre" class="se-input w-full" placeholder="Ej. Cantinera, donante, empresa">
                            @error('pagadorNombre') <p class="se-field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="se-label">Fecha</label>
                            <input type="date" wire:model="fecha" class="se-input w-full">
                            @error('fecha') <p class="se-field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="se-label">Medio de pago</label>
                            <select wire:model="idMedioPago" class="se-input w-full">
                                <option value="">— Seleccione —</option>
                                @foreach ($mediosPago as $medio)
                                    <option value="{{ $medio->id }}">{{ $medio->nombre }}</option>
                                @endforeach
                            </select>
                            @error('idMedioPago') <p class="se-field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-col justify-end">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Total del recibo</p>
                            <p class="text-2xl font-bold tabular-nums text-primary-700">${{ number_format($totalImporte, 2, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">
                        {{ $esOrigenEstudiantes ? 'Ítems a cobrar' : 'Conceptos a cobrar' }}
                    </h3>
                    <button type="button" wire:click="agregarLinea" class="btn-secondary btn-sm">Agregar ítem</button>
                </div>

                @if ($esOrigenEstudiantes)
                    <div class="w-full min-w-0 rounded-2xl border border-accent-200">
                        <div class="gf gf-coop-ingreso-lineas">
                            <div class="gf-head">
                                <div class="gf-th gf-th-num">#</div>
                                <div class="gf-th gf-th-alumno">Alumno</div>
                                <div class="gf-th gf-th-rubro">Rubro</div>
                                <div class="gf-th gf-th-item">Ítem</div>
                                <div class="gf-th gf-th-bruto text-right">Bruto</div>
                                <div class="gf-th gf-th-dto">Desc</div>
                                <div class="gf-th gf-th-cobrar text-right">A cobrar</div>
                                <div class="gf-th gf-th-concepto">Concepto</div>
                                <div class="gf-th gf-th-quitar"></div>
                            </div>

                            @foreach ($lineas as $index => $linea)
                                    @php
                                        $itemsLinea = $itemsPorRubro->get((int) ($linea['idRubro'] ?? 0), collect());
                                        $idLegajoLinea = (int) ($linea['idLegajo'] ?? 0);
                                        $nombreAlumnoLinea = $etiquetasLegajo[$idLegajoLinea] ?? null;
                                    @endphp
                                    <div class="gf-row gf-row-hover" wire:key="linea-{{ $index }}">
                                        <div class="gf-td gf-td-num text-xs font-semibold text-neutral-500">
                                            {{ $index + 1 }}
                                        </div>
                                        <div class="gf-td gf-td-alumno">
                                            @if ($nombreAlumnoLinea)
                                                <span class="text-xs font-medium text-neutral-800">{{ $nombreAlumnoLinea }}</span>
                                            @else
                                                <span class="text-xs italic text-neutral-400">Sin alumno</span>
                                            @endif
                                            @error('lineas.'.$index.'.idLegajo') <p class="text-[10px] text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="gf-td gf-td-rubro">
                                            <select wire:model.live="lineas.{{ $index }}.idRubro"
                                                    class="gf-inline-select @error('lineas.'.$index.'.idRubro') ring-2 ring-red-400 @enderror">
                                                <option value="">— Rubro —</option>
                                                @foreach ($rubros as $rubro)
                                                    <option value="{{ $rubro->id }}">{{ $rubro->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="gf-td gf-td-item">
                                            <select wire:model.live="lineas.{{ $index }}.idItem"
                                                    class="gf-inline-select @error('lineas.'.$index.'.idItem') ring-2 ring-red-400 @enderror"
                                                    @disabled($itemsLinea->isEmpty())>
                                                <option value="">— Ítem —</option>
                                                @foreach ($itemsLinea as $item)
                                                    <option value="{{ $item->id }}">{{ $item->nombre }} — ${{ number_format((float) $item->precio, 2, ',', '.') }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="gf-td gf-td-bruto">
                                            <input type="number"
                                                   step="0.01"
                                                   min="0"
                                                   wire:model.live="lineas.{{ $index }}.importeBruto"
                                                   class="gf-inline text-right tabular-nums">
                                        </div>
                                        <div class="gf-td gf-td-dto">
                                            <span class="text-xs tabular-nums text-neutral-600">{{ $linea['descuentoPct'] }}%</span>
                                        </div>
                                        <div class="gf-td gf-td-cobrar">
                                            <input type="number"
                                                   step="0.01"
                                                   min="0.01"
                                                   wire:model="lineas.{{ $index }}.importe"
                                                   class="gf-inline text-right tabular-nums font-semibold text-primary-700 @error('lineas.'.$index.'.importe') ring-2 ring-red-400 @enderror">
                                        </div>
                                        <div class="gf-td gf-td-concepto">
                                            <input type="text"
                                                   wire:model="lineas.{{ $index }}.concepto"
                                                   maxlength="2000"
                                                   placeholder="Opcional"
                                                   class="gf-inline text-neutral-600 placeholder:text-neutral-400">
                                        </div>
                                        <div class="gf-td gf-td-quitar gf-td-accion !py-1">
                                            @if (count($lineas) > 1)
                                                <button type="button"
                                                        wire:click="quitarLinea({{ $index }})"
                                                        class="inline-flex h-7 w-7 items-center justify-center rounded border border-accent-200 bg-white text-red-600 hover:bg-red-50"
                                                        title="Quitar ítem">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($errors->has('lineas.'.$index.'.idRubro') || $errors->has('lineas.'.$index.'.idItem') || $errors->has('lineas.'.$index.'.importe') || $errors->has('lineas.'.$index.'.idLegajo'))
                                        <div class="border-t border-accent-100 bg-red-50/60 px-3 py-1.5 text-[10px] text-red-700" wire:key="linea-err-{{ $index }}">
                                            @error('lineas.'.$index.'.idLegajo') <span>{{ $message }}</span> @enderror
                                            @error('lineas.'.$index.'.idRubro') <span>{{ $message }}</span> @enderror
                                            @error('lineas.'.$index.'.idItem') <span>{{ $message }}</span> @enderror
                                            @error('lineas.'.$index.'.importe') <span>{{ $message }}</span> @enderror
                                        </div>
                                    @endif
                            @endforeach
                        </div>
                    </div>
                @else
                    @foreach ($lineas as $index => $linea)
                        @php
                            $itemsLinea = $itemsPorRubro->get((int) ($linea['idRubro'] ?? 0), collect());
                        @endphp
                        <div class="rounded-2xl border border-accent-200 bg-white p-4 space-y-4 shadow-sm" wire:key="linea-{{ $index }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Concepto {{ $index + 1 }}</span>
                                @if (count($lineas) > 1)
                                    <button type="button" wire:click="quitarLinea({{ $index }})" class="text-xs font-semibold text-red-600 hover:text-red-700">Quitar</button>
                                @endif
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="se-label">Rubro</label>
                                    <select wire:model.live="lineas.{{ $index }}.idRubro" class="se-input w-full @error('lineas.'.$index.'.idRubro') ring-2 ring-red-400 @enderror">
                                        <option value="">— Seleccione —</option>
                                        @foreach ($rubros as $rubro)
                                            <option value="{{ $rubro->id }}">{{ $rubro->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('lineas.'.$index.'.idRubro') <p class="se-field-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="se-label">Ítem</label>
                                    <select wire:model.live="lineas.{{ $index }}.idItem" class="se-input w-full @error('lineas.'.$index.'.idItem') ring-2 ring-red-400 @enderror" @disabled($itemsLinea->isEmpty())>
                                        <option value="">— Seleccione —</option>
                                        @foreach ($itemsLinea as $item)
                                            <option value="{{ $item->id }}">{{ $item->nombre }}@if((float) $item->precio > 0) — ${{ number_format((float) $item->precio, 2, ',', '.') }}@endif</option>
                                        @endforeach
                                    </select>
                                    @error('lineas.'.$index.'.idItem') <p class="se-field-error">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="se-label">Importe</label>
                                <input type="number"
                                       step="0.01"
                                       min="0.01"
                                       wire:model.live="lineas.{{ $index }}.importe"
                                       class="se-input w-full sm:max-w-[14rem] tabular-nums font-semibold text-primary-700 @error('lineas.'.$index.'.importe') ring-2 ring-red-400 @enderror"
                                       placeholder="0,00">
                                @error('lineas.'.$index.'.importe') <p class="se-field-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="se-label">Detalle en recibo (opcional)</label>
                                <textarea wire:model="lineas.{{ $index }}.concepto"
                                          rows="2"
                                          class="se-input w-full"
                                          placeholder="Ej. Donación marzo, canon enero–marzo… Si se deja vacío se usa el nombre del ítem."></textarea>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            @if ($esOrigenEstudiantes)
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="se-label">Señor / pagador</label>
                        <div class="flex gap-2">
                            <input type="text"
                                   value="{{ $pagadorNombre }}"
                                   readonly
                                   tabindex="-1"
                                   placeholder="Elija el responsable de pago"
                                   class="se-input w-full min-w-0 cursor-pointer bg-white @error('pagadorNombre') ring-2 ring-red-400 @enderror"
                                   wire:click="abrirModalPagador"
                                   aria-readonly="true">
                            <button type="button"
                                    wire:click="abrirModalPagador"
                                    class="btn-secondary shrink-0 whitespace-nowrap"
                                    title="Editar responsables y elegir pagador">
                                Elegir
                            </button>
                        </div>
                        @if ($pagadorVinculo !== '')
                            <p class="mt-1 text-[11px] text-neutral-500">
                                Pagador: {{ ucfirst($pagadorVinculo) }}
                                @php $emailPag = \App\Support\Cooperadora\ResponsablesLegajoCooperadora::emailPagador($pagadorResponsables, $pagadorVinculo); @endphp
                                @if ($emailPag !== '')
                                    · {{ $emailPag }}
                                @endif
                            </p>
                        @endif
                        @error('pagadorNombre') <p class="se-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="se-label">Fecha</label>
                        <input type="date" wire:model="fecha" class="se-input w-full">
                        @error('fecha') <p class="se-field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="se-label">Medio de pago</label>
                        <select wire:model="idMedioPago" class="se-input w-full">
                            <option value="">— Seleccione —</option>
                            @foreach ($mediosPago as $medio)
                                <option value="{{ $medio->id }}">{{ $medio->nombre }}</option>
                            @endforeach
                        </select>
                        @error('idMedioPago') <p class="se-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col justify-end">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Total del recibo</p>
                        <p class="text-2xl font-bold tabular-nums text-primary-700">${{ number_format($totalImporte, 2, ',', '.') }}</p>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2 border-t border-accent-100 pt-4">
                <a href="{{ route('cooperadora.ingresos') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">Registrar y emitir recibo</button>
            </div>
        </form>
    </div>

    @script
    <script>
        $wire.on('se-swal-aviso', ({ mensaje, titulo }) => window.seSwalAviso(mensaje, titulo ?? 'Atención'));
        $wire.on('se-swal-error', ({ mensaje, titulo }) => window.seSwalError(mensaje, titulo ?? 'Error'));
    </script>
    @endscript

    @if ($modalPagadorAbierto)
        @teleport('body')
            @include('livewire.cooperadora.partials.modal-pagador-ingreso')
        @endteleport
    @endif
</div>
