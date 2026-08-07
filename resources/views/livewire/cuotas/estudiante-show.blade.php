@php
    use App\Livewire\Cuotas\CuotasIndex;
    use App\Support\Alumnos\ArancelesEscolares;
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Cuotas\GestionAranceles;
    use App\Support\Navegacion\ContextoEstudianteSesion;
    use App\Support\Security\OpaqueRouteToken;

    $vistaCuotasNav = $mostrarHistorial
        ? ContextoEstudianteSesion::VISTA_CUOTAS_HISTORIAL
        : ContextoEstudianteSesion::VISTA_CUOTAS_ANIO;
    $facturasAfipPorCuota = $facturasAfipPorCuota ?? [];
    $cuotasConComprobanteAfip = $cuotasConComprobanteAfip ?? [];
    $muestraComprobanteAfip = $muestraComprobanteAfip ?? false;
    $afipEnDevengamiento = $afipEnDevengamiento ?? false;
@endphp

<div class="se-page max-w-[90rem] mx-auto">
    @if (session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="se-eyebrow">Gestión de aranceles</p>
                @if ($encabezado)
                    <h1 class="text-xl font-bold uppercase tracking-tight sm:text-2xl">
                        {{ $encabezado['apellido'] }} {{ $encabezado['nombre'] }}
                        <span class="font-normal text-white/80">— {{ $encabezado['dni'] }}</span>
                    </h1>
                    <p class="mt-1 text-sm text-white/85">
                        {{ $encabezado['curso'] }} · Ciclo {{ $encabezado['terlecAno'] }}
                        @if ($mostrarHistorial)
                            <span class="text-white/70">— historial completo</span>
                        @endif
                    </p>
                    @if ($encabezado['becaResumen'] !== '')
                        <p class="mt-1 text-xs text-white/75">Beca: {{ $encabezado['becaResumen'] }}</p>
                    @endif
                @endif
            </div>
            <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                @if (! $mostrarHistorial)
                    <x-nav-contexto-estudiante
                        destino="cuotas.estudiante.generar"
                        :alcance="ContextoEstudianteSesion::CUOTAS_GESTION"
                        :id-legajos="$idLegajo"
                        :vista-cuotas="$vistaCuotasNav"
                        tag="a"
                        class="inline">
                        <span class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 cursor-pointer">
                            Generar cuota
                        </span>
                    </x-nav-contexto-estudiante>
                @endif
                @if ($cantidadSeleccionadas > 0)
                    <button type="button"
                            wire:click="irImputarSeleccionadas"
                            class="inline-flex items-center rounded-xl border border-white/40 bg-white px-4 py-2 text-sm font-semibold text-primary-800 hover:bg-white/90">
                        Cobrar {{ $cantidadSeleccionadas }} {{ $cantidadSeleccionadas === 1 ? 'cuota' : 'cuotas' }}
                    </button>
                    <button type="button"
                            wire:click="limpiarSeleccion"
                            class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-3 py-2 text-xs font-semibold text-white hover:bg-white/20">
                        Limpiar
                    </button>
                @endif
                <a href="{{ se_route_url('cuotas.resumen-pagos', ['ref' => OpaqueRouteToken::forResumenPagosEstudiante($idLegajo)]) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    Resumen de Pagos
                </a>
                @if (! $mostrarHistorial && ($totalesAdeudados['neto'] ?? 0) > 0)
                    <a href="{{ se_route_url('cuotas.cuotas-adeudadas', ['ref' => OpaqueRouteToken::forCuotasAdeudadasEstudiante($idLegajo)]) }}"
                       target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                        Imprimir adeudadas
                    </a>
                @endif
                <button type="button"
                        wire:click="alternarVistaCuotas"
                        class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    {{ $mostrarHistorial ? 'Cuotas del Año' : 'Historial' }}
                </button>
                <a href="{{ CuotasIndex::urlIndiceConBusquedaGuardada() }}" wire:navigate
                   class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    Volver
                </a>
            </div>
        </div>
    </section>

    <section class="se-card se-card-cuotas-grid p-0 overflow-hidden">
        @if ($cuotas->isEmpty())
            <div class="py-14 text-center text-sm text-neutral-600">
                @if ($mostrarHistorial)
                    No hay cuotas registradas para este estudiante en ningún ciclo lectivo.
                @else
                    No hay cuotas del ciclo {{ $encabezado['terlecAno'] ?? schoolCtx()->terlecAno() }} ni deudas de años anteriores para este estudiante.
                @endif
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-accent-200 bg-accent-50/60 px-4 py-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-600">
                    Seleccioná una o varias cuotas adeudadas para cobrarlas juntas
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                            wire:click="seleccionarTodasAdeudadas"
                            class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-accent-50">
                        Seleccionar adeudadas
                    </button>
                </div>
            </div>
            <div class="w-full overflow-x-auto">
                <div class="gf gf-vcenter gf-cuotas-estudiante">
                        <div class="gf-head">
                            <div class="gf-th gf-th-accion w-8" title="Seleccionar"></div>
                            <div class="gf-th gf-th-accion" title="Editar"></div>
                            <div class="gf-th gf-th-accion" title="Historial de pagos"></div>
                            <div class="gf-th w-12">Año</div>
                            <div class="gf-th w-14 text-center">Nivel</div>
                            <div class="gf-th w-24">Curso</div>
                            <div class="gf-th gf-th-cuota">Cuota</div>
                            <div class="gf-th w-14">Beca</div>
                            <div class="gf-th gf-th-fecha">Venc 1</div>
                            <div class="gf-th gf-th-fecha">Venc 2</div>
                            <div class="gf-th gf-th-fecha">Venc. act.</div>
                            <div class="gf-th gf-th-right w-[5.25rem]">Importe</div>
                            <div class="gf-th gf-th-right w-[4.75rem]">Bonif.</div>
                            <div class="gf-th gf-th-right w-[4.75rem]">Interés</div>
                            <div class="gf-th gf-th-right w-[4.75rem]">Pagado</div>
                            <div class="gf-th gf-th-right w-[4.75rem]">Saldo</div>
                            <div class="gf-th gf-th-accion" title="Imputar pago"></div>
                            <div class="gf-th gf-th-accion gf-th-accion-cupon" title="Cupón de pago">Cupón</div>
                            @if ($muestraComprobanteAfip)
                                <div class="gf-th gf-th-accion gf-th-accion-afip" title="Factura AFIP">AFIP</div>
                            @endif
                        </div>

                        @foreach ($cuotas as $c)
                            @php
                                $pagada = GestionAranceles::filaPagada($c);
                                $rowEstadoClass = $pagada ? 'gf-row--pagada' : 'gf-row--adeudada';
                                $nombreCuota = trim((string) ($c->cuota?->nombre ?? ''));
                                $nombreCurso = trim((string) ($c->curso?->nombreParaListado() ?? ''));
                                $etiquetaBeca = GestionAranceles::etiquetaBeca($c);
                                $nivelTexto = mb_strtoupper(trim((string) ($c->curso?->nivel?->nivel ?? '')));
                                [$nivelLinea1, $nivelLinea2] = CuotasFormato::nivelEnDosLineas($nivelTexto);
                                $facturaAfip = $facturasAfipPorCuota[(int) $c->id] ?? null;
                                $tieneComprobantesAfip = isset($cuotasConComprobanteAfip[(int) $c->id]);
                            @endphp
                            <div class="gf-row gf-row-hover {{ $rowEstadoClass }}" wire:key="cg-{{ $c->id }}-{{ $mostrarHistorial ? 'hist' : 'anio' }}">
                                <div class="gf-td gf-td-accion w-8 !py-1">
                                    @if ((float) $c->faltapa > 0)
                                        <label class="inline-flex h-6 w-6 cursor-pointer items-center justify-center">
                                            <input type="checkbox"
                                                   wire:model.live="cuotasSeleccionadas"
                                                   value="{{ (int) $c->id }}"
                                                   class="h-4 w-4 rounded border-gray-400 text-primary-600 focus:ring-primary-500"
                                                   title="Seleccionar para cobro múltiple">
                                        </label>
                                    @endif
                                </div>
                                <div class="gf-td gf-td-accion !py-1">
                                    <x-nav-contexto-estudiante
                                        destino="cuotas.cuota.editar"
                                        :alcance="ContextoEstudianteSesion::CUOTAS_GESTION"
                                        :id-legajos="$idLegajo"
                                        :id-cuota-generada="$c->id"
                                        :vista-cuotas="$vistaCuotasNav"
                                        class="inline">
                                        <span class="inline-flex h-6 w-6 cursor-pointer items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                              title="Editar cuota">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </span>
                                    </x-nav-contexto-estudiante>
                                </div>
                                <div class="gf-td gf-td-accion !py-1">
                                    <x-nav-contexto-estudiante
                                        destino="cuotas.cuota.historial-pagos"
                                        :alcance="ContextoEstudianteSesion::CUOTAS_GESTION"
                                        :id-legajos="$idLegajo"
                                        :id-cuota-generada="$c->id"
                                        :vista-cuotas="$vistaCuotasNav"
                                        class="inline">
                                        <span class="inline-flex h-6 w-6 cursor-pointer items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                              title="Historial de pagos">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </span>
                                    </x-nav-contexto-estudiante>
                                </div>
                                <div class="gf-td w-12 tabular-nums">{{ $c->terlec?->ano ?? '' }}</div>
                                <div class="gf-td gf-td-nivel w-14" title="{{ $nivelTexto }}">
                                    @if ($nivelLinea1 !== '')
                                        <span class="gf-td-nivel-linea">{{ $nivelLinea1 }}</span>
                                    @endif
                                    @if ($nivelLinea2 !== '')
                                        <span class="gf-td-nivel-linea">{{ $nivelLinea2 }}</span>
                                    @endif
                                </div>
                                <div class="gf-td w-24 uppercase truncate" title="{{ $nombreCurso }}">{{ $nombreCurso }}</div>
                                <div class="gf-td gf-td-cuota font-semibold uppercase text-primary-800" title="{{ $nombreCuota }}">{{ $nombreCuota }}</div>
                                <div class="gf-td w-14 truncate" title="{{ $etiquetaBeca }}">{{ $etiquetaBeca }}</div>
                                <div class="gf-td gf-td-fecha tabular-nums">{{ CuotasFormato::formatearFecha($c->venc1) }}</div>
                                <div class="gf-td gf-td-fecha tabular-nums">{{ CuotasFormato::formatearFecha($c->venc2) }}</div>
                                <div class="gf-td gf-td-fecha tabular-nums">{{ CuotasFormato::formatearFecha($c->nueVenc) }}</div>
                                <div class="gf-td gf-th-right w-[5.25rem] tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($c->importe) }}</div>
                                <div class="gf-td gf-th-right w-[4.75rem] tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($c->bonificacion) }}</div>
                                <div class="gf-td gf-th-right w-[4.75rem] tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($c->interes) }}</div>
                                <div class="gf-td gf-th-right w-[4.75rem] tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($c->pagado) }}</div>
                                <div class="gf-td gf-th-right w-[4.75rem] font-bold tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($c->faltapa) }}</div>
                                <div class="gf-td gf-td-accion !py-1">
                                    @if ((float) $c->faltapa > 0)
                                        <x-nav-contexto-estudiante
                                            destino="cuotas.cuota.imputar"
                                            :alcance="ContextoEstudianteSesion::CUOTAS_GESTION"
                                            :id-legajos="$idLegajo"
                                            :id-cuota-generada="$c->id"
                                            :vista-cuotas="$vistaCuotasNav"
                                            class="inline">
                                            <span class="inline-flex h-6 w-6 cursor-pointer items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                                  title="Imputar pago">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            </span>
                                        </x-nav-contexto-estudiante>
                                    @endif
                                </div>
                                <div class="gf-td gf-td-accion !py-1">
                                    @if ((float) $c->faltapa > 0)
                                        @if (ArancelesEscolares::cuotaVencidaParaReimpresion($c))
                                            <button type="button"
                                                    x-on:click="window.seSwalAviso(@js(ArancelesEscolares::mensajeCuotaVencidaReimpresion()), 'Cuota vencida')"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-400 bg-white text-neutral-400"
                                                    title="Cupón no disponible">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                            </button>
                                        @else
                                            <a href="{{ se_route_url('cuotas.comprobante', ['ref' => OpaqueRouteToken::forComprobantePagoCuotaAdministracion((int) $c->id, $idLegajo)]) }}"
                                               target="_blank" rel="noopener noreferrer"
                                               class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                               title="Emitir cupón de pago">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                            </a>
                                        @endif
                                    @endif
                                </div>
                                @if ($muestraComprobanteAfip)
                                    <div class="gf-td gf-td-accion !py-1">
                                        @if ($afipEnDevengamiento && $tieneComprobantesAfip)
                                            <x-nav-contexto-estudiante
                                                destino="cuotas.cuota.comprobantes-afip-devengamiento"
                                                :alcance="ContextoEstudianteSesion::CUOTAS_GESTION"
                                                :id-legajos="$idLegajo"
                                                :id-cuota-generada="$c->id"
                                                :vista-cuotas="$vistaCuotasNav"
                                                class="inline">
                                                <span class="inline-flex h-6 w-6 cursor-pointer items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                                      title="Comprobantes AFIP">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    <span class="sr-only">Comprobantes AFIP</span>
                                                </span>
                                            </x-nav-contexto-estudiante>
                                        @elseif (! $afipEnDevengamiento && $facturaAfip)
                                            <a href="{{ se_route_url('cuotas.comprobante-afip', ['ref' => OpaqueRouteToken::forComprobanteAfipRegistro((int) $facturaAfip->idComprobanteAfip, $idLegajo)]) }}"
                                               target="_blank" rel="noopener noreferrer"
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

                        <div class="gf-row gf-row--totales gf-row--totales-inicio gf-cuotas-estudiante-totales" wire:key="cg-totales-{{ $mostrarHistorial ? 'hist' : 'anio' }}">
                            <div class="gf-td gf-td-accion w-8" aria-hidden="true"></div>
                            <div class="gf-td gf-td-accion" aria-hidden="true"></div>
                            <div class="gf-td gf-td-accion" aria-hidden="true"></div>
                            <div class="gf-td w-12" aria-hidden="true"></div>
                            <div class="gf-td w-14" aria-hidden="true"></div>
                            <div class="gf-td w-24" aria-hidden="true"></div>
                            <div class="gf-td gf-td-cuota gf-td-total-label justify-end">Total neto</div>
                            <div class="gf-td w-14" aria-hidden="true"></div>
                            <div class="gf-td gf-td-fecha" aria-hidden="true"></div>
                            <div class="gf-td gf-td-fecha" aria-hidden="true"></div>
                            <div class="gf-td gf-td-fecha" aria-hidden="true"></div>
                            <div class="gf-td gf-th-right w-[5.25rem]" aria-hidden="true"></div>
                            <div class="gf-td gf-th-right w-[4.75rem]" aria-hidden="true"></div>
                            <div class="gf-td gf-th-right w-[4.75rem]" aria-hidden="true"></div>
                            <div class="gf-td gf-th-right w-[4.75rem]" aria-hidden="true"></div>
                            <div class="gf-td gf-td-total-importe gf-th-right w-[4.75rem] tabular-nums whitespace-nowrap">
                                {{ CuotasFormato::formatearImporte($totalesAdeudados['neto']) }}
                            </div>
                            <div class="gf-td gf-td-accion" aria-hidden="true"></div>
                            <div class="gf-td gf-td-accion" aria-hidden="true"></div>
                            @if ($muestraComprobanteAfip)
                                <div class="gf-td gf-td-accion" aria-hidden="true"></div>
                            @endif
                        </div>
                        <div class="gf-row gf-row--totales gf-cuotas-estudiante-totales" wire:key="cg-totales-intereses-{{ $mostrarHistorial ? 'hist' : 'anio' }}">
                            <div class="gf-td gf-td-accion w-8" aria-hidden="true"></div>
                            <div class="gf-td gf-td-accion" aria-hidden="true"></div>
                            <div class="gf-td gf-td-accion" aria-hidden="true"></div>
                            <div class="gf-td w-12" aria-hidden="true"></div>
                            <div class="gf-td w-14" aria-hidden="true"></div>
                            <div class="gf-td w-24" aria-hidden="true"></div>
                            <div class="gf-td gf-td-cuota gf-td-total-label justify-end">Total con intereses al día de hoy</div>
                            <div class="gf-td w-14" aria-hidden="true"></div>
                            <div class="gf-td gf-td-fecha" aria-hidden="true"></div>
                            <div class="gf-td gf-td-fecha" aria-hidden="true"></div>
                            <div class="gf-td gf-td-fecha" aria-hidden="true"></div>
                            <div class="gf-td gf-th-right w-[5.25rem]" aria-hidden="true"></div>
                            <div class="gf-td gf-th-right w-[4.75rem]" aria-hidden="true"></div>
                            <div class="gf-td gf-th-right w-[4.75rem]" aria-hidden="true"></div>
                            <div class="gf-td gf-th-right w-[4.75rem]" aria-hidden="true"></div>
                            <div class="gf-td gf-td-total-importe gf-th-right w-[4.75rem] tabular-nums whitespace-nowrap">
                                {{ CuotasFormato::formatearImporte($totalesAdeudados['conIntereses']) }}
                            </div>
                            <div class="gf-td gf-td-accion" aria-hidden="true"></div>
                            <div class="gf-td gf-td-accion" aria-hidden="true"></div>
                            @if ($muestraComprobanteAfip)
                                <div class="gf-td gf-td-accion" aria-hidden="true"></div>
                            @endif
                        </div>
                </div>
            </div>
        @endif
    </section>
</div>

@script
<script>
    (function () {
        @if (session('cuotas_cuota_vencida'))
            if (typeof window.seSwalAviso === 'function') {
                window.seSwalAviso(@js(session('cuotas_cuota_vencida')), 'Cuota vencida');
            }
        @endif

        @if (session('cuotas_siro_config'))
            if (typeof window.seSwalError === 'function') {
                window.seSwalError(@js(session('cuotas_siro_config')), 'SIRO');
            }
        @endif

        @if (session('afip_swal_mensaje'))
            const afipMensaje = @js(session('afip_swal_mensaje'));
            const afipTipo = @js(session('afip_swal_tipo', 'exito'));
            if (afipTipo === 'error' && typeof window.seSwalError === 'function') {
                window.seSwalError(afipMensaje, 'Facturación AFIP');
            } else if (typeof window.seSwalExito === 'function') {
                window.seSwalExito(afipMensaje, 'Facturación AFIP');
            }
        @endif

        $wire.on('se-swal-aviso', (event) => {
            if (typeof window.seSwalAviso === 'function') {
                window.seSwalAviso(event.mensaje ?? '', event.titulo ?? 'Aviso');
            }
        });

        $wire.on('se-swal-error', (event) => {
            if (typeof window.seSwalError === 'function') {
                window.seSwalError(event.mensaje ?? '', event.titulo ?? 'Error');
            }
        });
    })();
</script>
@endscript
