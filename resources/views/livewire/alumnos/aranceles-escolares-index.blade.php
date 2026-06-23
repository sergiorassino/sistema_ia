<div class="se-page max-w-7xl mx-auto">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Portal familia</p>
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Aranceles Escolares</h2>
                <p class="text-sm text-white/85">
                    @if ($mostrarHistorial)
                        Historial completo de cuotas
                    @else
                        Cuotas pendientes de pago
                    @endif
                </p>
                @if ($encabezado)
                    <p class="pt-1 text-xs leading-relaxed text-white/70" aria-label="Datos del estudiante">
                        <span class="uppercase text-white/90">{{ $encabezado['apellido'] }}, {{ $encabezado['nombre'] }}</span>
                        <span class="mx-1.5 text-white/40" aria-hidden="true">·</span>
                        <span>DNI {{ $encabezado['dni'] }}</span>
                        @if ($encabezado['curso'] !== '')
                            <span class="mx-1.5 text-white/40" aria-hidden="true">·</span>
                            <span class="uppercase">{{ $encabezado['curso'] }}</span>
                        @endif
                        @if ($encabezado['nivel'] !== '')
                            <span class="mx-1.5 text-white/40" aria-hidden="true">·</span>
                            <span class="uppercase">{{ $encabezado['nivel'] }}</span>
                        @endif
                    </p>
                @endif
            </div>
            <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                <a href="{{ se_route_url('alumnos.aranceles-escolares.resumen-pagos', ['ref' => \App\Support\Security\OpaqueRouteToken::forResumenPagosAutogestion((int) studentCtx()->idLegajo)]) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    Resumen de Pagos
                </a>
                @if (! $mostrarHistorial && ($totalesAdeudados['neto'] ?? 0) > 0)
                    <a href="{{ se_route_url('alumnos.aranceles-escolares.cuotas-adeudadas', ['ref' => \App\Support\Security\OpaqueRouteToken::forCuotasAdeudadasAutogestion((int) studentCtx()->idLegajo)]) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                        Imprimir adeudadas
                    </a>
                @endif
                <button type="button"
                        wire:click="alternarVistaCuotas"
                        class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    {{ $mostrarHistorial ? 'Cuotas pendientes' : 'Historial' }}
                </button>
            </div>
        </div>
    </section>

    <section class="se-card p-0 overflow-hidden">
        @php
            $debitoAutomatico = tenantArancelesEscolaresDebitoAutomatico();
            $mediosPago = tenantArancelesEscolaresMediosPago();
        @endphp
        @if (! $mostrarHistorial && ($debitoAutomatico || (tenantCuotasSiroHabilitado() && $encabezado && $cuotas->isNotEmpty())))
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-accent-100 px-4 py-3 sm:px-6">
                @if (tenantCuotasSiroHabilitado() && $encabezado && $cuotas->isNotEmpty())
                    <div class="min-w-[240px] flex-1 rounded-xl border border-primary-200 bg-white px-4 py-3 text-center shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-800">
                            Código de pago electrónico
                        </p>
                        <p class="mt-1 text-base font-bold tabular-nums tracking-wide text-primary-900 sm:text-lg">
                            {{ $encabezado['codigoPagoElectronico'] }}
                        </p>
                    </div>
                @endif
                @if ($debitoAutomatico)
                    <a href="{{ $debitoAutomatico['pdf_url'] }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="ml-auto block max-w-md shrink-0 rounded-lg transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                       title="Formulario de adhesión a débito automático">
                        <img src="{{ $debitoAutomatico['banner_url'] }}"
                             alt="Ahorre tiempo haciendo el pago en débito automático — clic aquí"
                             class="h-auto w-full max-w-md"
                             width="480"
                             height="96">
                    </a>
                @endif
            </div>
        @endif

        @if ($cuotas->isEmpty())
            <div class="gf-empty py-14 text-center">
                <svg class="mx-auto mb-3 h-10 w-10 text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <p class="text-sm font-semibold text-neutral-700">
                    @if ($mostrarHistorial)
                        No hay cuotas registradas para este estudiante en ningún ciclo lectivo.
                    @else
                        No hay cuotas pendientes de pago
                    @endif
                </p>
            </div>
        @else
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="gf gf-vcenter gf-cuotas-autogestion min-w-[1108px]">
                        <div class="gf-head">
                            <div class="gf-th w-[180px]">Apellido y nombre</div>
                            <div class="gf-th w-[95px]">Dni</div>
                            <div class="gf-th w-[115px]">Sala/Grado/Curso</div>
                            <div class="gf-th w-[100px]">Nivel</div>
                            <div class="gf-th w-12">Año</div>
                            <div class="gf-th w-[120px]">Cuota</div>
                            <div class="gf-th w-[85px]">Venc 1</div>
                            <div class="gf-th w-[85px]">Venc 2</div>
                            <div class="gf-th w-[105px]">Actualizada al:</div>
                            @if ($mostrarHistorial)
                                <div class="gf-th gf-th-right w-[95px]">Pagado</div>
                                <div class="gf-th gf-th-right w-[95px]">Saldo</div>
                            @else
                                <div class="gf-th gf-th-right w-[95px]">Saldo</div>
                            @endif
                            <div class="gf-th gf-th-accion gf-th-accion-cupon" title="Cupón de pago">Cupón</div>
                            @if ($mostrarHistorial && $muestraComprobanteAfip)
                                <div class="gf-th gf-th-accion gf-th-accion-afip" title="Factura AFIP">AFIP</div>
                            @endif
                        </div>

                        @foreach ($cuotas as $c)
                            @php
                                $pagada = (float) ($c->faltapa ?? 0) <= 0;
                                $rowEstadoClass = $mostrarHistorial
                                    ? ($pagada ? 'gf-row--pagada' : 'gf-row--adeudada')
                                    : '';
                                $facturaAfip = $facturasAfip[(int) $c->id] ?? null;
                            @endphp
                            <div class="gf-row gf-row-hover {{ $rowEstadoClass }}"
                                 wire:key="cuota-{{ $c->id }}-{{ $mostrarHistorial ? 'hist' : 'pend' }}">
                                <div class="gf-td w-[180px] uppercase">{{ trim(trim((string) ($c->legajo->apellido ?? '')).', '.trim((string) ($c->legajo->nombre ?? ''))) }}</div>
                                <div class="gf-td w-[95px] tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearDni($c->legajo->dni ?? '') }}</div>
                                <div class="gf-td w-[115px] uppercase">{{ trim((string) ($c->curso?->nombreParaListado() ?? '')) }}</div>
                                <div class="gf-td w-[100px] uppercase">{{ trim((string) ($c->curso?->nivel?->nivel ?? '')) }}</div>
                                <div class="gf-td w-12 tabular-nums">{{ $c->terlec?->ano ?? '' }}</div>
                                <div class="gf-td w-[120px] font-bold uppercase text-primary-800">{{ trim((string) ($c->cuota?->nombre ?? '')) }}</div>
                                <div class="gf-td w-[85px] tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc1) }}</div>
                                <div class="gf-td w-[85px] tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc2) }}</div>
                                <div class="gf-td w-[105px] tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->nueVenc) }}</div>
                                @if ($mostrarHistorial)
                                    <div class="gf-td gf-th-right w-[95px] tabular-nums">
                                        {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($c->pagado) }}
                                    </div>
                                    <div class="gf-td gf-th-right w-[95px] font-bold tabular-nums">
                                        {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($c->faltapa) }}
                                    </div>
                                @else
                                    <div class="gf-td gf-th-right w-[95px] font-bold tabular-nums">
                                        {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($c->faltapa) }}
                                    </div>
                                @endif
                                <div class="gf-td gf-td-accion gf-td-accion-cupon !py-1">
                                    @if (! $mostrarHistorial || (float) $c->faltapa > 0)
                                        @if (\App\Support\Alumnos\ArancelesEscolares::cuotaVencidaParaReimpresion($c))
                                            <button type="button"
                                                    x-on:click="window.seSwalAviso(@js(\App\Support\Alumnos\ArancelesEscolares::mensajeCuotaVencidaReimpresion()), 'Cuota vencida')"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-400 bg-white text-neutral-400"
                                                    title="Cupón no disponible — cuota vencida">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                </svg>
                                                <span class="sr-only">Cupón no disponible</span>
                                            </button>
                                        @else
                                            <a href="{{ se_route_url('alumnos.aranceles-escolares.comprobante', ['ref' => \App\Support\Security\OpaqueRouteToken::forComprobantePagoCuota((int) $c->id, (int) studentCtx()->idLegajo)]) }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                               title="Emitir cupón de pago">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                </svg>
                                                <span class="sr-only">Emitir cupón de pago</span>
                                            </a>
                                        @endif
                                    @endif
                                </div>
                                @if ($mostrarHistorial && $muestraComprobanteAfip)
                                    <div class="gf-td gf-td-accion gf-td-accion-afip !py-1">
                                        @if ($pagada && $facturaAfip)
                                            <a href="{{ se_route_url('alumnos.aranceles-escolares.comprobante-afip', ['ref' => \App\Support\Security\OpaqueRouteToken::forComprobanteAfipAutogestion((int) $facturaAfip->idComprobanteAfip, (int) $c->id, (int) studentCtx()->idLegajo)]) }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                               title="Descargar factura AFIP">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                </svg>
                                                <span class="sr-only">Descargar factura AFIP</span>
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if (! $mostrarHistorial)
                            <div class="gf-row gf-row--totales gf-row--totales-inicio gf-cuotas-autogestion-totales"
                                 wire:key="ae-totales-neto">
                                <div class="gf-td w-[180px]" aria-hidden="true"></div>
                                <div class="gf-td w-[95px]" aria-hidden="true"></div>
                                <div class="gf-td w-[115px]" aria-hidden="true"></div>
                                <div class="gf-td w-[100px]" aria-hidden="true"></div>
                                <div class="gf-td w-12" aria-hidden="true"></div>
                                <div class="gf-td w-[120px] gf-td-total-label justify-end">Total neto</div>
                                <div class="gf-td w-[85px]" aria-hidden="true"></div>
                                <div class="gf-td w-[85px]" aria-hidden="true"></div>
                                <div class="gf-td w-[105px]" aria-hidden="true"></div>
                                <div class="gf-td gf-td-total-importe gf-th-right w-[95px] tabular-nums whitespace-nowrap">
                                    {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($totalesAdeudados['neto']) }}
                                </div>
                                <div class="gf-td gf-td-accion gf-td-accion-cupon" aria-hidden="true"></div>
                            </div>
                            <div class="gf-row gf-row--totales gf-cuotas-autogestion-totales"
                                 wire:key="ae-totales-intereses">
                                <div class="gf-td w-[180px]" aria-hidden="true"></div>
                                <div class="gf-td w-[95px]" aria-hidden="true"></div>
                                <div class="gf-td w-[115px]" aria-hidden="true"></div>
                                <div class="gf-td w-[100px]" aria-hidden="true"></div>
                                <div class="gf-td w-12" aria-hidden="true"></div>
                                <div class="gf-td w-[120px] gf-td-total-label justify-end">Total con intereses al día de hoy</div>
                                <div class="gf-td w-[85px]" aria-hidden="true"></div>
                                <div class="gf-td w-[85px]" aria-hidden="true"></div>
                                <div class="gf-td w-[105px]" aria-hidden="true"></div>
                                <div class="gf-td gf-td-total-importe gf-th-right w-[95px] tabular-nums whitespace-nowrap">
                                    {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($totalesAdeudados['conIntereses']) }}
                                </div>
                                <div class="gf-td gf-td-accion gf-td-accion-cupon" aria-hidden="true"></div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if (! $mostrarHistorial && $mediosPago)
                <div class="border-t border-accent-100 px-4 py-4 sm:px-6">
                    <a href="{{ $mediosPago['url'] }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       title="Medios de pago disponibles (se abre en una nueva pestaña)"
                       x-on:click.prevent="window.open($el.href, '_blank', 'noopener,noreferrer')"
                       class="mx-auto block w-1/2 transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                        <img src="{{ $mediosPago['banner_url'] }}"
                             alt="Medios de pago — clic para más información"
                             class="h-auto w-full">
                    </a>
                </div>
            @endif
        @endif
    </section>
</div>

@script
<script>
    (function () {
        @if (session('aranceles_cuota_vencida'))
            if (typeof window.seSwalAviso === 'function') {
                window.seSwalAviso(@js(session('aranceles_cuota_vencida')), 'Cuota vencida');
            }
        @endif
    })();
</script>
@endscript
