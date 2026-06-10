@props(['resumenPromocion', 'anoLabel' => '', 'pctPromocion' => [0, 0, 0, 0]])

@if ($resumenPromocion !== null)
    <h3 class="text-sm font-semibold text-neutral-800 mb-1">
        Promoción anual por estudiante @if ($anoLabel) — {{ $anoLabel }} @endif
    </h3>
    <p class="text-xs text-neutral-500 mb-3 leading-relaxed">
        Cantidad de alumnos según el resultado final de todas sus materias.
        Un alumno que debió rendir en Febrero (aunque también haya rendido en Diciembre) se cuenta en «con examen en Febrero».
    </p>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
        <div class="se-card p-4 text-center">
            <div class="text-2xl font-bold text-neutral-800 tabular-nums">{{ (int) $resumenPromocion['total_estudiantes'] }}</div>
            <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mt-1">Total estudiantes</div>
        </div>
        <div class="se-card p-4 text-center">
            <div class="text-2xl font-bold text-emerald-700 tabular-nums">{{ (int) $resumenPromocion['promovidos_anio'] }}</div>
            <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mt-1">Todo durante el año</div>
            @if ((int) $resumenPromocion['total_estudiantes'] > 0)
                <div class="text-[11px] text-neutral-400 mt-1 tabular-nums">{{ $pctPromocion[0] }}%</div>
            @endif
        </div>
        <div class="se-card p-4 text-center">
            <div class="text-2xl font-bold text-blue-700 tabular-nums">{{ (int) $resumenPromocion['promovidos_dic'] }}</div>
            <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mt-1">Todo con examen en Dic</div>
            @if ((int) $resumenPromocion['total_estudiantes'] > 0)
                <div class="text-[11px] text-neutral-400 mt-1 tabular-nums">{{ $pctPromocion[1] }}%</div>
            @endif
        </div>
        <div class="se-card p-4 text-center">
            <div class="text-2xl font-bold text-amber-700 tabular-nums">{{ (int) $resumenPromocion['promovidos_feb'] }}</div>
            <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mt-1">Todo con examen en Feb</div>
            @if ((int) $resumenPromocion['total_estudiantes'] > 0)
                <div class="text-[11px] text-neutral-400 mt-1 tabular-nums">{{ $pctPromocion[2] }}%</div>
            @endif
        </div>
        <div class="se-card p-4 text-center col-span-2 sm:col-span-1">
            <div class="text-2xl font-bold text-red-700 tabular-nums">{{ (int) $resumenPromocion['no_promovidos'] }}</div>
            <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mt-1">No promovidos</div>
            @if ((int) $resumenPromocion['total_estudiantes'] > 0)
                <div class="text-[11px] text-neutral-400 mt-1 tabular-nums">{{ $pctPromocion[3] }}%</div>
            @endif
        </div>
    </div>
@endif
