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
            <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <a href="{{ se_route_url('alumnos.aranceles-escolares.resumen-pagos', ['ref' => \App\Support\Security\OpaqueRouteToken::forResumenPagosAutogestion((int) studentCtx()->idLegajo)]) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex w-full items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/20 sm:w-auto sm:py-2">
                    Resumen de Pagos
                </a>
                @if (! $mostrarHistorial && ($totalesAdeudados['neto'] ?? 0) > 0)
                    <a href="{{ se_route_url('alumnos.aranceles-escolares.cuotas-adeudadas', ['ref' => \App\Support\Security\OpaqueRouteToken::forCuotasAdeudadasAutogestion((int) studentCtx()->idLegajo)]) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex w-full items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/20 sm:w-auto sm:py-2">
                        Imprimir adeudadas
                    </a>
                @endif
                <button type="button"
                        wire:click="alternarVistaCuotas"
                        class="inline-flex w-full items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/20 sm:w-auto sm:py-2">
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
            <div class="flex flex-col gap-3 border-b border-accent-100 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-4 sm:px-6">
                @if (tenantCuotasSiroHabilitado() && $encabezado && $cuotas->isNotEmpty())
                    <div class="w-full min-w-0 flex-1 rounded-xl border border-primary-200 bg-white px-4 py-3 text-center shadow-sm sm:min-w-[240px]">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-800">
                            Código de pago electrónico
                        </p>
                        <p class="mt-1 break-all text-base font-bold tabular-nums tracking-wide text-primary-900 sm:text-lg">
                            {{ $encabezado['codigoPagoElectronico'] }}
                        </p>
                    </div>
                @endif
                @if ($debitoAutomatico)
                    <a href="{{ $debitoAutomatico['pdf_url'] }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="block w-full max-w-md shrink-0 rounded-lg transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 sm:ml-auto"
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
            {{-- Mobile: cards --}}
            <div class="space-y-3 p-4 md:hidden">
                @foreach ($cuotas as $c)
                    @php
                        $pagada = (float) ($c->faltapa ?? 0) <= 0;
                        $facturaAfip = $facturasAfip[(int) $c->id] ?? null;
                        $nombreCuota = trim((string) ($c->cuota?->nombre ?? ''));
                        $nombreCurso = trim((string) ($c->curso?->nombreParaListado() ?? ''));
                        $nombreNivel = trim((string) ($c->curso?->nivel?->nivel ?? ''));
                        $cardEstadoClass = $mostrarHistorial
                            ? ($pagada ? 'border-green-200 bg-green-50/80' : 'border-accent-200 bg-white')
                            : 'border-accent-200 bg-white';
                    @endphp
                    <article wire:key="cuota-m-{{ $c->id }}-{{ $mostrarHistorial ? 'hist' : 'pend' }}"
                             class="rounded-xl border p-4 shadow-sm {{ $cardEstadoClass }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Cuota</p>
                                <p class="mt-0.5 text-sm font-bold uppercase leading-snug text-primary-800">{{ $nombreCuota }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                @if ($mostrarHistorial)
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Saldo</p>
                                @endif
                                <p class="text-base font-bold tabular-nums text-neutral-900">
                                    {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($c->faltapa) }}
                                </p>
                            </div>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs text-neutral-700">
                            <div class="min-w-0">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">DNI</dt>
                                <dd class="mt-0.5 tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearDni($c->legajo->dni ?? '') }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Año</dt>
                                <dd class="mt-0.5 tabular-nums">{{ $c->terlec?->ano ?? '' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Curso</dt>
                                <dd class="mt-0.5 uppercase">{{ $nombreCurso }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Nivel</dt>
                                <dd class="mt-0.5 uppercase">{{ $nombreNivel }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Venc 1</dt>
                                <dd class="mt-0.5 tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc1) }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Venc 2</dt>
                                <dd class="mt-0.5 tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc2) }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Actualizada</dt>
                                <dd class="mt-0.5 tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->nueVenc) }}</dd>
                            </div>
                            @if ($mostrarHistorial)
                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Pagado</dt>
                                    <dd class="mt-0.5 tabular-nums font-semibold">{{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($c->pagado) }}</dd>
                                </div>
                            @endif
                        </dl>

                        <div class="mt-4 flex flex-col gap-2 border-t border-accent-100/80 pt-3">
                            @if (! $mostrarHistorial || (float) $c->faltapa > 0)
                                @if (\App\Support\Alumnos\ArancelesEscolares::cuotaVencidaParaReimpresion($c))
                                    <button type="button"
                                            x-on:click="window.seSwalAviso(@js(\App\Support\Alumnos\ArancelesEscolares::mensajeCuotaVencidaReimpresion()), 'Cuota vencida')"
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-400"
                                            title="Cupón no disponible — cuota vencida">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                        Cupón no disponible
                                    </button>
                                @else
                                    <a href="{{ se_route_url('alumnos.aranceles-escolares.comprobante', ['ref' => \App\Support\Security\OpaqueRouteToken::forComprobantePagoCuota((int) $c->id, (int) studentCtx()->idLegajo)]) }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-primary-200 bg-primary-50 px-4 py-2.5 text-sm font-semibold text-primary-800 hover:bg-primary-100"
                                       title="Emitir cupón de pago">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                        Emitir cupón
                                    </a>
                                @endif
                            @endif
                            @if ($muestraComprobanteAfip && $facturaAfip)
                                <a href="{{ se_route_url('alumnos.aranceles-escolares.comprobante-afip', ['ref' => \App\Support\Security\OpaqueRouteToken::forComprobanteAfipAutogestion((int) $facturaAfip->idComprobanteAfip, (int) $c->id, (int) studentCtx()->idLegajo)]) }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-800 hover:bg-accent-50"
                                   title="Descargar factura">
                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    Descargar factura
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach

                @if (! $mostrarHistorial)
                    <div class="rounded-xl border border-primary-200 bg-accent-50 px-4 py-3" wire:key="ae-totales-m">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-semibold uppercase tracking-wide text-neutral-700">Total neto</span>
                            <span class="font-bold tabular-nums text-neutral-900">
                                {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($totalesAdeudados['neto']) }}
                            </span>
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-3 border-t border-accent-200 pt-2 text-sm">
                            <span class="font-semibold uppercase tracking-wide text-neutral-700">Total con intereses</span>
                            <span class="font-bold tabular-nums text-red-700">
                                {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($totalesAdeudados['conIntereses']) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Desktop: planilla con scroll horizontal --}}
            <div class="hidden w-full overflow-x-auto md:block">
                <div class="flex justify-start">
                    <div class="gf gf-vcenter gf-cuotas-autogestion">
                        <div class="gf-head">
                            <div class="gf-th gf-col-dni">Dni</div>
                            <div class="gf-th gf-col-curso">Sala/Grado/Curso</div>
                            <div class="gf-th gf-col-nivel">Nivel</div>
                            <div class="gf-th gf-col-ano">Año</div>
                            <div class="gf-th gf-col-cuota">Cuota</div>
                            <div class="gf-th gf-col-fecha">Venc 1</div>
                            <div class="gf-th gf-col-fecha">Venc 2</div>
                            <div class="gf-th gf-col-fecha" title="Actualizada al">Act.</div>
                            @if ($mostrarHistorial)
                                <div class="gf-th gf-th-right gf-col-importe">Pagado</div>
                                <div class="gf-th gf-th-right gf-col-importe">Saldo</div>
                            @else
                                <div class="gf-th gf-th-right gf-col-importe">Saldo</div>
                            @endif
                            <div class="gf-th gf-th-accion gf-th-accion-cupon" title="Cupón de pago">Cupón</div>
                            @if ($muestraComprobanteAfip)
                                <div class="gf-th gf-th-accion gf-th-accion-afip" title="Descargar factura">Factura</div>
                            @endif
                        </div>

                        @foreach ($cuotas as $c)
                            @php
                                $pagada = (float) ($c->faltapa ?? 0) <= 0;
                                $rowEstadoClass = $mostrarHistorial
                                    ? ($pagada ? 'gf-row--pagada' : 'gf-row--adeudada')
                                    : '';
                                $facturaAfip = $facturasAfip[(int) $c->id] ?? null;
                                $nombreCuota = trim((string) ($c->cuota?->nombre ?? ''));
                                $nombreCurso = trim((string) ($c->curso?->nombreParaListado() ?? ''));
                                $nombreNivel = trim((string) ($c->curso?->nivel?->nivel ?? ''));
                            @endphp
                            <div class="gf-row gf-row-hover {{ $rowEstadoClass }}"
                                 wire:key="cuota-{{ $c->id }}-{{ $mostrarHistorial ? 'hist' : 'pend' }}">
                                <div class="gf-td gf-col-dni">{{ \App\Support\Alumnos\ArancelesEscolares::formatearDni($c->legajo->dni ?? '') }}</div>
                                <div class="gf-td gf-col-curso" title="{{ $nombreCurso }}">{{ $nombreCurso }}</div>
                                <div class="gf-td gf-col-nivel" title="{{ $nombreNivel }}">{{ $nombreNivel }}</div>
                                <div class="gf-td gf-col-ano tabular-nums">{{ $c->terlec?->ano ?? '' }}</div>
                                <div class="gf-td gf-col-cuota font-bold uppercase text-primary-800" title="{{ $nombreCuota }}">{{ $nombreCuota }}</div>
                                <div class="gf-td gf-col-fecha">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc1) }}</div>
                                <div class="gf-td gf-col-fecha">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc2) }}</div>
                                <div class="gf-td gf-col-fecha">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->nueVenc) }}</div>
                                @if ($mostrarHistorial)
                                    <div class="gf-td gf-th-right gf-col-importe">
                                        {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($c->pagado) }}
                                    </div>
                                    <div class="gf-td gf-th-right gf-col-importe font-bold">
                                        {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($c->faltapa) }}
                                    </div>
                                @else
                                    <div class="gf-td gf-th-right gf-col-importe font-bold">
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
                                @if ($muestraComprobanteAfip)
                                    <div class="gf-td gf-td-accion gf-td-accion-afip !py-1">
                                        @if ($facturaAfip)
                                            <a href="{{ se_route_url('alumnos.aranceles-escolares.comprobante-afip', ['ref' => \App\Support\Security\OpaqueRouteToken::forComprobanteAfipAutogestion((int) $facturaAfip->idComprobanteAfip, (int) $c->id, (int) studentCtx()->idLegajo)]) }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                               title="Descargar factura">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                </svg>
                                                <span class="sr-only">Descargar factura</span>
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if (! $mostrarHistorial)
                            <div class="gf-row gf-row--totales gf-row--totales-inicio gf-cuotas-autogestion-totales"
                                 wire:key="ae-totales-neto">
                                <div class="gf-td gf-col-dni" aria-hidden="true"></div>
                                <div class="gf-td gf-col-curso" aria-hidden="true"></div>
                                <div class="gf-td gf-col-nivel" aria-hidden="true"></div>
                                <div class="gf-td gf-col-ano" aria-hidden="true"></div>
                                <div class="gf-td gf-td-total-label">Total neto</div>
                                <div class="gf-td gf-col-fecha" aria-hidden="true"></div>
                                <div class="gf-td gf-col-fecha" aria-hidden="true"></div>
                                <div class="gf-td gf-col-fecha" aria-hidden="true"></div>
                                <div class="gf-td gf-td-total-importe gf-th-right gf-col-importe">
                                    {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($totalesAdeudados['neto']) }}
                                </div>
                                <div class="gf-td gf-td-accion gf-td-accion-cupon" aria-hidden="true"></div>
                                @if ($muestraComprobanteAfip)
                                    <div class="gf-td gf-td-accion gf-td-accion-afip" aria-hidden="true"></div>
                                @endif
                            </div>
                            <div class="gf-row gf-row--totales gf-cuotas-autogestion-totales"
                                 wire:key="ae-totales-intereses">
                                <div class="gf-td gf-col-dni" aria-hidden="true"></div>
                                <div class="gf-td gf-col-curso" aria-hidden="true"></div>
                                <div class="gf-td gf-col-nivel" aria-hidden="true"></div>
                                <div class="gf-td gf-col-ano" aria-hidden="true"></div>
                                <div class="gf-td gf-td-total-label">Total con intereses al día de hoy</div>
                                <div class="gf-td gf-col-fecha" aria-hidden="true"></div>
                                <div class="gf-td gf-col-fecha" aria-hidden="true"></div>
                                <div class="gf-td gf-col-fecha" aria-hidden="true"></div>
                                <div class="gf-td gf-td-total-importe gf-th-right gf-col-importe">
                                    {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($totalesAdeudados['conIntereses']) }}
                                </div>
                                <div class="gf-td gf-td-accion gf-td-accion-cupon" aria-hidden="true"></div>
                                @if ($muestraComprobanteAfip)
                                    <div class="gf-td gf-td-accion gf-td-accion-afip" aria-hidden="true"></div>
                                @endif
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
                       class="mx-auto block w-full max-w-lg transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 sm:w-1/2">
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
