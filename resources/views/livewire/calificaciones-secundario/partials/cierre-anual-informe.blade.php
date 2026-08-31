{{-- Informe de un lote de cierre (cards). Variables: $informe, $mostrarVerFilas, $mostrarCerrar --}}
@php
    $informe = $informe ?? [];
    $mostrarVerFilas = $mostrarVerFilas ?? false;
    $mostrarCerrar = $mostrarCerrar ?? false;
@endphp
<div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/80 px-4 py-4" role="status" wire:key="informe-cierre-{{ $informe['operacion'] ?? 'x' }}-{{ $informe['lote_id'] ?? 0 }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 space-y-1">
            <p class="text-sm font-semibold text-emerald-950">{{ $informe['titulo'] ?? 'Informe de cierre' }}</p>
            <p class="text-xs text-emerald-900/80">
                {{ $informe['nivel'] ?? '' }} · Ciclo lectivo {{ $informe['ano_lectivo'] ?? '—' }}
                @if (! empty($informe['created_at']))
                    · {{ \App\Support\CalificacionesSecundario\CierreAnualJournal::formatearFecha((string) $informe['created_at']) }}
                @endif
                @if (! empty($informe['nombre_profesor']))
                    · {{ $informe['nombre_profesor'] }}
                @endif
                @if (! empty($informe['lote_id']))
                    · Lote {{ (int) $informe['lote_id'] }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($mostrarVerFilas && (int) ($informe['lote_id'] ?? 0) > 0)
                <button type="button"
                        wire:click="verFilasLote"
                        class="btn-secondary btn-sm shrink-0 border-emerald-200 text-emerald-900 hover:bg-white">
                    Ver filas de este cierre
                </button>
            @endif
            @if ($mostrarCerrar)
                <button type="button"
                        wire:click="cerrarInformeCierre"
                        class="btn-secondary btn-sm shrink-0 border-emerald-200 text-emerald-900 hover:bg-white">
                    Cerrar informe
                </button>
            @endif
        </div>
    </div>
    <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-emerald-200/80 bg-white px-3 py-2.5">
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Registros procesados</dt>
            <dd class="mt-0.5 text-xl font-bold tabular-nums text-neutral-900">{{ $informe['procesados'] ?? 0 }}</dd>
        </div>
        <div class="rounded-xl border border-emerald-200/80 bg-white px-3 py-2.5">
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Registros actualizados</dt>
            <dd class="mt-0.5 text-xl font-bold tabular-nums text-emerald-800">{{ $informe['actualizados'] ?? 0 }}</dd>
        </div>
        <div class="rounded-xl border border-emerald-200/80 bg-white px-3 py-2.5">
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Pasados al matriz</dt>
            <dd class="mt-0.5 text-xl font-bold tabular-nums text-neutral-900">{{ $informe['aprobados'] ?? 0 }}</dd>
        </div>
        @if (($informe['operacion'] ?? '') === 'feb')
            <div class="rounded-xl border border-emerald-200/80 bg-white px-3 py-2.5">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Nuevas previas en esta ejecución</dt>
                <dd class="mt-0.5 text-xl font-bold tabular-nums text-amber-800">{{ $informe['previas'] ?? 0 }}</dd>
            </div>
        @endif
        <div class="rounded-xl border border-emerald-200/80 bg-white px-3 py-2.5 {{ ($informe['operacion'] ?? '') === 'feb' ? 'sm:col-span-2 lg:col-span-1' : '' }}">
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Sin cambio</dt>
            <dd class="mt-0.5 text-xl font-bold tabular-nums text-neutral-600">{{ $informe['omitidos'] ?? 0 }}</dd>
            <p class="mt-1 text-[10px] leading-snug text-neutral-500">
                @if (($informe['operacion'] ?? '') === 'feb')
                    Sin cambio: ya al matriz, ya previa sin nuevas notas aprobatorias, o sin filas modificadas
                @else
                    No aprobados (Dic) o ya cerrados al matriz
                @endif
            </p>
        </div>
    </dl>
</div>
