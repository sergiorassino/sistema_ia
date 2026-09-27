@php
    use App\Support\Cuotas\CuotasFormato;
@endphp

@if (! empty($vistaPreviaCobro))
    @php
        $totalPrevio = (int) ($vistaPreviaCobro['total'] ?? 0);
        $totalAlumnosPrevio = (int) ($vistaPreviaCobro['totalAlumnos'] ?? 0);
        $filasPrevio = $vistaPreviaCobro['filas'] ?? [];
    @endphp
    <section class="se-card mt-4 overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
            <p class="text-sm font-semibold text-neutral-800">Vista previa de facturación</p>
            <p class="mt-1 text-xs text-neutral-600">
                <span class="font-semibold tabular-nums">{{ $totalAlumnosPrevio }}</span> estudiante(s)
                · Se facturará <span class="font-semibold tabular-nums">{{ $totalPrevio }}</span>
            </p>
        </div>
        <div class="max-h-[min(50dvh,28rem)] overflow-y-auto px-4 py-3 sm:px-5">
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="gf gf-vcenter gf-facturacion-afip-previa">
                        <div class="gf-head text-[10px]">
                            <div class="gf-th gf-th-apellido">Apellido</div>
                            <div class="gf-th gf-th-nombre">Nombre</div>
                            <div class="gf-th gf-th-dni">DNI</div>
                            <div class="gf-th gf-th-destinatario">Destinatario</div>
                            <div class="gf-th gf-th-dni-dest" title="DNI del destinatario">DNI R.</div>
                            <div class="gf-th gf-th-cuota">Cuota</div>
                            <div class="gf-th gf-th-importe">Importe</div>
                            <div class="gf-th gf-th-estado">Estado</div>
                            <div class="gf-th gf-th-accion" title="Destinatario de facturación AFIP">Resp.</div>
                        </div>
                        @foreach ($filasPrevio as $fila)
                            @php
                                $puedeFila = (bool) ($fila['puedeFacturar'] ?? false);
                                $idFamiliaFila = (int) ($fila['idFamilia'] ?? 0);
                            @endphp
                            <div class="gf-row gf-row-hover text-[11px]"
                                 wire:key="prev-cobro-afip-{{ $fila['idLegajo'] ?? 0 }}-{{ $loop->index }}">
                                <div class="gf-td gf-td-apellido font-medium text-neutral-800">{{ $fila['apellido'] ?? '' }}</div>
                                <div class="gf-td gf-td-nombre text-neutral-800">{{ $fila['nombre'] ?? '' }}</div>
                                <div class="gf-td gf-td-dni tabular-nums text-neutral-700">
                                    {{ CuotasFormato::formatearDni($fila['dni'] ?? '') }}
                                </div>
                                <div class="gf-td gf-td-destinatario text-neutral-800" title="{{ $fila['destinatario'] ?: '' }}">{{ $fila['destinatario'] ?: '—' }}</div>
                                <div class="gf-td gf-td-dni-dest tabular-nums text-neutral-700">
                                    @if (filled($fila['dniDestinatario'] ?? ''))
                                        {{ CuotasFormato::formatearDni($fila['dniDestinatario']) }}
                                    @else
                                        —
                                    @endif
                                </div>
                                <div class="gf-td gf-td-cuota text-neutral-800">{{ $fila['cuotaNombre'] ?? '' }}</div>
                                <div class="gf-td gf-td-importe tabular-nums">
                                    @if ((float) ($fila['importe'] ?? 0) > 0)
                                        {{ CuotasFormato::formatearImporte($fila['importe']) }}
                                    @else
                                        —
                                    @endif
                                </div>
                                <div class="gf-td gf-td-estado {{ $puedeFila ? 'text-emerald-800' : 'text-amber-900' }}"
                                     title="{{ $puedeFila ? 'Se facturará' : ($fila['estado'] ?? '') }}">
                                    {{ $puedeFila ? 'Se facturará' : ($fila['estado'] ?? '') }}
                                </div>
                                <div class="gf-td gf-td-accion !py-1">
                                    @if ($idFamiliaFila > 0 || (int) ($fila['idLegajo'] ?? 0) > 0)
                                        <button type="button"
                                                wire:click="abrirModalRespAdmi({{ (int) ($fila['idLegajo'] ?? 0) }})"
                                                title="Destinatario de facturación AFIP"
                                                class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-1 py-0.5 text-[9px] font-semibold leading-tight text-primary-700 hover:bg-accent-50">
                                            Resp.
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
            @if (! empty($mostrarVolverSinFacturar))
                <button type="button"
                        x-data
                        x-on:click="
                            seSwalConfirmar(
                                @js('¿Seguro que quiere dejar registrado el pago sin facturar?'),
                                'Volver sin facturar',
                                { confirmButtonText: 'Sí, volver' }
                            ).then(ok => { if (ok) $wire.volverSinFacturarCobro(); })
                        "
                        class="inline-flex items-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-accent-50">
                    Volver sin facturar
                </button>
            @endif
            <button type="button"
                    wire:loading.attr="disabled"
                    wire:target="emitirFacturacionCobro"
                    @disabled($totalPrevio < 1)
                    x-data
                    x-on:click="
                        seSwalConfirmar(
                            @js('Se emitirán comprobantes AFIP para '.$totalPrevio.' estudiante(s).'),
                            '¿Confirma la facturación?'
                        ).then(ok => { if (ok) $wire.emitirFacturacionCobro(); })
                    "
                    class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="emitirFacturacionCobro">Facturar en AFIP</span>
                <span wire:loading wire:target="emitirFacturacionCobro">Facturando…</span>
            </button>
        </div>
    </section>
@endif
