<div class="se-page" x-on:cooperadora-abrir-pdf.window="window.open($event.detail.url, '_blank', 'noopener,noreferrer')">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cooperadora</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Ingresos</h2>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('cooperadora.ingresos.nuevo', ['tipo' => 'origen_estudiantes']) }}" class="btn-primary btn-sm">Origen Estudiantes</a>
                <a href="{{ route('cooperadora.ingresos.nuevo', ['tipo' => 'otros_origenes']) }}" class="btn-secondary btn-sm">Otros Orígenes</a>
            </div>
        </div>
    </section>

    <div class="se-toolbar flex-wrap gap-3">
        <div>
            <label class="se-label">Desde</label>
            <input type="date" wire:model.live="fechaDesde" class="se-input">
        </div>
        <div>
            <label class="se-label">Hasta</label>
            <input type="date" wire:model.live="fechaHasta" class="se-input">
        </div>
    </div>

    <div class="w-full min-w-0">
        <div class="gf gf-coop-ingresos-index">
            <div class="gf-head">
                <div class="gf-th gf-th-fecha">Fecha</div>
                <div class="gf-th gf-th-recibo">Recibo</div>
                <div class="gf-th gf-th-estudiante">Estudiante</div>
                <div class="gf-th gf-th-pagador">Pagador / detalle</div>
                <div class="gf-th gf-th-importe">Importe</div>
                <div class="gf-th gf-th-email">Email</div>
                <div class="gf-th gf-th-acciones">Acciones</div>
            </div>
            @forelse ($ingresos as $ingreso)
                @php
                    $ref = \App\Support\Security\OpaqueRouteToken::forCoopRecibo(\App\Support\Cooperadora\ReciboIngresosGrupo::idReferenciaPdf($ingreso));
                    $estadoEmail = $ingreso->recibo_email_estado ?? 'pendiente';
                    $etiquetaEmail = \App\Support\Cooperadora\EnvioReciboCooperadora::etiquetaEstado($estadoEmail);
                    $tieneEmailPagador = trim((string) ($ingreso->pagador_email ?? '')) !== '';
                @endphp
                <div class="gf-row @if($ingreso->anulado) gf-row-anulado @else gf-row-hover @endif" wire:key="ing-{{ $ingreso->id }}">
                    <div class="gf-td gf-td-fecha">{{ $ingreso->fecha->format('d/m/Y') }}</div>
                    <div class="gf-td gf-td-recibo tabular-nums">
                        {{ $ingreso->recibo_numero }}
                        @if ($ingreso->anulado)
                            <span class="mt-0.5 block se-pill bg-red-100 text-red-800 text-[10px]">Anulado</span>
                        @endif
                    </div>
                    <div class="gf-td gf-td-estudiante">
                        @if ($ingreso->tipo === 'origen_estudiantes' && $ingreso->legajo)
                            <span class="font-medium block" title="{{ trim($ingreso->legajo->apellido.', '.$ingreso->legajo->nombre) }}">
                                {{ trim($ingreso->legajo->apellido.', '.$ingreso->legajo->nombre) }}
                            </span>
                        @else
                            <span class="text-neutral-400">—</span>
                        @endif
                    </div>
                    <div class="gf-td gf-td-pagador">
                        <span class="font-medium">{{ $ingreso->pagador_nombre }}</span>
                        <span class="block text-xs text-neutral-500">{{ $ingreso->rubro?->nombre }}@if($ingreso->item) — {{ $ingreso->item->nombre }}@endif</span>
                    </div>
                    <div class="gf-td gf-td-importe tabular-nums">${{ number_format((float) $ingreso->importe, 2, ',', '.') }}</div>
                    <div class="gf-td gf-td-email">
                        @if ($ingreso->tipo === 'origen_estudiantes')
                            @php
                                $emailPagador = mb_strtolower(trim((string) ($ingreso->pagador_email ?? '')));
                            @endphp
                            @if ($emailPagador !== '')
                                <span class="block text-xs text-neutral-800" title="{{ $emailPagador }}">{{ $emailPagador }}</span>
                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                    <span @class([
                                        'se-pill text-[10px]',
                                        'bg-primary-100 text-primary-800' => in_array($estadoEmail, ['simulado', 'enviado'], true),
                                        'bg-amber-100 text-amber-900' => $estadoEmail === 'pendiente',
                                        'bg-red-100 text-red-800' => $estadoEmail === 'error',
                                    ])>{{ $etiquetaEmail }}</span>
                                    @if ($ingreso->recibo_email_enviado_at)
                                        <span class="text-[10px] text-neutral-500">{{ $ingreso->recibo_email_enviado_at->format('d/m/Y H:i') }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="block text-xs text-neutral-500">Sin email del pagador</span>
                                @if ($estadoEmail !== 'pendiente')
                                    <span @class([
                                        'mt-1 inline-block se-pill text-[10px]',
                                        'bg-red-100 text-red-800' => $estadoEmail === 'error',
                                        'bg-amber-100 text-amber-900' => $estadoEmail === 'pendiente',
                                        'bg-primary-100 text-primary-800' => in_array($estadoEmail, ['simulado', 'enviado'], true),
                                    ])>{{ $etiquetaEmail }}</span>
                                @endif
                            @endif
                        @else
                            <span class="text-xs text-neutral-400">—</span>
                        @endif
                    </div>
                    <div class="gf-td gf-td-acciones !py-1.5">
                        <a href="{{ route('cooperadora.recibo.pdf', ['ref' => $ref]) }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn-secondary btn-sm">Recibo</a>
                        @if ($ingreso->tipo === 'origen_estudiantes' && $tieneEmailPagador && ! $ingreso->anulado)
                            <button type="button"
                                    wire:click="reenviarReciboEmail({{ $ingreso->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="reenviarReciboEmail({{ $ingreso->id }})"
                                    class="btn-secondary btn-sm"
                                    title="Reenviar recibo por email al pagador">
                                <span wire:loading.remove wire:target="reenviarReciboEmail({{ $ingreso->id }})">Email</span>
                                <span wire:loading wire:target="reenviarReciboEmail({{ $ingreso->id }})">…</span>
                            </button>
                        @endif
                        @if (! $ingreso->anulado)
                            <button type="button"
                                    class="btn-danger btn-sm"
                                    title="Anular ingreso"
                                    x-on:click="window.seSwalConfirmar(
                                        'El ingreso quedará marcado como anulado y dejará de sumar en movimientos y saldos. ¿Continuar?',
                                        'Anular ingreso',
                                        { confirmButtonText: 'Sí, anular' }
                                    ).then((ok) => { if (ok) $wire.anular({{ $ingreso->id }}); })">
                                Anular
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="gf-empty">No hay ingresos en el período.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">{{ $ingresos->links() }}</div>

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje, titulo }) => window.seSwalExito(mensaje, titulo ?? 'Listo'));
        $wire.on('se-swal-error', ({ mensaje, titulo }) => window.seSwalError(mensaje, titulo ?? 'Error'));
        $wire.on('se-swal-aviso', ({ mensaje, titulo }) => window.seSwalAviso(mensaje, titulo ?? 'Atención'));
    </script>
    @endscript
</div>
