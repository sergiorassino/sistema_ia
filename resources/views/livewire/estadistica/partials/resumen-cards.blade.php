@props(['resumen', 'anoLabel' => ''])

@if ($resumen !== null)
    <h3 class="text-sm font-semibold text-neutral-800 mb-3">
        Resumen @if ($anoLabel) — {{ $anoLabel }} @endif
    </h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
        <div class="se-card p-4 text-center">
            <div class="text-2xl font-bold text-neutral-800 tabular-nums">{{ (int) $resumen['total'] }}</div>
            <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mt-1">Total registros</div>
        </div>
        <div class="se-card p-4 text-center">
            <div class="text-2xl font-bold text-emerald-700 tabular-nums">{{ (int) $resumen['aprobados_durante_anio'] }}</div>
            <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mt-1">Aprobados durante el año</div>
        </div>
        <div class="se-card p-4 text-center">
            <div class="text-2xl font-bold text-blue-700 tabular-nums">{{ (int) $resumen['aprobados_diciembre'] }}</div>
            <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mt-1">Aprobados en Diciembre</div>
        </div>
        <div class="se-card p-4 text-center">
            <div class="text-2xl font-bold text-amber-700 tabular-nums">{{ (int) $resumen['aprobados_febrero'] }}</div>
            <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mt-1">Aprobados en Febrero</div>
        </div>
        <div class="se-card p-4 text-center col-span-2 sm:col-span-1">
            <div class="text-2xl font-bold text-neutral-600 tabular-nums">{{ (int) ($resumen['pendientes'] ?? 0) }}</div>
            <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mt-1">Pendientes</div>
        </div>
    </div>
@endif
