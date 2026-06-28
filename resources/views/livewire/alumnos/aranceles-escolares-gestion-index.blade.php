<div class="se-page max-w-7xl mx-auto">
    <section class="se-gestion-aranceles overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-sm">
        @if (tenantCuotasSiroHabilitado() && $encabezado && $encabezado['codigoPagoElectronico'] !== '')
            <div class="se-gestion-aranceles-cpe px-4 py-5 sm:px-8 sm:py-6" x-data="{ cpeCopiado: false }">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/75">
                    Código de Pago Electrónico (CPE)
                </p>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-white/90 sm:text-base">
                    Este código identifica al estudiante en el sistema de pagos del Banco Roela (Siro). Copie (al portapapeles) con el ícono junto al número, abra el Botón de Pagos y péguelo allí para acceder a los canales de pago online.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <p class="break-all text-xl font-bold tabular-nums tracking-wide text-white sm:text-2xl">
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
        @endif

        <div class="se-gestion-aranceles-toolbar flex flex-wrap items-center justify-center gap-3 border-b border-accent-100 px-4 py-4 sm:gap-4 sm:px-6">
            <button type="button"
                    x-on:click="
                        @if ($cuotas->isNotEmpty())
                            window.seSwalAviso('No puede emitir libre deuda mientras existan cuotas pendientes de pago.', 'Libre deuda');
                        @else
                            window.seSwalExito('No registra cuotas pendientes de pago.', 'Libre deuda');
                        @endif
                    "
                    class="se-gestion-aranceles-btn se-gestion-aranceles-btn--secundario">
                Emitir Libre Deuda
            </button>
            <a href="{{ se_route_url('alumnos.aranceles-escolares.resumen-pagos', ['ref' => \App\Support\Security\OpaqueRouteToken::forResumenPagosAutogestion((int) studentCtx()->idLegajo)]) }}"
               target="_blank"
               rel="noopener noreferrer"
               class="se-gestion-aranceles-btn se-gestion-aranceles-btn--secundario">
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
                        class="se-gestion-aranceles-btn se-gestion-aranceles-btn--pagos">
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
            <div class="w-full overflow-x-auto">
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
    })();
</script>
@endscript
