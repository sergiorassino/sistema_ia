<div class="se-page max-w-7xl mx-auto">
    <section class="overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-sm">
        @if (tenantCuotasSiroHabilitado() && $encabezado && $encabezado['codigoPagoElectronico'] !== '')
            <div class="se-gestion-aranceles-cpe se-hero rounded-none shadow-none" x-data="{ cpeCopiado: false }">
                <div class="se-hero-inner relative z-[1] flex flex-col gap-4 py-5 sm:px-8 sm:py-6 lg:flex-row lg:items-start lg:justify-between lg:gap-8">
                    <div class="min-w-0 flex-1">
                        <p class="se-eyebrow">
                            Código de Pago Electrónico (CPE)
                        </p>
                        <p class="mt-2 text-sm leading-relaxed text-white/90 sm:text-base">
                            Este código identifica al estudiante en el sistema de pagos del Banco Roela (Siro). Copie (al portapapeles) con el ícono junto al número, abra el Botón de Pagos y péguelo allí para acceder a los canales de pago online.
                        </p>
                    </div>
                    <div class="flex w-full min-w-0 shrink-0 items-center justify-between gap-3 rounded-xl border border-white/20 bg-white/10 px-3 py-2.5 sm:w-auto sm:justify-start sm:overflow-x-auto lg:mt-1 lg:rounded-none lg:border-0 lg:bg-transparent lg:px-0 lg:py-0">
                        <p class="min-w-0 truncate text-lg font-bold tabular-nums tracking-wide text-white sm:whitespace-nowrap sm:text-xl lg:text-2xl">
                            {{ $encabezado['codigoPagoElectronico'] }}
                        </p>
                        <button type="button"
                                x-on:click="
                                    navigator.clipboard.writeText(@js($encabezado['codigoPagoElectronico'])).then(function () {
                                        cpeCopiado = true;
                                        setTimeout(function () { cpeCopiado = false; }, 2000);
                                    }).catch(function () {
                                        if (typeof window.seSwalError === 'function') {
                                            window.seSwalError('No se pudo copiar. Seleccione el código manualmente.', 'Copiar CPE');
                                        }
                                    });
                                "
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/30 bg-white/10 text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                :title="cpeCopiado ? '¡Copiado!' : 'Copiar código al portapapeles'"
                                :aria-label="cpeCopiado ? 'Código copiado' : 'Copiar código al portapapeles'">
                            <svg x-show="!cpeCopiado" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="cpeCopiado" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <div class="se-gestion-aranceles-toolbar flex flex-col gap-2 border-b border-accent-100 px-4 py-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-center sm:gap-3 sm:px-6">
            <button type="button"
                    x-on:click="
                        @if ($cuotas->isNotEmpty())
                            window.seSwalAviso('No puede emitir libre deuda mientras existan cuotas pendientes de pago.', 'Libre deuda');
                        @else
                            window.seSwalExito('No registra cuotas pendientes de pago.', 'Libre deuda');
                        @endif
                    "
                    class="se-gestion-aranceles-btn se-gestion-aranceles-btn--secundario w-full sm:w-auto">
                Emitir Libre Deuda
            </button>
            <a href="{{ se_route_url('alumnos.aranceles-escolares.resumen-pagos', ['ref' => \App\Support\Security\OpaqueRouteToken::forResumenPagosAutogestion((int) studentCtx()->idLegajo)]) }}"
               target="_blank"
               rel="noopener noreferrer"
               class="se-gestion-aranceles-btn se-gestion-aranceles-btn--secundario w-full sm:w-auto">
                Resumen de Pagos
            </a>
            @if ($botonPagosUrl !== '')
                <button type="button"
                        x-on:click="
                            window.seSwalConfirmar('', 'Botón de pagos', {
                                html: @js('Está por ingresar al entorno de Pagos de Siro, del Banco Roela.<br>Pegue allí el Código de Pago Electrónico del Estudiante para acceder a los canales de pago.'),
                                icon: 'info',
                                showCancelButton: false,
                                confirmButtonText: 'Aceptar',
                            }).then(function (ok) {
                                if (ok) {
                                    window.open(@js($botonPagosUrl), '_blank', 'noopener,noreferrer');
                                }
                            });
                        "
                        class="se-gestion-aranceles-btn se-gestion-aranceles-btn--pagos w-full sm:w-auto">
                    BOTÓN DE PAGOS
                </button>
            @endif
        </div>

        @if ($cuotas->isEmpty())
            <div class="py-14 text-center">
                <svg class="mx-auto mb-3 h-10 w-10 text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <p class="text-sm font-semibold text-neutral-700">
                    No hay cuotas pendientes de pago
                </p>
            </div>
        @else
            {{-- Mobile: cards (sin scroll horizontal) --}}
            <div class="se-gestion-aranceles-cards space-y-3 p-4 md:hidden">
                @foreach ($cuotas as $c)
                    @php
                        $nombreCuota = trim((string) ($c->cuota?->nombre ?? ''));
                        $nombreCurso = trim((string) ($c->curso?->nombreParaListado() ?? ''));
                        $apellido = trim((string) ($c->legajo->apellido ?? ''));
                        $nombre = trim((string) ($c->legajo->nombre ?? ''));
                    @endphp
                    <article wire:key="ga-cuota-m-{{ $c->id }}"
                             class="se-gestion-aranceles-card rounded-xl border border-accent-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Cuota</p>
                                <p class="mt-0.5 text-sm font-bold uppercase leading-snug text-primary-800">{{ $nombreCuota }}</p>
                            </div>
                            <p class="shrink-0 text-base font-bold tabular-nums text-neutral-900">
                                {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($c->faltapa) }}
                            </p>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs text-neutral-700">
                            <div class="min-w-0">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Estudiante</dt>
                                <dd class="mt-0.5 truncate uppercase">{{ $apellido }}, {{ $nombre }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">DNI</dt>
                                <dd class="mt-0.5 tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearDni($c->legajo->dni ?? '') }}</dd>
                            </div>
                            <div class="min-w-0 col-span-2">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Sala/Grado/Curso</dt>
                                <dd class="mt-0.5 uppercase">{{ $nombreCurso }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Venc 1</dt>
                                <dd class="mt-0.5 tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc1) }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Venc 2</dt>
                                <dd class="mt-0.5 tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc2) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 border-t border-accent-100 pt-3">
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
                                   title="Descargar cupón de pago">
                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                    Descargar cupón
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Desktop: tabla ancha con scroll horizontal si hace falta --}}
            <div class="hidden w-full overflow-x-auto md:block">
                <div class="flex justify-start">
                    <table class="se-gestion-aranceles-tabla min-w-[980px]">
                        <thead>
                            <tr>
                                <th scope="col">Apellido</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Dni</th>
                                <th scope="col">Sala/Grado/Curso</th>
                                <th scope="col">Cuota</th>
                                <th scope="col">Venc 1</th>
                                <th scope="col">Venc 2</th>
                                <th scope="col" class="se-gestion-aranceles-tabla-th-accion">Descargar Cupón</th>
                                <th scope="col" class="se-gestion-aranceles-tabla-th-monto">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cuotas as $c)
                                <tr wire:key="ga-cuota-{{ $c->id }}">
                                    <td class="uppercase">{{ trim((string) ($c->legajo->apellido ?? '')) }}</td>
                                    <td class="uppercase">{{ trim((string) ($c->legajo->nombre ?? '')) }}</td>
                                    <td class="tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearDni($c->legajo->dni ?? '') }}</td>
                                    <td class="uppercase">{{ trim((string) ($c->curso?->nombreParaListado() ?? '')) }}</td>
                                    <td class="font-bold uppercase">{{ trim((string) ($c->cuota?->nombre ?? '')) }}</td>
                                    <td class="tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc1) }}</td>
                                    <td class="tabular-nums">{{ \App\Support\Alumnos\ArancelesEscolares::formatearFecha($c->venc2) }}</td>
                                    <td class="se-gestion-aranceles-tabla-td-accion">
                                        @if (\App\Support\Alumnos\ArancelesEscolares::cuotaVencidaParaReimpresion($c))
                                            <button type="button"
                                                    x-on:click="window.seSwalAviso(@js(\App\Support\Alumnos\ArancelesEscolares::mensajeCuotaVencidaReimpresion()), 'Cuota vencida')"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-400 bg-white text-neutral-400"
                                                    title="Cupón no disponible — cuota vencida">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                </svg>
                                                <span class="sr-only">Cupón no disponible</span>
                                            </button>
                                        @else
                                            <a href="{{ se_route_url('alumnos.aranceles-escolares.comprobante', ['ref' => \App\Support\Security\OpaqueRouteToken::forComprobantePagoCuota((int) $c->id, (int) studentCtx()->idLegajo)]) }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                               title="Descargar cupón de pago">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                </svg>
                                                <span class="sr-only">Descargar cupón de pago</span>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="se-gestion-aranceles-tabla-td-monto tabular-nums">
                                        {{ \App\Support\Alumnos\ArancelesEscolares::formatearImporte($c->faltapa) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
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

        @if (session('aranceles_siro_config'))
            if (typeof window.seSwalError === 'function') {
                window.seSwalError(@js(session('aranceles_siro_config')), 'SIRO');
            }
        @endif
    })();
</script>
@endscript
