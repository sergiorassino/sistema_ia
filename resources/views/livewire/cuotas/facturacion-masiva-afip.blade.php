@php
    use App\Support\Cuotas\CuotasFormato;
@endphp

<div class="se-page w-full !max-w-none">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Gestión masiva · AFIP</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Facturación masiva AFIP</h1>
                <p class="text-xs text-white/75">
                    Ciclo lectivo {{ $ano }} · Devengamiento manual
                    @if ($tipoOperacion !== '')
                        · {{ $esNotaCredito ? 'Nota de crédito' : 'Factura' }}
                    @endif
                </p>
            </div>
            @if ($paso === 2)
                <button type="button"
                        wire:click="volverACuotas"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    ← Volver a las cuotas
                </button>
            @elseif (\App\Support\PermisosArca::puedeDescargarGuiasArca())
                <a href="{{ route('arca.guia-configuracion-facturacion.pdf') }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-white/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Guía ARCA (PDF)
                </a>
            @endif
        </div>
    </section>

    @if ($paso === 1)
        @include('livewire.cuotas.partials.facturacion-masiva-afip-paso-cuotas')
    @elseif ($paso === 2)
        @if ($cursos->isEmpty())
            <div class="se-card mb-4 border-amber-200 bg-amber-50/50 p-4 text-sm text-amber-900">
                No hay cursos cargados para el ciclo lectivo activo. Puede agregar estudiantes individuales.
            </div>
        @endif
        @include('livewire.cuotas.partials.facturacion-masiva-afip-paso-cursos')

        @if (! empty($vistaPrevia))
            @php
                $totalPrevio = (int) ($vistaPrevia['total'] ?? 0);
                $totalAlumnosPrevio = (int) ($vistaPrevia['totalAlumnos'] ?? 0);
                $nombreCuotas = (string) ($vistaPrevia['cuotasNombre'] ?? '');
                $filasPrevio = $vistaPrevia['filas'] ?? [];
                $leyendaEstadoOk = $esNotaCredito ? 'Se anulará con NC' : 'Se facturará';
            @endphp
            <div class="se-card overflow-hidden mt-4">
                <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
                    <p class="text-sm font-semibold text-neutral-800">Vista previa — {{ $nombreCuotas }}</p>
                    <p class="mt-1 text-xs text-neutral-600">
                        <span class="font-semibold tabular-nums">{{ $totalAlumnosPrevio }}</span> estudiante(s)
                        @if ($esNotaCredito)
                            · Se anulará con NC <span class="font-semibold tabular-nums">{{ $totalPrevio }}</span>
                        @else
                            · Se facturará <span class="font-semibold tabular-nums">{{ $totalPrevio }}</span>
                        @endif
                    </p>
                </div>
                <div class="max-h-[min(50dvh,28rem)] overflow-y-auto px-4 py-3 sm:px-5">
                    <div class="gf gf-vcenter gf-facturacion-afip-previa">
                        <div class="gf-head text-[10px]">
                            <div class="gf-th gf-th-apellido">Apellido</div>
                            <div class="gf-th gf-th-nombre">Nombre</div>
                            <div class="gf-th gf-th-dni">DNI</div>
                            <div class="gf-th gf-th-destinatario">Destinatario</div>
                            <div class="gf-th gf-th-dni-dest" title="DNI del destinatario">DNI R.</div>
                            <div class="gf-th gf-th-cuota">Cuota</div>
                            <div class="gf-th gf-th-importe">Importe</div>
                            <div class="gf-th gf-th-estado">Estado</div>
                            @if (! $esNotaCredito)
                                <div class="gf-th gf-th-accion" title="Destinatario de facturación AFIP">Resp.</div>
                            @endif
                        </div>
                                @foreach ($filasPrevio as $fila)
                                    @php
                                        $puedeFila = (bool) ($fila['puedeFacturar'] ?? false);
                                        $idFamiliaFila = (int) ($fila['idFamilia'] ?? 0);
                                    @endphp
                                    <div class="gf-row gf-row-hover text-[11px]"
                                         wire:key="prev-afip-fila-{{ $fila['idLegajo'] ?? 0 }}-{{ $loop->index }}">
                                        <div class="gf-td gf-td-apellido font-medium text-neutral-800">{{ $fila['apellido'] ?? '' }}</div>
                                        <div class="gf-td gf-td-nombre text-neutral-800">{{ $fila['nombre'] ?? '' }}</div>
                                        <div class="gf-td gf-td-dni tabular-nums text-neutral-700">
                                            {{ CuotasFormato::formatearDni($fila['dni'] ?? '') }}
                                        </div>
                                        <div class="gf-td gf-td-destinatario text-neutral-800" title="{{ $fila['destinatario'] ?: '' }}">{{ $fila['destinatario'] ?: '—' }}</div>
                                        <div class="gf-td gf-td-dni-dest tabular-nums text-neutral-700">
                                            @if (filled($fila['dniDestinatario'] ?? ''))
                                                {{ CuotasFormato::formatearDni($fila['dniDestinatario']) }}
                                            @else
                                                —
                                            @endif
                                        </div>
                                        <div class="gf-td gf-td-cuota text-neutral-800">{{ $fila['cuotaNombre'] ?? '' }}</div>
                                        <div class="gf-td gf-td-importe tabular-nums">
                                            @if ((float) ($fila['importe'] ?? 0) > 0)
                                                {{ CuotasFormato::formatearImporte($fila['importe']) }}
                                            @else
                                                —
                                            @endif
                                        </div>
                                        <div class="gf-td gf-td-estado {{ $puedeFila ? 'text-emerald-800' : 'text-amber-900' }}"
                                             title="{{ $puedeFila ? $leyendaEstadoOk : ($fila['estado'] ?? '') }}">
                                            {{ $puedeFila ? $leyendaEstadoOk : ($fila['estado'] ?? '') }}
                                        </div>
                                        @if (! $esNotaCredito)
                                            <div class="gf-td gf-td-accion !py-1">
                                                @if ($idFamiliaFila > 0)
                                                    <button type="button"
                                                            wire:click="abrirModalRespAdmi({{ (int) ($fila['idLegajo'] ?? 0) }})"
                                                            title="Destinatario de facturación AFIP"
                                                            class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-1 py-0.5 text-[9px] font-semibold leading-tight text-primary-700 hover:bg-accent-50">
                                                        Resp.
                                                    </button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
                    <button type="button"
                            wire:loading.attr="disabled"
                            wire:target="facturar"
                            @disabled($totalPrevio < 1)
                            x-data
                            x-on:click="
                                seSwalConfirmar(
                                    @js($esNotaCredito
                                        ? "Se emitirán notas de crédito AFIP para {$totalPrevio} comprobante(s)."
                                        : "Se emitirán comprobantes AFIP para {$totalPrevio} estudiante(s)."),
                                    @js($esNotaCredito ? '¿Confirma la emisión masiva de notas de crédito?' : '¿Confirma la facturación masiva?')
                                ).then(ok => { if (ok) $wire.facturar(); })
                            "
                            class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="facturar">
                            {{ $esNotaCredito ? 'Emitir notas de crédito' : 'Facturar en AFIP' }}
                        </span>
                        <span wire:loading wire:target="facturar">
                            {{ $esNotaCredito ? 'Emitiendo…' : 'Facturando…' }}
                        </span>
                    </button>
                </div>
            </div>
        @endif
    @else
        @php
            $nombreCuotasRes = (string) ($resultado['cuotasNombre'] ?? '');
            $facturadosRes = (int) ($resultado['facturados'] ?? 0);
            $noFacturadosRes = (int) ($resultado['noFacturados'] ?? 0);
            $porCursoRes = $resultado['porCurso'] ?? [];
        @endphp
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <p class="text-sm font-semibold text-neutral-800">Resultado — {{ $nombreCuotasRes }}</p>
                <p class="mt-1 text-xs text-neutral-600">
                    @if ($esNotaCredito)
                        Emitidas: <span class="font-semibold text-emerald-800 tabular-nums">{{ $facturadosRes }}</span>
                    @else
                        Facturados: <span class="font-semibold text-emerald-800 tabular-nums">{{ $facturadosRes }}</span>
                    @endif
                    @if ($noFacturadosRes > 0)
                        · No procesados: <span class="font-semibold text-amber-900 tabular-nums">{{ $noFacturadosRes }}</span>
                    @endif
                </p>
            </div>
            <div class="max-h-[min(55dvh,30rem)] overflow-y-auto px-4 py-3 sm:px-5">
                <div class="space-y-4 text-[11px] leading-snug text-neutral-700">
                    @foreach ($porCursoRes as $idCursoRes => $bloqueRes)
                        <div wire:key="res-afip-curso-{{ $idCursoRes }}">
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-primary-800">
                                {{ $bloqueRes['cursoNombre'] ?? '' }}
                            </p>
                            <ul class="list-none space-y-1">
                                @foreach ($bloqueRes['alumnos'] ?? [] as $filaRes)
                                    <li wire:key="res-afip-{{ $filaRes['idLegajo'] }}-{{ $loop->index }}"
                                        class="flex flex-wrap items-baseline gap-x-2">
                                        <span class="text-neutral-800">{{ $filaRes['etiqueta'] }}</span>
                                        <span class="{{ ($filaRes['exito'] ?? false) ? 'text-emerald-800' : 'text-amber-900' }}">
                                            — {{ $filaRes['estado'] ?? '' }}
                                            @if (! empty($filaRes['nroAfip']))
                                                · {{ $filaRes['nroAfip'] }}
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
                <button type="button"
                        wire:click="nuevaOperacion"
                        class="inline-flex items-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-accent-50">
                    Nueva operación
                </button>
            </div>
        </div>
    @endif

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }
            $wire.on('se-swal-exito', (event) => {
                if (typeof window.seSwalExito === 'function') {
                    window.seSwalExito(mensajeDeEvento(event, 'Operación realizada correctamente.'));
                }
            });
            $wire.on('se-swal-error', (event) => {
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensajeDeEvento(event, 'No se pudo completar la operación.'));
                }
            });
        })();
    </script>
    @endscript

    @if ($modalRespAdmiAbierto)
        @teleport('body')
            @include('livewire.cuotas.partials.facturacion-masiva-afip-modal-resp-admi')
        @endteleport
    @endif
</div>
