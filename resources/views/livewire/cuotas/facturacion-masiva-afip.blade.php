@php
    use App\Support\Cuotas\CuotasFormato;
@endphp

<div class="se-page max-w-5xl mx-auto">
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
                    <div class="space-y-4 text-[11px] leading-snug text-neutral-700">
                        @foreach ($vistaPrevia['porCurso'] ?? [] as $idCursoPrev => $bloque)
                            <div wire:key="prev-afip-curso-{{ $idCursoPrev }}">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-primary-800">
                                    {{ $bloque['cursoNombre'] ?? '' }}
                                </p>
                                <ul class="list-none space-y-1">
                                    @foreach ($bloque['alumnos'] ?? [] as $alumno)
                                        <li wire:key="prev-afip-{{ $alumno['idLegajo'] }}-{{ $loop->index }}"
                                            class="flex flex-wrap items-baseline gap-x-2">
                                            <span class="text-neutral-800">{{ $alumno['etiqueta'] }}</span>
                                            <span class="{{ ($alumno['puedeFacturar'] ?? false) ? 'text-emerald-800' : 'text-amber-900' }}">
                                                — {{ $alumno['estado'] ?? '' }}
                                                @if (($alumno['puedeFacturar'] ?? false) && (float) ($alumno['importe'] ?? 0) > 0)
                                                    · {{ CuotasFormato::formatearImporte($alumno['importe']) }}
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
            $wire.on('se-swal-error', (event) => {
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensajeDeEvento(event, 'No se pudo completar la operación.'));
                }
            });
        })();
    </script>
    @endscript
</div>
