@php
    $act = $actividad;
    $tipoNombre = $act->tipoRegistro?->nombre ?? 'Actividad Extraprogramática';
    $fechasTxt = \App\Support\ProyectosExtracurriculares\ExtActividadesService::textoResumenFechas($act);
    $grupoTxt = \App\Support\ProyectosExtracurriculares\ExtActividadesService::textoGrupoInvolucrado($act);
    $aCargo = $act->docentes
        ->where('rol', \App\Models\ExtActividad::ROL_A_CARGO)
        ->map(fn ($d) => $d->profesor?->nombre_completo)
        ->filter()
        ->implode('; ');
    $otros = $act->docentes
        ->where('rol', \App\Models\ExtActividad::ROL_OTRO)
        ->map(fn ($d) => $d->profesor?->nombre_completo)
        ->filter()
        ->implode('; ');
@endphp

<div class="space-y-4 text-sm text-neutral-800">
    <div>
        <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Tipo de registro</p>
        <p class="mt-0.5 font-semibold">{{ $tipoNombre }}</p>
    </div>
    <div>
        <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Actividad</p>
        <p class="mt-0.5 text-base font-bold text-neutral-900">{{ $act->nombre }}</p>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Fechas</p>
            <p class="mt-0.5">{{ $fechasTxt !== '' ? $fechasTxt : '—' }}</p>
        </div>
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Horario</p>
            <p class="mt-0.5">{{ $act->horario ?: '—' }}</p>
        </div>
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Lugar</p>
            <p class="mt-0.5">{{ $act->lugar ?: '—' }}</p>
        </div>
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Estado</p>
            <p class="mt-0.5">
                <span @class([
                    'se-pill',
                    'bg-emerald-100 text-emerald-800' => $act->estaAprobada(),
                    'bg-amber-100 text-amber-900' => $act->estaPendiente(),
                ])>
                    {{ \App\Support\ProyectosExtracurriculares\ExtActividadesService::etiquetaEstado((string) $act->estado) }}
                </span>
            </p>
        </div>
    </div>
    <div>
        <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Grupo involucrado</p>
        <p class="mt-0.5 leading-relaxed">{{ $grupoTxt !== '' ? $grupoTxt : '—' }}</p>
    </div>
    <div>
        <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Docente a cargo</p>
        <p class="mt-0.5">{{ $aCargo !== '' ? $aCargo : '—' }}</p>
    </div>
    @if ($otros !== '')
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Otros docentes</p>
            <p class="mt-0.5">{{ $otros }}</p>
        </div>
    @endif
    <div>
        <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Breve descripción</p>
        <p class="mt-1 whitespace-pre-wrap leading-relaxed text-neutral-700">{{ $act->descripcion ?: '—' }}</p>
    </div>
    <div>
        <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Evaluación</p>
        <p class="mt-1 whitespace-pre-wrap leading-relaxed text-neutral-700">{{ $act->evaluacion ?: '—' }}</p>
    </div>
    @if ($act->proponente)
        <p class="text-xs text-neutral-500">Presentado por {{ $act->proponente->nombre_completo }}</p>
    @endif
</div>
