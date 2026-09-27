<div>
<div class="se-page max-w-6xl mx-auto">
    <section class="se-hero mb-6">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Gestión masiva</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl uppercase">
                    Discriminación de cuotas — Año {{ $ano }}
                </h1>
                <p class="text-sm text-white/80 max-w-xl">
                    Elija una cuota. Los ítems de la factura se definen en cada curso.
                </p>
            </div>
        </div>
    </section>

    @if (! $tablasListas)
        <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
            @if ($esquemaAnterior)
                Las tablas de discriminación están en una versión anterior. Hay que volver a crearlas: cada ítem, con su importe, pertenece a un curso.
            @else
                Faltan las tablas de discriminación. Hay que crearlas antes de usar esta pantalla.
            @endif
        </div>
    @else
        <div class="se-toolbar se-toolbar-pocos-campos mb-4" x-data x-init="$nextTick(() => $refs.cuotasDetalleBuscar?.focus())">
            <div class="relative w-full max-w-xs">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input wire:model.live.debounce.300ms="search"
                       type="search"
                       x-ref="cuotasDetalleBuscar"
                       placeholder="Búsqueda por nombre"
                       class="form-input pl-9 text-sm"
                       autocomplete="off">
            </div>
        </div>

        <div class="se-card overflow-hidden p-2 sm:p-3">
            <div class="se-cuotas-importes-lista-scroll">
                <table class="se-cuotas-importes-lista-tabla se-cuotas-detalle-index-tabla">
                    <thead>
                        <tr>
                            <th scope="col" class="se-cil-th-nombre">Nombre de la cuota</th>
                            <th scope="col" class="se-cil-th-venc">Cursos</th>
                            <th scope="col" class="se-cil-th-accion"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cuotas as $cuota)
                            <tr wire:key="cuota-detalle-{{ $cuota->id }}">
                                <td class="se-cil-td-nombre">{{ $cuota->nombre }}</td>
                                <td class="se-cil-td-venc tabular-nums">{{ (int) ($cursosPorCuota[$cuota->id] ?? 0) }}</td>
                                <td class="se-cil-td-accion">
                                    <div class="inline-flex items-center justify-center gap-1.5">
                                        <button type="button"
                                                wire:click="abrirCopiar({{ $cuota->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="abrirCopiar({{ $cuota->id }})"
                                                @disabled((int) ($cursosPorCuota[$cuota->id] ?? 0) === 0)
                                                title="{{ (int) ($cursosPorCuota[$cuota->id] ?? 0) === 0 ? 'Esta cuota no tiene discriminación para copiar' : 'Copiar esta discriminación a otras cuotas' }}"
                                                class="inline-flex items-center justify-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-[10px] font-semibold text-primary-700 shadow-sm transition hover:border-primary-500 hover:bg-accent-50 whitespace-nowrap disabled:cursor-not-allowed disabled:opacity-50">
                                            Copiar a
                                        </button>
                                        <button type="button"
                                                wire:click="abrirEditor({{ $cuota->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="abrirEditor"
                                                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-1.5 text-[10px] font-semibold text-white shadow-sm transition hover:bg-primary-700 whitespace-nowrap disabled:opacity-60">
                                            Discriminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-10 text-center text-sm text-neutral-500">
                                    @if (trim($search) !== '')
                                        No hay cuotas que coincidan con la búsqueda.
                                    @else
                                        No hay cuotas del año {{ $ano }}. Cree plantillas en «Crear / Editar Cuotas».
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

    @teleport('body')
        <div>
            @if ($modalCopiar)
                <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="copiar-cuota-detalle-titulo">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarCopiar"></div>
                    <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),40rem)]">
                        <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                            <h2 id="copiar-cuota-detalle-titulo" class="text-base font-semibold text-neutral-800">Copiar a</h2>
                            <p class="mt-1 text-xs text-neutral-500">
                                Desde {{ $nombreCuotaOrigen }}. Se copian los ítems y los importes de todos los cursos.
                            </p>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-3">
                            <p class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
                                Si existen registros en la cuota de destino, se van a sobrescribir.
                            </p>
                            @if ($opcionesDestino->isEmpty())
                                <p class="text-sm text-neutral-500">No hay otras cuotas en este año.</p>
                            @else
                                <div class="mb-3 flex gap-2">
                                    <button type="button" wire:click="marcarTodosDestino" class="rounded-lg border border-accent-200 px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-accent-50">Marcar todas</button>
                                    <button type="button" wire:click="limpiarDestino" class="rounded-lg border border-accent-200 px-3 py-1.5 text-xs font-semibold text-neutral-600 hover:bg-accent-50">Quitar todas</button>
                                </div>
                                <ul class="space-y-1">
                                    @foreach ($opcionesDestino as $destino)
                                        <li wire:key="dest-cuota-{{ $destino->id }}">
                                            <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50">
                                                <input type="checkbox" value="{{ $destino->id }}" wire:model="cuotasDestino" class="rounded border-accent-200 text-primary-600 focus:ring-primary-500">
                                                <span class="min-w-0 flex-1 text-sm text-neutral-800">{{ $destino->nombre }}</span>
                                                @if ((int) ($cursosDestinoConteo[$destino->id] ?? 0) > 0)
                                                    <span class="shrink-0 text-[10px] tabular-nums text-amber-700">{{ (int) $cursosDestinoConteo[$destino->id] }} cursos</span>
                                                @endif
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @error('cuotasDestino')
                                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex shrink-0 justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-3">
                            <button type="button" wire:click="cerrarCopiar" class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-white">
                                Cancelar
                            </button>
                            <button type="button"
                                    wire:click="solicitarCopia"
                                    wire:loading.attr="disabled"
                                    wire:target="solicitarCopia,copiarACuotas"
                                    @disabled($opcionesDestino->isEmpty())
                                    class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60">
                                Copiar
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
        $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
        $wire.on('cuotas-detalle-confirmar-copia', () => {
            window.seSwalConfirmar(
                'Si existen registros en la cuota de destino, se van a sobrescribir.',
                'Copiar discriminación',
                { confirmButtonText: 'Sí, copiar', icon: 'warning' }
            ).then((ok) => { if (ok) $wire.copiarACuotas(); });
        });
    </script>
    @endscript
</div>
