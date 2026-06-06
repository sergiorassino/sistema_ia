<div class="se-page max-w-7xl mx-auto">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Portal familia</p>
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Aranceles Escolares</h2>
                <p class="text-sm text-white/85">Cuotas pendientes de pago</p>
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
        </div>
    </section>

    <section class="se-card p-0 overflow-hidden">
        @php
            $debitoAutomatico = tenantArancelesEscolaresDebitoAutomatico();
            $mediosPago = tenantArancelesEscolaresMediosPago();
        @endphp
        @if ($debitoAutomatico || ($encabezado && $cuotas->isNotEmpty()))
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-accent-100 px-4 py-3 sm:px-6">
                @if ($encabezado && $cuotas->isNotEmpty())
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
                <p class="text-sm font-semibold text-neutral-700">No hay cuotas pendientes de pago</p>
            </div>
        @else
            <div class="w-full overflow-x-auto">
                <div class="flex justify-center">
                    <div class="gf gf-vcenter min-w-[1020px]">
                        <div class="gf-head">
                            <div class="gf-th w-[180px]">Apellido y nombre</div>
                            <div class="gf-th w-[95px]">Dni</div>
                            <div class="gf-th w-[115px]">Sala/Grado/Curso</div>
                            <div class="gf-th w-[100px]">Nivel</div>
                            <div class="gf-th w-[120px]">Cuota</div>
                            <div class="gf-th w-[85px]">Venc 1</div>
                            <div class="gf-th w-[85px]">Venc 2</div>
                            <div class="gf-th w-[105px]">Actualizada al:</div>
                            <div class="gf-th gf-th-right w-[95px]">Saldo</div>
                            <div class="gf-th gf-th-right w-[130px]"></div>
                        </div>

                        @foreach ($cuotas as $c)
                            <div class="gf-row gf-row-hover" wire:key="cuota-{{ $c->id }}">
                                <div class="gf-td w-[180px] uppercase">{{ trim(trim((string) ($c->legajo->apellido ?? '')).', '.trim((string) ($c->legajo->nombre ?? ''))) }}</div>
                                <div class="gf-td w-[95px] tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearDni($c->legajo->dni ?? '') }}</div>
                                <div class="gf-td w-[115px] uppercase">{{ trim((string) ($c->curso?->nombreParaListado() ?? '')) }}</div>
                                <div class="gf-td w-[100px] uppercase">{{ trim((string) ($c->curso?->nivel?->nivel ?? '')) }}</div>
                                <div class="gf-td w-[120px] font-bold uppercase text-primary-800">{{ trim((string) ($c->cuota?->nombre ?? '')) }}</div>
                                <div class="gf-td w-[85px] tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc1) }}</div>
                                <div class="gf-td w-[85px] tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc2) }}</div>
                                <div class="gf-td w-[105px] tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->nueVenc) }}</div>
                                <div class="gf-td gf-th-right w-[95px] font-bold tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($c->faltapa) }}</div>
                                <div class="gf-td gf-td-actions w-[130px]">
                                    @if (\App\Support\Alumnos\ArancelesEscolares::cuotaVencidaParaReimpresion($c))
                                        <button type="button"
                                                x-on:click="window.seSwalAviso(@js(\App\Support\Alumnos\ArancelesEscolares::mensajeCuotaVencidaReimpresion()), 'Cuota vencida')"
                                                class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-500 hover:bg-accent-50"
                                                title="Cuota vencida — no se puede reimprimir">
                                            Reimprimir
                                        </button>
                                    @else
                                        <a href="{{ se_route_url('alumnos.aranceles-escolares.comprobante', ['ref' => \App\Support\Security\OpaqueRouteToken::forComprobantePagoCuota((int) $c->id, (int) studentCtx()->idLegajo)]) }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-500 hover:bg-accent-50"
                                           title="Reimprimir comprobante de pago">
                                            Reimprimir
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($mediosPago)
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
