@php
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Cuotas\GestionAranceles;
@endphp

<div class="se-page max-w-[90rem] mx-auto">
    <section class="se-hero mb-6">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Administración</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Gestión de aranceles</h1>
                <p class="text-sm text-white/80 max-w-2xl">
                    Ciclo lectivo {{ schoolCtx()->terlecAno() }} — busque cualquier estudiante que haya tenido matrícula en la escuela.
                </p>
            </div>
        </div>
    </section>

    <div class="se-toolbar mb-4" x-data x-init="$nextTick(() => $refs.cuotasBuscar?.focus())">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <input wire:model.live.debounce.400ms="search"
                   type="search"
                   x-ref="cuotasBuscar"
                   autofocus
                   placeholder="Apellido y nombre, apellido, nombre o DNI..."
                   class="form-input pl-9"
                   autocomplete="off">
        </div>
    </div>

    @if ($legajos === null)
        <div class="se-card p-6 text-sm text-neutral-600">
            Ingrese al menos un criterio de búsqueda para listar estudiantes con matrícula en la escuela.
        </div>
    @elseif ($legajos->isEmpty())
        <div class="se-card p-8 text-center text-sm text-neutral-600">
            No se encontraron estudiantes con ese criterio.
        </div>
    @else
        <div class="se-card se-card-cuotas-busqueda overflow-hidden p-0">
            <div class="w-full overflow-x-auto">
                <div class="gf gf-vcenter gf-cuotas-busqueda">
                    <div class="gf-head">
                        <div class="gf-th gf-th-nombre">Apellido y nombre</div>
                        <div class="gf-th w-28">DNI</div>
                        <div class="gf-th w-16 text-center">Año lect.</div>
                        <div class="gf-th w-32">Curso</div>
                        <div class="gf-th w-40 shrink-0">Nivel</div>
                        <div class="gf-th w-28 shrink-0" title="Condición en el ciclo lectivo actual">Condición</div>
                        <div class="gf-th w-20 justify-center">Beca</div>
                        <div class="gf-th w-32 justify-center"></div>
                    </div>

                    @foreach ($legajos as $l)
                        @php
                            $datos = GestionAranceles::datosListadoBusqueda($l);
                            $nombreCompleto = trim($l->apellido.', '.$l->nombre);
                        @endphp
                        <div @class([
                            'gf-row gf-row-hover',
                            'gf-row--sin-matricula-actual' => ! $datos['tieneMatriculaActual'],
                        ]) wire:key="legajo-{{ $l->id }}">
                            <div class="gf-td gf-td-nombre font-medium">
                                <x-nav-contexto-estudiante
                                    destino="cuotas.estudiante"
                                    :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::CUOTAS_GESTION"
                                    :id-legajos="$l->id"
                                    class="inline-block max-w-full">
                                    <span class="block truncate text-left text-primary-800 hover:text-primary-600 hover:underline cursor-pointer"
                                          title="{{ $nombreCompleto }}">
                                        {!! CuotasFormato::resaltarTerminoBusqueda($nombreCompleto, $search) !!}
                                    </span>
                                </x-nav-contexto-estudiante>
                            </div>
                            <div class="gf-td w-28 tabular-nums whitespace-nowrap">
                                {{ CuotasFormato::formatearDni($l->dni) }}
                            </div>
                            <div class="gf-td w-16 text-center tabular-nums whitespace-nowrap" title="Última matrícula registrada">
                                {{ $datos['anoUltimaMatricula'] }}
                            </div>
                            <div class="gf-td w-32 uppercase whitespace-nowrap">{{ $datos['curso'] }}</div>
                            <div class="gf-td gf-td-nivel w-40 shrink-0">
                                @if ($datos['nivelEtiqueta'] === '—')
                                    <span class="text-neutral-500">—</span>
                                @else
                                    <span @class([
                                        'se-mat-nivel-chip inline-block max-w-full truncate font-semibold',
                                        $datos['claseChipNivel'],
                                    ]) title="{{ $datos['nivelEtiqueta'] }}">
                                        {{ $datos['nivelEtiqueta'] }}
                                    </span>
                                @endif
                            </div>
                            <div class="gf-td w-28 shrink-0 truncate whitespace-nowrap"
                                 title="{{ $datos['condicion'] !== '—' ? 'Ciclo actual: '.$datos['condicion'] : 'Sin matrícula en el ciclo lectivo actual' }}">
                                {{ $datos['condicion'] }}
                            </div>
                            <div class="gf-td w-20 justify-center whitespace-nowrap">{{ $datos['beca'] }}</div>
                            <div class="gf-td w-32 justify-center">
                                <x-nav-contexto-estudiante
                                    destino="cuotas.estudiante"
                                    :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::CUOTAS_GESTION"
                                    :id-legajos="$l->id"
                                    class="inline">
                                    <span class="inline-flex cursor-pointer items-center justify-center rounded border border-gray-400 bg-white px-2 py-0.5 text-[10px] font-semibold text-primary-700 hover:bg-primary-50">
                                        Gestionar
                                    </span>
                                </x-nav-contexto-estudiante>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @if ($legajos->hasPages())
                <div class="border-t border-gray-300 bg-accent-50/50 px-2 py-2">
                    {{ $legajos->links('vendor.pagination.se') }}
                </div>
            @endif
        </div>
    @endif
</div>
