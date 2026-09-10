{{-- Copiar cursos, materias y horarios de un año lectivo a otro. --}}
<div class="se-page mx-auto w-full max-w-5xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Configuración</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Copiar cursos, materias y horarios</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Replica la estructura del año de origen en el año de destino. Los registros que ya existen no se duplican.
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    @error('copia')
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    <div class="se-card space-y-6 p-5 sm:p-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="tl-origen" class="form-label">Año de origen</label>
                <select id="tl-origen" wire:model.live="idTerlecOrigen" class="form-select">
                    <option value="">— Seleccione año —</option>
                    @foreach ($terlecs as $t)
                        <option value="{{ (int) $t->id }}">{{ $t->ano }}</option>
                    @endforeach
                </select>
                @error('idTerlecOrigen')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="tl-destino" class="form-label">Año de destino</label>
                <select id="tl-destino" wire:model.live="idTerlecDestino" class="form-select">
                    <option value="">— Seleccione año —</option>
                    @foreach ($terlecs as $t)
                        <option value="{{ (int) $t->id }}">{{ $t->ano }}</option>
                    @endforeach
                </select>
                @error('idTerlecDestino')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p class="form-label mb-0">Niveles a procesar</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="seleccionarTodosNiveles"
                            class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-accent-50">
                        Marcar todos
                    </button>
                    <button type="button" wire:click="quitarTodosNiveles"
                            class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-600 shadow-sm transition hover:border-accent-300 hover:bg-accent-50">
                        Desmarcar todos
                    </button>
                </div>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($niveles as $nivel)
                    <label class="flex items-center gap-3 rounded-xl border border-accent-200 bg-accent-50/40 px-3 py-2.5 text-sm text-neutral-800">
                        <input type="checkbox"
                               value="{{ (int) $nivel->id }}"
                               wire:model.live="idNiveles"
                               class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                        <span>{{ $nivel->nivel }}@if (trim((string) $nivel->abrev) !== '') <span class="text-neutral-500">({{ $nivel->abrev }})</span>@endif</span>
                    </label>
                @endforeach
            </div>
            @error('idNiveles')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('idNiveles.*')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        @if ($preview)
            <div class="space-y-3">
                <p class="text-sm font-semibold text-neutral-800">Previsualización</p>
                <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ([
                        'Cursos' => $preview['cursos'],
                        'Materias' => $preview['materias'],
                        'Horarios (grilla)' => $preview['horarios26'],
                        'Horarios (legado)' => $preview['horarios'],
                    ] as $titulo => $bloque)
                        <div class="rounded-xl border border-accent-200 bg-accent-50/60 px-3 py-2">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">{{ $titulo }}</dt>
                            <dd class="mt-1 text-sm text-neutral-800">
                                <span class="font-semibold tabular-nums text-primary-700">{{ $bloque['a_crear'] }}</span>
                                <span class="text-xs text-neutral-500"> a crear</span>
                                <span class="mt-0.5 block text-xs text-neutral-500">
                                    {{ $bloque['origen'] }} origen · {{ $bloque['existentes'] }} ya existen
                                </span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        <div>
            <button type="button"
                    class="btn-primary"
                    wire:loading.attr="disabled"
                    wire:target="ejecutar"
                    @disabled(! $preview)
                    x-on:click="window.seSwalConfirmar(
                        'Se copiarán cursos, materias y horarios del año {{ $origenAno ?? 'origen' }} al año {{ $destinoAno ?? 'destino' }}. Los que ya existan no se duplican.',
                        'Copiar cursos y materias',
                        { confirmButtonText: 'Sí, copiar', icon: 'warning' }
                    ).then(ok => ok && $wire.ejecutar())">
                <span wire:loading.remove wire:target="ejecutar">Copiar al año de destino</span>
                <span wire:loading wire:target="ejecutar">Procesando…</span>
            </button>
        </div>
    </div>

    @if (! empty($informe))
        <div class="se-card space-y-3 p-5 sm:p-6" role="status">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <p class="text-sm font-semibold text-neutral-800">Informe de la copia</p>
                    <p class="text-xs text-neutral-500">
                        {{ $origenAno ?? '—' }} → {{ $destinoAno ?? '—' }}
                    </p>
                </div>
                <button type="button" wire:click="cerrarInforme" class="btn-secondary text-xs">Cerrar</button>
            </div>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([
                    'Cursos' => $informe['cursos'],
                    'Materias' => $informe['materias'],
                    'Horarios (grilla)' => $informe['horarios26'],
                    'Horarios (legado)' => $informe['horarios'],
                ] as $titulo => $bloque)
                    <div class="rounded-xl border border-accent-200 bg-accent-50/60 px-3 py-2">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">{{ $titulo }}</dt>
                        <dd class="text-lg font-semibold tabular-nums text-primary-700">{{ $bloque['a_crear'] }}</dd>
                        <dd class="text-xs text-neutral-500">creados · {{ $bloque['existentes'] }} ya existían</dd>
                    </div>
                @endforeach
            </dl>

            @if (! empty($informe['por_nivel']))
                <div class="space-y-2 pt-1">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Creados por nivel</p>
                    <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                        <table class="w-max table-auto text-sm">
                            <thead>
                                <tr class="border-b border-accent-200 bg-accent-50">
                                    <th scope="col" class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Nivel</th>
                                    <th scope="col" class="px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Cursos</th>
                                    <th scope="col" class="px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Materias</th>
                                    <th scope="col" class="px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Horarios</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($informe['por_nivel'] as $filaNivel)
                                    <tr class="border-b border-accent-100">
                                        <td class="px-3 py-2 font-medium text-neutral-800">{{ $filaNivel['nombre'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-neutral-800">{{ (int) $filaNivel['cursos'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-neutral-800">{{ (int) $filaNivel['materias'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-neutral-800">{{ (int) $filaNivel['horarios'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-accent-200">
                                    <th scope="row" class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Total</th>
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-primary-700">{{ (int) $informe['cursos']['a_crear'] }}</td>
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-primary-700">{{ (int) $informe['materias']['a_crear'] }}</td>
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-primary-700">{{ (int) $informe['horarios26']['a_crear'] }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            $wire.on('se-swal-exito', (event) => {
                const mensaje = mensajeDeEvento(event, 'Copia completada.');
                if (typeof window.seSwalExito === 'function') {
                    window.seSwalExito(mensaje);
                }
            });

            $wire.on('se-swal-error', (event) => {
                const mensaje = mensajeDeEvento(event, 'No se pudo completar la copia.');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                }
            });

            $wire.on('se-swal-aviso', (event) => {
                const mensaje = mensajeDeEvento(event, 'No había registros nuevos para copiar.');
                if (typeof window.seSwalAviso === 'function') {
                    window.seSwalAviso(mensaje);
                }
            });
        })();
    </script>
    @endscript
</div>
