@php
    $iconPaths = match ($icono ?? '') {
        'carga' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        'inscribir' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        'notas' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'historial' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        default => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    };
    $btnClass = 'inline-flex h-9 w-9 items-center justify-center rounded-xl border border-primary-200 bg-primary-50 text-primary-700 shadow-sm transition hover:border-primary-400 hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-primary-500/40';
    $usarNav = isset($navDestino) && isset($idLegajos) && (int) $idLegajos > 0;
    $href = $href ?? null;
    $activo = $usarNav || ($href !== null && $href !== '');
@endphp
<span class="inline-flex justify-center"
      title="{{ $titulo }} — {{ $descripcion }}{{ $activo ? '' : ' (próximamente)' }}">
    @if ($usarNav)
        <x-nav-contexto-estudiante
            :destino="$navDestino"
            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::EXAMENES_MATERIAS_ADEUDADAS"
            :id-legajos="$idLegajos"
            :buscar="$buscarListado ?? null"
            class="inline">
            <span class="{{ $btnClass }}" aria-label="{{ $titulo }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPaths }}"/>
                </svg>
            </span>
        </x-nav-contexto-estudiante>
    @elseif ($activo)
        <a href="{{ $href }}"
           wire:navigate
           class="{{ $btnClass }}"
           aria-label="{{ $titulo }}">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPaths }}"/>
            </svg>
        </a>
    @else
        <button type="button"
                disabled
                class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-xl border border-accent-200 bg-accent-50/80 text-neutral-400"
                aria-label="{{ $titulo }} (próximamente)">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPaths }}"/>
            </svg>
        </button>
    @endif
</span>
