@php
    use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
@endphp

<div class="se-page max-w-[90rem] mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Medios de pago · SIRO</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Planilla Nº {{ number_format($planilla->nroPlanilla, 0, ',', '.') }}</h1>
                <dl class="grid gap-1 text-xs text-white/85 sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="inline font-semibold">Fecha de Carga:</dt> {{ $planilla->fecha?->format('d/m/Y') ?? '—' }}</div>
                    <div><dt class="inline font-semibold">Canal de Pago:</dt> {{ $etiquetaCanal }}</div>
                    <div class="sm:col-span-2"><dt class="inline font-semibold">Archivo de Origen:</dt> {{ $planilla->nombreArchivo }}</div>
                </dl>
            </div>
            <a href="{{ route('cuotas.siro-descarga') }}"
               wire:navigate
               class="inline-flex shrink-0 items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                Volver
            </a>
        </div>
    </section>

    <section class="se-card mb-4 overflow-hidden">
        <div class="se-toolbar flex flex-col gap-3 border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-end sm:px-5">
            <div class="min-w-0 flex-1 sm:max-w-md">
                <label class="form-label">Añadir Archivo</label>
                <input type="file"
                       wire:model="archivoRendicion"
                       accept=".txt,text/plain"
                       class="block w-full text-sm text-neutral-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                @error('archivoRendicion') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <button type="button"
                    wire:click="procesarArchivo"
                    wire:loading.attr="disabled"
                    wire:target="procesarArchivo,archivoRendicion"
                    @disabled((int) ($planilla->impactado ?? 0) === 1)
                    class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 hover:border-primary-500 disabled:opacity-50">
                Procesar archivo
            </button>
            <button type="button"
                    x-on:click="window.seSwalConfirmar('¿Impactar todos los pagos en las cuotas de los alumnos?', 'Impactar pagos').then(ok => ok && $wire.impactarTodos())"
                    wire:loading.attr="disabled"
                    wire:target="impactarTodos"
                    @disabled($rendiciones->isEmpty())
                    class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                Impactar los pagos en las cuotas de los alumnos
            </button>
            <button type="button"
                    x-on:click="window.seSwalConfirmar('¿Eliminar todos los pagos descargados de esta planilla?', 'Borrar todos', { icon: 'warning' }).then(ok => ok && $wire.borrarTodos())"
                    wire:loading.attr="disabled"
                    wire:target="borrarTodos"
                    @disabled($rendiciones->isEmpty() || (int) ($planilla->impactado ?? 0) === 1)
                    class="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50">
                Borrar todos
            </button>
        </div>

        @if ($rendiciones->isEmpty())
            <div class="py-14 text-center text-sm text-neutral-600">
                No hay pagos descargados. Cargue el archivo de rendición SIRO.
            </div>
        @else
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div @class([
                        'gf gf-vcenter gf-siro-descarga-rendiciones',
                        'has-obs-data' => $hayObsEnPlanilla,
                    ])>
                        <div class="gf-head">
                            <div class="gf-th gf-th-item">#</div>
                            <div class="gf-th gf-th-fecha-pago" title="Fecha de pago">F. pago</div>
                            <div class="gf-th gf-th-canal-pago" title="Canal de pago">Canal</div>
                            <div class="gf-th gf-th-estudiante" title="Estudiante">Estud.</div>
                            <div class="gf-th gf-th-curso" title="Sala / grado / curso">Curso</div>
                            <div class="gf-th gf-th-cuota" title="Cuota">Cuota</div>
                            <div class="gf-th gf-th-planilla" title="Nº planilla">Plan.</div>
                            <div class="gf-th gf-th-venc1" title="1º vencimiento">1º vto</div>
                            <div class="gf-th gf-th-beca" title="Porcentaje de beca">Beca</div>
                            <div class="gf-th gf-th-importe" title="Importe">Importe</div>
                            <div class="gf-th gf-th-interes" title="Interés">Int.</div>
                            <div class="gf-th gf-th-bonific" title="Bonificación">Bonif.</div>
                            <div class="gf-th gf-th-pagado" title="Pagado">Pagado</div>
                            <div class="gf-th gf-th-nombre-archivo" title="Nombre del archivo">Archivo</div>
                            <div class="gf-th gf-th-impactado" title="Impactado">Impto.</div>
                            @if ($hayObsEnPlanilla)
                                <div class="gf-th gf-th-obs" title="Observaciones">Obs.</div>
                            @endif
                        </div>
                        @foreach ($rendiciones as $i => $r)
                            @php
                                $legajo = $r->legajo;
                                $nombreEst = $legajo
                                    ? mb_strtoupper(trim((string) $legajo->apellido.' '.(string) $legajo->nombre))
                                    : '—';
                                $cursoEtiqueta = $r->curso
                                    ? GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($r->curso)
                                    : '—';
                                $cuotaNombre = trim((string) ($r->cuota?->nombre ?? ''));
                                $becaPct = $r->beca?->porcentaje;
                            @endphp
                            <div class="gf-row gf-row-hover" wire:key="rend-{{ $r->id }}">
                                <div class="gf-td gf-td-item tabular-nums">{{ $i + 1 }}</div>
                                <div class="gf-td gf-td-fecha-pago tabular-nums">{{ $r->fechaPago?->format('d/m/Y') ?? '—' }}</div>
                                <div class="gf-td gf-td-canal-pago">{{ trim((string) ($r->tipoPago?->abrev ?? '')) }}</div>
                                <div class="gf-td gf-td-estudiante" title="{{ $nombreEst }}">{{ $nombreEst }}</div>
                                <div class="gf-td gf-td-curso" title="{{ $cursoEtiqueta }}">{{ $cursoEtiqueta }}</div>
                                <div class="gf-td gf-td-cuota" title="{{ $cuotaNombre }}">{{ $cuotaNombre }}</div>
                                <div class="gf-td gf-td-planilla tabular-nums">{{ number_format((int) $r->nroPlanilla, 0, ',', '.') }}</div>
                                <div class="gf-td gf-td-venc1 tabular-nums">{{ $r->fechVenc1?->format('d/m/Y') ?? '—' }}</div>
                                <div class="gf-td gf-td-beca tabular-nums">{{ $becaPct !== null ? rtrim(rtrim(number_format((float) $becaPct, 2, ',', '.'), '0'), ',') : '' }}</div>
                                <div class="gf-td gf-td-importe tabular-nums">{{ $fmtImporte((float) $r->importe) }}</div>
                                <div class="gf-td gf-td-interes tabular-nums">{{ $fmtImporte((float) $r->interes) }}</div>
                                <div class="gf-td gf-td-bonific tabular-nums">{{ $fmtImporte((float) $r->bonificacion) }}</div>
                                <div class="gf-td gf-td-pagado tabular-nums font-semibold">{{ $fmtImporte((float) $r->pagado) }}</div>
                                <div class="gf-td gf-td-nombre-archivo" title="{{ $r->nombreArchivo }}">{{ $r->nombreArchivo }}</div>
                                <div class="gf-td gf-td-impactado">
                                    @if ((int) ($r->impactado ?? 0) === 1)
                                        <span class="font-semibold text-primary-700">Sí</span>
                                    @else
                                        No
                                    @endif
                                </div>
                                @if ($hayObsEnPlanilla)
                                    @php $obsTexto = trim((string) ($r->obs ?? '')); @endphp
                                    <div @class([
                                        'gf-td gf-td-obs',
                                        'text-amber-800' => $obsTexto !== '',
                                    ]) title="{{ $obsTexto }}">{{ $obsTexto }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="border-t border-accent-200 bg-accent-50/50 px-4 py-3 text-right text-sm font-semibold text-neutral-800 sm:px-5">
                Total Cobrado: {{ $fmtImporte($totalCobrado) }}
            </div>
        @endif
    </section>

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
        $wire.on('se-swal-aviso', ({ mensaje }) => window.seSwalAviso(mensaje));
        $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
    </script>
    @endscript
</div>
