@php
    use App\Support\Security\OpaqueRouteToken;
@endphp

<div class="se-page max-w-[96rem] mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Cooperadora</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Pagos del estudiante</h1>
                @if ($encabezado)
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/90 sm:text-sm">
                        {{ $encabezado['apellido'] }} {{ $encabezado['nombre'] }}
                        <span class="font-normal text-white/75">— {{ $encabezado['dni'] }}</span>
                    </p>
                    @if (($encabezado['curso'] ?? '') !== '')
                        <p class="text-xs text-white/75">
                            {{ $encabezado['curso'] }} · Ciclo {{ $encabezado['terlecAno'] }}
                        </p>
                    @endif
                    @if (! empty($encabezado['esHermanoCooperadora']))
                        <p class="mt-1">
                            <span class="se-pill bg-white/15 text-white text-[10px]">Hermano — descuento cooperadora</span>
                        </p>
                    @endif
                @endif
            </div>
            <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                @if (! empty($encabezado['idMatriculaActiva']))
                    <button type="button"
                            wire:click="alternarHermanoCooperadora"
                            wire:loading.attr="disabled"
                            wire:target="alternarHermanoCooperadora"
                            class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 disabled:opacity-60">
                        <span wire:loading.remove wire:target="alternarHermanoCooperadora">
                            {{ ! empty($encabezado['esHermanoCooperadora']) ? 'Quitar marca hermano' : 'Marcar como hermano' }}
                        </span>
                        <span wire:loading wire:target="alternarHermanoCooperadora">…</span>
                    </button>
                @endif
                <a href="{{ se_route_url('cooperadora.pagos-estudiante.pdf', ['ref' => OpaqueRouteToken::forCoopPagosEstudiante($idLegajo)]) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    Imprimir PDF
                </a>
                <a href="{{ route('cooperadora.pagos-estudiante') }}"
                   wire:navigate
                   class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    Volver
                </a>
            </div>
        </div>
    </section>

    <section class="se-card p-0 overflow-hidden">
        @if ($filas === [])
            <div class="py-14 text-center text-sm text-neutral-600">
                Este estudiante no tiene pagos registrados en la cooperadora.
            </div>
        @else
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="gf gf-coop-pagos-estudiante">
                        <div class="gf-head">
                            <div class="gf-th gf-th-accion">Rec.</div>
                            <div class="gf-th gf-th-fecha">Fecha</div>
                            <div class="gf-th gf-th-recibo">Nº recibo</div>
                            <div class="gf-th gf-th-rubro">Rubro</div>
                            <div class="gf-th gf-th-item">Ítem</div>
                            <div class="gf-th gf-th-concepto">Concepto</div>
                            <div class="gf-th gf-th-pagador">Pagador</div>
                            <div class="gf-th gf-th-medio">Medio pago</div>
                            <div class="gf-th gf-th-importe">Imp. bruto</div>
                            <div class="gf-th gf-th-dto">Dto.</div>
                            <div class="gf-th gf-th-importe">Importe</div>
                            <div class="gf-th gf-th-recibo-dest">Recibo enviado a</div>
                            <div class="gf-th gf-th-estado">Estado</div>
                            <div class="gf-th gf-th-fecha-envio">Fecha envío</div>
                        </div>

                        @foreach ($filas as $fila)
                            @php
                                $estado = (string) ($fila['reciboEmailEstado'] ?? 'pendiente');
                                $refRecibo = OpaqueRouteToken::forCoopRecibo((int) $fila['idReferenciaRecibo']);
                            @endphp
                            <div class="gf-row gf-row-hover" wire:key="coop-pago-{{ $fila['idIngreso'] }}">
                                <div class="gf-td gf-td-accion !py-1">
                                    <a href="{{ route('cooperadora.recibo.pdf', ['ref' => $refRecibo]) }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                       title="Ver recibo">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                        <span class="sr-only">Ver recibo</span>
                                    </a>
                                </div>
                                <div class="gf-td gf-td-fecha tabular-nums">{{ $fila['fecha'] }}</div>
                                <div class="gf-td gf-td-recibo tabular-nums">{{ $fila['reciboNumero'] }}</div>
                                <div class="gf-td gf-td-rubro">{{ $fila['rubro'] }}</div>
                                <div class="gf-td gf-td-item">{{ $fila['item'] }}</div>
                                <div class="gf-td gf-td-concepto">{{ $fila['concepto'] }}</div>
                                <div class="gf-td gf-td-pagador">
                                    @if (($fila['pagadorNombre'] ?? '') !== '')
                                        <span class="block font-medium">{{ $fila['pagadorNombre'] }}</span>
                                    @endif
                                    @if (($fila['pagadorVinculo'] ?? '') !== '')
                                        <span class="block text-[11px] text-neutral-500">{{ $fila['pagadorVinculo'] }}</span>
                                    @endif
                                </div>
                                <div class="gf-td gf-td-medio">{{ $fila['medioPago'] }}</div>
                                <div class="gf-td gf-td-importe tabular-nums">{{ $fila['importeBruto'] }}</div>
                                <div class="gf-td gf-td-dto tabular-nums">{{ $fila['descuentoPct'] }}</div>
                                <div class="gf-td gf-td-importe tabular-nums font-semibold">{{ $fila['importe'] }}</div>
                                <div class="gf-td gf-td-recibo-dest">
                                    @if (($fila['reciboDestinatarioEmail'] ?? '') !== '')
                                        <span class="block text-neutral-800">{{ $fila['reciboDestinatarioEmail'] }}</span>
                                        @if (($fila['pagadorNombre'] ?? '') !== '')
                                            <span class="block text-[11px] text-neutral-500">{{ $fila['pagadorNombre'] }}</span>
                                        @endif
                                    @elseif (($fila['pagadorNombre'] ?? '') !== '')
                                        <span class="block text-neutral-600">{{ $fila['pagadorNombre'] }}</span>
                                        <span class="block text-[11px] text-neutral-400">Sin email registrado</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </div>
                                <div class="gf-td gf-td-estado">
                                    <span @class([
                                        'se-pill text-[10px]',
                                        'bg-primary-100 text-primary-800' => in_array($estado, ['simulado', 'enviado'], true),
                                        'bg-amber-100 text-amber-900' => $estado === 'pendiente',
                                        'bg-red-100 text-red-800' => $estado === 'error',
                                    ])>{{ $fila['reciboEmailEstadoEtiqueta'] }}</span>
                                </div>
                                <div class="gf-td gf-td-fecha-envio tabular-nums text-neutral-600">
                                    {{ $fila['reciboEmailEnviadoAt'] !== '' ? $fila['reciboEmailEnviadoAt'] : '—' }}
                                </div>
                            </div>
                        @endforeach

                        <div class="gf-row gf-row-totales font-semibold bg-accent-50">
                            <div class="gf-td gf-td-accion"></div>
                            <div class="gf-td gf-td-fecha"></div>
                            <div class="gf-td gf-td-recibo"></div>
                            <div class="gf-td gf-td-rubro"></div>
                            <div class="gf-td gf-td-item"></div>
                            <div class="gf-td gf-td-concepto text-right uppercase text-[10px] tracking-wide text-neutral-600">
                                Totales ({{ $totales['cantidad'] }})
                            </div>
                            <div class="gf-td gf-td-pagador"></div>
                            <div class="gf-td gf-td-medio"></div>
                            <div class="gf-td gf-td-importe"></div>
                            <div class="gf-td gf-td-dto"></div>
                            <div class="gf-td gf-td-importe tabular-nums">{{ $totales['importe'] }}</div>
                            <div class="gf-td gf-td-recibo-dest"></div>
                            <div class="gf-td gf-td-estado"></div>
                            <div class="gf-td gf-td-fecha-envio"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </section>
</div>

@script
<script>
    $wire.on('se-swal-exito', ({ mensaje, titulo }) => window.seSwalExito(mensaje, titulo ?? 'Listo'));
    $wire.on('se-swal-error', ({ mensaje, titulo }) => window.seSwalError(mensaje, titulo ?? 'Error'));
</script>
@endscript
