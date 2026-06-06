<div class="se-page max-w-7xl mx-auto">
    @if (session('success'))
        <div class="mb-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Gestión masiva</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Editar cuotas generadas</h1>
                <p class="text-xs text-white/75">
                    Ciclo lectivo {{ $ano }} · Puede modificar varios datos a la vez en el mismo paso
                </p>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden mb-4">
        <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
            <p class="text-sm text-neutral-700">
                Defina los filtros y pulse <strong>Buscar</strong>. Complete solo los campos que desea cambiar;
                el botón de aplicar se habilita cuando haya al menos un valor nuevo.
            </p>
        </div>

        <div class="grid gap-4 px-4 py-4 sm:grid-cols-2 sm:px-5">
            <div>
                <label for="filtro-nivel-cg" class="form-label">Nivel</label>
                <select id="filtro-nivel-cg" wire:model.live="idNivel" class="form-input">
                    <option value="0">Todos</option>
                    @foreach ($niveles as $nivel)
                        <option value="{{ (int) $nivel['id'] }}">{{ $nivel['nombre'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filtro-curso-cg" class="form-label">Curso</label>
                <select id="filtro-curso-cg" wire:model.live="idCurso" class="form-input">
                    <option value="0">Todos</option>
                    @foreach ($cursos as $curso)
                        <option value="{{ (int) $curso->Id }}">{{ $etiquetaCurso($curso) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filtro-cuota-cg" class="form-label">Cuota</label>
                <select id="filtro-cuota-cg" wire:model.live="idCuota" class="form-input">
                    <option value="0">Todas</option>
                    @foreach ($cuotas as $cuota)
                        <option value="{{ (int) $cuota->id }}">{{ trim((string) $cuota->nombre) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <span class="form-label">Pagado</span>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <select wire:model.live="pagadoOp"
                            class="form-input w-full min-w-[9rem] max-w-[11rem] shrink-0"
                            aria-label="Comparador pagado">
                        @foreach ($opcionesComparador as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <input type="text"
                           inputmode="decimal"
                           placeholder="0,00"
                           wire:model.live="pagadoValor"
                           class="form-input w-full min-w-[6rem] flex-1 tabular-nums"
                           @disabled($pagadoOp === '')
                           aria-label="Pagado" />
                </div>
            </div>

            <div class="sm:col-span-2">
                <span class="form-label">Saldo (faltapa)</span>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <select wire:model.live="saldoOp"
                            class="form-input w-full min-w-[9rem] max-w-[11rem] shrink-0"
                            aria-label="Comparador saldo">
                        @foreach ($opcionesComparador as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <input type="text"
                           inputmode="decimal"
                           placeholder="0,00"
                           wire:model.live="saldoValor"
                           class="form-input w-full min-w-[6rem] flex-1 tabular-nums"
                           @disabled($saldoOp === '')
                           aria-label="Saldo" />
                </div>
            </div>
        </div>

        @error('filtros')
            <div class="px-4 pb-2 sm:px-5">
                <p class="form-error">{{ $message }}</p>
            </div>
        @enderror

        <div class="flex flex-wrap gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-4 sm:px-5">
            <button type="button"
                    wire:click="buscar"
                    wire:loading.attr="disabled"
                    wire:target="buscar"
                    class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="buscar">Buscar</span>
                <span wire:loading wire:target="buscar">Buscando…</span>
            </button>
            <button type="button"
                    wire:click="limpiarFiltros"
                    class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-accent-50">
                Limpiar filtros
            </button>
        </div>
    </div>

    @if ($mostrandoResultados)
        @if ($totalRegistros > 0)
            <div class="se-card overflow-hidden mb-4">
                <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                    <p class="text-sm font-semibold text-neutral-800">
                        <span class="tabular-nums">{{ $totalSeleccionados }}</span> de
                        <span class="tabular-nums">{{ $totalRegistros }}</span>
                        {{ $totalRegistros === 1 ? 'registro seleccionado para actualizar' : 'registros seleccionados para actualizar' }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-600">
                        Vista previa de los datos actuales. Todas las filas del filtro quedan marcadas; desmarque las que no desee modificar.
                    </p>
                </div>

                <div class="se-toolbar flex-wrap gap-3 border-b border-accent-200 px-4 py-3 sm:px-5">
                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                                wire:click="seleccionarTodosRegistros"
                                class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                            Todos
                        </button>
                        <button type="button"
                                wire:click="quitarTodosRegistros"
                                class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                            Ninguno
                        </button>
                    </div>
                    <div class="relative w-full max-w-md min-w-[12rem] flex-1">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <input wire:model.live.debounce.300ms="search"
                               type="search"
                               placeholder="Buscar en el listado"
                               class="form-input pl-9 text-sm py-1.5"
                               autocomplete="off">
                    </div>
                </div>

                <div class="max-h-[min(22rem,45dvh)] w-full overflow-auto">
                    <table class="w-full min-w-[52rem] text-left text-xs">
                        <thead class="sticky top-0 z-[1] bg-accent-50/95 backdrop-blur-sm">
                            <tr class="border-b border-accent-200">
                                <th scope="col" class="w-10 px-2 py-2">
                                    <span class="sr-only">Incluir en la actualización</span>
                                </th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600">Estudiante</th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600">Curso</th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600">Cuota</th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600">Beca</th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600 text-right">Importe</th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600 text-right">Pagado</th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600 text-right">Saldo</th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600">Venc. 1</th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600">Venc. 2</th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600">Venc. 3</th>
                                <th scope="col" class="px-3 py-2 font-semibold uppercase tracking-wide text-neutral-600">Venc. act.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($registros as $row)
                                <tr @class(['border-b border-accent-100', 'opacity-75' => ! ($row['puedeModificarImporte'] ?? false)])
                                    wire:key="cg-prev-{{ $row['id'] }}">
                                    <td class="px-2 py-2 text-center">
                                        <input type="checkbox"
                                               class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                               wire:model.live="idsRegistrosSeleccionados"
                                               value="{{ (int) $row['id'] }}"
                                               aria-label="Incluir {{ $row['estudiante'] }}">
                                    </td>
                                    <td class="px-3 py-2 max-w-[11rem] truncate font-medium text-neutral-800" title="{{ $row['estudiante'] }}">{{ $row['estudiante'] }}</td>
                                    <td class="px-3 py-2 max-w-[9rem] truncate text-neutral-700" title="{{ $row['cursoLabel'] }}">{{ $row['cursoLabel'] }}</td>
                                    <td class="px-3 py-2 max-w-[7rem] truncate text-neutral-600" title="{{ $row['cuotaNombre'] }}">{{ $row['cuotaNombre'] }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $row['beca'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['importe'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-neutral-600">{{ $row['pagado'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium text-primary-800">{{ $row['saldo'] }}</td>
                                    <td class="px-3 py-2 text-neutral-700">{{ $row['venc1'] ?: '—' }}</td>
                                    <td class="px-3 py-2 text-neutral-700">{{ $row['venc2'] ?: '—' }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $row['venc3'] ?: '—' }}</td>
                                    <td class="px-3 py-2 text-neutral-700">{{ $row['nueVenc'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-4 py-6 text-center text-sm text-neutral-600">
                                        Ningún registro coincide con la búsqueda en pantalla.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="se-card overflow-hidden">
                <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                    <p class="se-section-title !text-left !mb-0">Valores nuevos (complete solo lo que desea cambiar)</p>
                </div>

                <div class="grid gap-4 px-4 py-4 sm:grid-cols-2 lg:grid-cols-3 sm:px-5">
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label for="nuevo-importe-base" class="form-label">Importe base</label>
                        <input id="nuevo-importe-base"
                               type="text"
                               inputmode="decimal"
                               placeholder="Sin cambio"
                               wire:model.live="nuevoImporte"
                               class="form-input w-full tabular-nums text-right"
                               aria-describedby="ayuda-importe-base">
                        <p id="ayuda-importe-base" class="mt-1.5 text-[11px] leading-relaxed text-neutral-600">
                            Solo cuotas con <strong>pagado ≤ 0</strong> y <strong>saldo &gt; 0</strong>
                            ({{ $elegiblesImporte }} {{ $elegiblesImporte === 1 ? 'elegible' : 'elegibles' }}).
                            El importe final se calcula con la beca cargada en cada cuota del alumno.
                        </p>
                        @error('nuevoImporte')
                            <p class="mt-1 form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="nuevo-venc1" class="form-label">Vencimiento 1</label>
                        <input id="nuevo-venc1"
                               type="date"
                               wire:model.live="nuevoVenc1"
                               class="form-input w-full"
                               aria-label="Nuevo vencimiento 1">
                        @error('nuevoVenc1')
                            <p class="mt-1 form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="nuevo-venc2" class="form-label">Vencimiento 2</label>
                        <input id="nuevo-venc2"
                               type="date"
                               wire:model.live="nuevoVenc2"
                               class="form-input w-full"
                               aria-label="Nuevo vencimiento 2">
                        @error('nuevoVenc2')
                            <p class="mt-1 form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="nuevo-venc3-lectura" class="form-label">Vencimiento 3</label>
                        <input id="nuevo-venc3-lectura"
                               type="date"
                               value="{{ $nuevoVenc2 }}"
                               readonly
                               tabindex="-1"
                               class="form-input w-full bg-accent-50/80 text-neutral-600"
                               title="Solo lectura: al cambiar venc. 2 se guarda igual al 2.º"
                               aria-label="Vencimiento 3 (igual al 2.º)">
                        <p class="mt-1 text-[11px] text-neutral-500">Solo lectura · copia venc. 2</p>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-1">
                        <label for="nuevo-nuevenc" class="form-label">Vencimiento actualizado</label>
                        <input id="nuevo-nuevenc"
                               type="date"
                               wire:model.live="nuevoNueVenc"
                               class="form-input w-full @if($limpiarNueVenc) opacity-50 @endif"
                               @disabled($limpiarNueVenc)
                               aria-label="Nuevo vencimiento actualizado">
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-neutral-700">
                            <input type="checkbox"
                                   @checked($limpiarNueVenc)
                                   wire:click="alternarLimpiarNueVenc"
                                   class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                            Quitar vencimiento actualizado
                        </label>
                        @error('nuevoNueVenc')
                            <p class="mt-1 form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @error('cambios')
                    <div class="px-4 pb-2 sm:px-5">
                        <p class="form-error">{{ $message }}</p>
                    </div>
                @enderror

                <div class="border-t border-accent-200 bg-accent-50/60 px-4 py-4 sm:px-5">
                    <button type="button"
                            wire:loading.attr="disabled"
                            wire:target="aplicarMasivo"
                            @disabled(! $puedeAplicarMasivo)
                            x-on:click="window.seSwalConfirmar(
                                @js($textoConfirmacion),
                                'Confirmar edición masiva',
                                { confirmButtonText: 'Sí, aplicar' }
                            ).then((ok) => { if (ok) $wire.aplicarMasivo(); })"
                            class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span wire:loading.remove wire:target="aplicarMasivo">Aplicar cambios masivos</span>
                        <span wire:loading wire:target="aplicarMasivo">Aplicando…</span>
                    </button>
                    @if (! $puedeAplicarMasivo)
                        <p class="mt-2 text-xs text-neutral-500">
                            @if ($totalSeleccionados < 1)
                                Seleccione al menos un registro en la vista previa y complete un campo nuevo.
                            @else
                                Complete al menos un campo nuevo para habilitar la acción.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        @else
            <div class="se-card px-4 py-8 sm:px-5">
                <p class="text-center text-sm text-neutral-600">
                    No se encontraron cuotas con los filtros indicados.
                </p>
            </div>
        @endif
    @endif

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            $wire.on('se-swal-error', (event) => {
                const mensaje = mensajeDeEvento(event, 'No se pudo completar la operación.');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                }
            });
        })();
    </script>
    @endscript
</div>
