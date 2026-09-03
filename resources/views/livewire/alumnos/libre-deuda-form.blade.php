<div>
    <div class="se-page max-w-3xl mx-auto">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Aranceles</p>
                    <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Libre Deuda</h2>
                </div>
            </div>
        </section>

        @if ($datos === null)
            <section class="se-card p-5">
                <p class="text-sm text-neutral-700">
                    No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.
                </p>
            </section>
        @else
            <section class="se-card mb-4 border-2 border-primary-300 bg-gradient-to-b from-primary-50 to-white p-4 shadow-sm sm:p-5"
                     aria-label="Datos del estudiante">
                <p class="mb-4 text-center text-xs font-bold uppercase tracking-wider text-primary-800">
                    Datos del estudiante
                </p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="form-label text-primary-900">Apellido</label>
                        <input type="text"
                               value="{{ $datos['apellido'] }}"
                               readonly
                               tabindex="-1"
                               class="form-input mt-1 border-primary-200 bg-white font-semibold text-neutral-900 shadow-inner"
                               aria-readonly="true">
                    </div>
                    <div>
                        <label class="form-label text-primary-900">Nombre</label>
                        <input type="text"
                               value="{{ $datos['nombre'] }}"
                               readonly
                               tabindex="-1"
                               class="form-input mt-1 border-primary-200 bg-white font-semibold text-neutral-900 shadow-inner"
                               aria-readonly="true">
                    </div>
                    <div>
                        <label class="form-label text-primary-900">DNI</label>
                        <input type="text"
                               value="{{ $datos['dni'] }}"
                               readonly
                               tabindex="-1"
                               class="form-input mt-1 border-primary-200 bg-white font-semibold text-neutral-900 shadow-inner"
                               aria-readonly="true">
                    </div>
                </div>
                @if (($datos['cursec'] ?? '') !== '' || ($datos['nivel'] ?? '') !== '')
                    <p class="mt-4 text-center text-sm text-neutral-700">
                        @if (($datos['cursec'] ?? '') !== '')
                            <span class="font-semibold">{{ $datos['cursec'] }}</span>
                        @endif
                        @if (($datos['nivel'] ?? '') !== '')
                            <span class="text-neutral-500">({{ $datos['nivel'] }})</span>
                        @endif
                    </p>
                @endif
            </section>

            <section class="se-card p-5 sm:p-6">
                <p class="text-sm leading-relaxed text-neutral-600">
                    Antes de emitir la constancia se consulta la cuenta corriente en Áulica (estudiante y grupo familiar).
                </p>
                <div class="mt-5 flex justify-center">
                    <button type="button"
                            wire:click="consultarYMostrar"
                            wire:loading.attr="disabled"
                            wire:target="consultarYMostrar"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50">
                        <svg wire:loading.remove wire:target="consultarYMostrar" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span wire:loading.remove wire:target="consultarYMostrar">Consultar deuda</span>
                        <span wire:loading wire:target="consultarYMostrar">Consultando Áulica…</span>
                    </button>
                </div>
            </section>
        @endif
    </div>

    @if ($modalAbierto && $detalle !== [])
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="libre-deuda-modal-titulo"
                 wire:key="libre-deuda-consulta-modal"
                 x-data
                 x-on:keydown.escape.window="$wire.cerrarModal()">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                     wire:click="cerrarModal"
                     aria-hidden="true"></div>
                <div class="relative z-10 my-auto flex w-full max-w-2xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),42rem)]"
                     @click.stop>
                    <div class="shrink-0 border-b border-accent-200 px-5 py-4 sm:px-6">
                        <h3 id="libre-deuda-modal-titulo" class="text-lg font-bold text-neutral-900">
                            Consulta Áulica
                        </h3>
                        <p class="mt-1 text-xs text-neutral-500">
                            {{ strtoupper((string) ($detalle['metodo'] ?? 'POST')) }}
                            · {{ $detalle['endpoint'] ?? '' }}
                            · {{ ($detalle['ambiente'] ?? '') === 'produccion' ? 'Producción' : 'Test' }}
                        </p>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-6">
                        <p class="mb-3 text-[10px] font-semibold uppercase tracking-wider text-neutral-500">
                            Enviado a la API
                        </p>
                        <div class="mb-5 overflow-x-auto rounded-xl border border-accent-200">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-accent-50 text-[10px] font-semibold uppercase tracking-wider text-neutral-500">
                                    <tr>
                                        <th class="px-3 py-2">Rol</th>
                                        <th class="px-3 py-2">TipoDoc</th>
                                        <th class="px-3 py-2">NroDoc</th>
                                        <th class="px-3 py-2">Origen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse (($detalle['consultas'] ?? []) as $consulta)
                                        <tr class="border-t border-accent-100">
                                            <td class="px-3 py-2 text-neutral-800">{{ $consulta['rol'] ?? '' }}</td>
                                            <td class="px-3 py-2 font-semibold tabular-nums text-neutral-900">{{ $consulta['tipo_doc'] ?? 'DNI' }}</td>
                                            <td class="px-3 py-2 font-semibold tabular-nums text-neutral-900">{{ $consulta['nro_doc'] ?? '' }}</td>
                                            <td class="px-3 py-2 text-neutral-600">{{ $consulta['origen'] ?? '' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-3 py-3 text-sm text-neutral-500">Sin DNI para consultar.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <p class="mb-3 text-[10px] font-semibold uppercase tracking-wider text-neutral-500">
                            Deuda recibida
                        </p>

                        <p class="mb-3 whitespace-pre-line text-sm leading-relaxed text-neutral-700">{{ $detalle['mensaje'] ?? '' }}</p>

                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Estudiante</p>
                        <div class="mb-4 overflow-x-auto rounded-xl border border-accent-200">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-accent-50 text-[10px] font-semibold uppercase tracking-wider text-neutral-500">
                                    <tr>
                                        <th class="px-3 py-2">Persona</th>
                                        <th class="px-3 py-2">DNI</th>
                                        <th class="px-3 py-2 text-right">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse (($detalle['estudiante'] ?? []) as $persona)
                                        <tr class="border-t border-accent-100">
                                            <td class="px-3 py-2 text-neutral-800">{{ ($persona['nombre_completo'] ?? '') !== '' ? $persona['nombre_completo'] : '—' }}</td>
                                            <td class="px-3 py-2 tabular-nums text-neutral-700">{{ $persona['nro_doc'] ?? '' }}</td>
                                            <td class="px-3 py-2 text-right font-semibold tabular-nums text-neutral-900">{{ $persona['saldo_texto'] ?? '' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-3 py-3 text-sm text-neutral-500">
                                                Sin personas (Áulica 404 o saldo vacío). Total {{ $detalle['saldo_estudiante_texto'] ?? '$ 0,00' }}.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Grupo familiar</p>
                        <div class="overflow-x-auto rounded-xl border border-accent-200">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-accent-50 text-[10px] font-semibold uppercase tracking-wider text-neutral-500">
                                    <tr>
                                        <th class="px-3 py-2">Persona</th>
                                        <th class="px-3 py-2">DNI</th>
                                        <th class="px-3 py-2 text-right">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse (($detalle['grupo_familiar'] ?? []) as $persona)
                                        <tr class="border-t border-accent-100">
                                            <td class="px-3 py-2 text-neutral-800">{{ ($persona['nombre_completo'] ?? '') !== '' ? $persona['nombre_completo'] : '—' }}</td>
                                            <td class="px-3 py-2 tabular-nums text-neutral-700">{{ $persona['nro_doc'] ?? '' }}</td>
                                            <td class="px-3 py-2 text-right font-semibold tabular-nums text-neutral-900">{{ $persona['saldo_texto'] ?? '' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-3 py-3 text-sm text-neutral-500">
                                                Sin personas en el grupo familiar. Total {{ $detalle['saldo_grupo_texto'] ?? '$ 0,00' }}.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col-reverse gap-2 border-t border-accent-200 bg-accent-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                        <button type="button"
                                wire:click="cerrarModal"
                                class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                            Cerrar
                        </button>
                        @if (! empty($detalle['puede_emitir']))
                            <a href="{{ $urlPdf }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                                Abrir constancia PDF
                            </a>
                        @elseif ($urlAranceles !== '' && ! empty($detalle['tiene_deuda']))
                            <a href="{{ $urlAranceles }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                                Ir a aranceles
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    @script
    <script>
        $wire.on('se-swal-error', (event) => {
            const mensaje = event?.mensaje ?? event?.detail?.mensaje ?? 'No se pudo consultar.';
            if (typeof window.seSwalError === 'function') {
                window.seSwalError(mensaje, 'Libre Deuda');
            }
        });
    </script>
    @endscript
</div>
