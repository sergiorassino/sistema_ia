<div class="se-page max-w-7xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Medios de pago · SIRO</p>
                <h1 class="flex items-center gap-2.5 text-xl font-bold tracking-tight text-white sm:text-2xl">
                    <svg class="h-6 w-6 shrink-0 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span>Descarga rendición SIRO</span>
                </h1>
                <p class="text-xs text-white/75">
                    Planillas de cobranza · Cada archivo de rendición genera una planilla y sus pagos en rendicionesroela.
                </p>
                @if (\App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionMatchUploadCercano::HABILITADO)
                    <p class="mt-2 max-w-3xl rounded-lg border border-amber-300/40 bg-amber-500/20 px-3 py-2 text-xs leading-relaxed text-amber-50">
                        <span class="font-semibold">Excepción provisorio (puesta en marcha):</span>
                        {{ \App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionMatchUploadCercano::mensajeAvisoFormulario() }}
                    </p>
                @endif
            </div>
            <button type="button"
                    wire:click="abrirModalAlta"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                + Nueva planilla
            </button>
        </div>
    </section>

    <section class="se-card overflow-hidden">
        <div class="se-toolbar border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
            <label class="flex min-w-0 flex-1 items-center gap-2 sm:max-w-md">
                <span class="sr-only">Buscar</span>
                <input type="search"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Nº planilla o nombre de archivo…"
                       class="w-full rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
            </label>
        </div>

        @if ($planillas->isEmpty())
            <div class="py-14 text-center text-sm text-neutral-600">
                No hay planillas de rendición registradas.
            </div>
        @else
            <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                <div class="gf gf-vcenter gf-siro-descarga-planillas">
                    <div class="gf-head">
                        <div class="gf-th gf-th-accion">Acción</div>
                        <div class="gf-th gf-th-nro-planilla">Nro Planilla</div>
                        <div class="gf-th gf-th-fecha-planilla">Fecha de la Planilla</div>
                        <div class="gf-th gf-th-canal-pago">Canal Pago</div>
                        <div class="gf-th gf-th-nombre-archivo">Nombre Archivo</div>
                        <div class="gf-th gf-th-impactado">Impactado</div>
                    </div>
                    @foreach ($planillas as $planilla)
                        @php
                            $canal = collect($canalesPago)->firstWhere('id', (int) ($planilla->canalPago ?? 0));
                        @endphp
                        <div class="gf-row gf-row-hover" wire:key="planilla-{{ $planilla->id }}">
                            <div class="gf-td gf-td-accion">
                                <a href="{{ route('cuotas.siro-descarga.detalle', ['nroPlanilla' => $planilla->nroPlanilla]) }}"
                                   class="text-sm font-semibold text-primary-700 hover:text-primary-800"
                                   wire:navigate>
                                    Abrir
                                </a>
                            </div>
                            <div class="gf-td gf-td-nro-planilla font-semibold tabular-nums">{{ number_format((int) $planilla->nroPlanilla, 0, ',', '.') }}</div>
                            <div class="gf-td gf-td-fecha-planilla tabular-nums">{{ $planilla->fecha?->format('d/m/Y') ?? '—' }}</div>
                            <div class="gf-td gf-td-canal-pago">{{ $canal['label'] ?? $planilla->canalPago }}</div>
                            <div class="gf-td gf-td-nombre-archivo" title="{{ $planilla->nombreArchivo }}">{{ $planilla->nombreArchivo !== '' ? $planilla->nombreArchivo : '—' }}</div>
                            <div class="gf-td gf-td-impactado">
                                @if ((int) ($planilla->impactado ?? 0) === 1)
                                    <span class="se-pill se-pill-ok">Sí</span>
                                @else
                                    <span class="se-pill">No</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="border-t border-accent-200 px-4 py-3 sm:px-5">
                {{ $planillas->links() }}
            </div>
        @endif
    </section>

    @if ($modalAbierto)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="siro-descarga-alta-titulo"
                 wire:key="siro-descarga-modal-alta">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModal" aria-hidden="true"></div>
                <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5"
                     @click.stop>
                    <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                        <h2 id="siro-descarga-alta-titulo" class="text-lg font-bold text-neutral-800">Nueva planilla de rendición</h2>
                    </div>
                    <form wire:submit="guardarPlanilla" class="flex min-h-0 flex-1 flex-col">
                        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            <div>
                                <label class="form-label">
                                    Nro Planilla
                                    @if ($ultimoNro > 0)
                                        <span class="normal-case font-normal text-neutral-500">(el último número fue el {{ number_format($ultimoNro, 0, ',', '.') }})</span>
                                    @endif
                                    <span class="text-red-600">*</span>
                                </label>
                                <input type="number" wire:model="nroPlanilla" min="1"
                                       class="form-input w-full">
                                @error('nroPlanilla') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">Fecha de la Planilla <span class="text-red-600">*</span></label>
                                <input type="date" wire:model="fecha" class="form-input w-full">
                                @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">Canal Pago <span class="text-red-600">*</span></label>
                                @if (count($canalesPagoAlta) === 1)
                                    <p class="form-input bg-accent-50 text-neutral-800">{{ $canalesPagoAlta[0]['label'] }}</p>
                                @else
                                    <select wire:model="canalPago" class="form-input w-full">
                                        <option value="">Seleccione</option>
                                        @foreach ($canalesPagoAlta as $canal)
                                            <option value="{{ $canal['id'] }}">{{ $canal['label'] }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                @error('canalPago') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <p class="text-[11px] text-neutral-500">
                                El nombre del archivo se registrará al procesar la rendición en el detalle de la planilla.
                            </p>
                            <p class="text-[11px] text-red-600">* Campos obligatorios</p>
                        </div>
                        <div class="shrink-0 flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/80 px-5 py-4">
                            <button type="button" wire:click="cerrarModal"
                                    class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-accent-50">
                                Cancelar
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="guardarPlanilla"
                                    class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                                Agregar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
        $wire.on('se-swal-aviso', ({ mensaje }) => window.seSwalAviso(mensaje));
        $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
    </script>
    @endscript
</div>
