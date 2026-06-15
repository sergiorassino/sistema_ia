@php
    use App\Support\Cooperadora\BusquedaEstudianteCooperadora;
    use App\Support\Cuotas\CuotasFormato;
@endphp

<div class="se-page max-w-[90rem] mx-auto">
    <section class="se-hero mb-6">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Cooperadora</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Pagos por estudiante</h1>
                <p class="text-sm text-white/80 max-w-2xl">
                    Ciclo lectivo {{ $anoCiclo }} — busque un estudiante para ver sus ingresos registrados y el envío de recibos.
                </p>
            </div>
        </div>
    </section>

    <div class="se-toolbar mb-4" x-data x-init="$nextTick(() => $refs.coopPagosBuscar?.focus())">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <input wire:model.live.debounce.400ms="search"
                   type="search"
                   x-ref="coopPagosBuscar"
                   autofocus
                   placeholder="Apellido y nombre, apellido, nombre o DNI..."
                   class="form-input pl-9"
                   autocomplete="off">
        </div>
    </div>

    @if ($legajos === null)
        <div class="se-card p-6 text-sm text-neutral-600">
            Ingrese al menos un criterio de búsqueda para listar estudiantes.
        </div>
    @elseif ($legajos->isEmpty())
        <div class="se-card p-8 text-center text-sm text-neutral-600">
            No se encontraron estudiantes con ese criterio.
        </div>
    @else
        <div class="se-card overflow-hidden p-0">
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="gf gf-vcenter gf-coop-pagos-busqueda">
                        <div class="gf-head">
                            <div class="gf-th gf-th-nombre">Apellido y nombre</div>
                            <div class="gf-th gf-th-dni">DNI</div>
                            <div class="gf-th gf-th-ano">Año lect.</div>
                            <div class="gf-th gf-th-curso">Curso</div>
                            <div class="gf-th gf-th-nivel">Nivel</div>
                            <div class="gf-th gf-th-hermano">Hermano</div>
                            <div class="gf-th gf-th-accion"></div>
                        </div>

                        @foreach ($legajos as $l)
                            @php
                                $datos = BusquedaEstudianteCooperadora::datosListadoBusqueda($l);
                                $nombreCompleto = trim($l->apellido.', '.$l->nombre);
                            @endphp
                            <div @class([
                                'gf-row gf-row-hover',
                                'gf-row--sin-matricula-actual' => ! $datos['tieneMatriculaActual'],
                            ]) wire:key="coop-legajo-{{ $l->id }}">
                                <div class="gf-td gf-td-nombre font-medium">
                                    <x-nav-contexto-estudiante
                                        destino="cooperadora.pagos-estudiante.ver"
                                        :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::COOPERADORA_PAGOS_ESTUDIANTE"
                                        :id-legajos="$l->id"
                                        class="inline-block max-w-full">
                                        <span class="block text-left text-primary-800 hover:text-primary-600 hover:underline cursor-pointer">
                                            {!! CuotasFormato::resaltarTerminoBusqueda($nombreCompleto, $search) !!}
                                        </span>
                                    </x-nav-contexto-estudiante>
                                </div>
                                <div class="gf-td gf-td-dni tabular-nums">
                                    {{ CuotasFormato::formatearDni($l->dni) }}
                                </div>
                                <div class="gf-td gf-td-ano tabular-nums">
                                    {{ $datos['anoUltimaMatricula'] }}
                                </div>
                                <div class="gf-td gf-td-curso uppercase">{{ $datos['curso'] }}</div>
                                <div class="gf-td gf-td-nivel">
                                    @if ($datos['nivelEtiqueta'] === '—')
                                        <span class="text-neutral-500">—</span>
                                    @else
                                        <span @class([
                                            'se-mat-nivel-chip inline-block font-semibold',
                                            $datos['claseChipNivel'],
                                        ])>
                                            {{ $datos['nivelEtiqueta'] }}
                                        </span>
                                    @endif
                                </div>
                                <div class="gf-td gf-td-hermano">
                                    @if ($datos['esHermanoCooperadora'])
                                        <span class="se-pill bg-primary-100 text-primary-800 text-[10px]">Sí</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </div>
                                <div class="gf-td gf-td-accion">
                                    <x-nav-contexto-estudiante
                                        destino="cooperadora.pagos-estudiante.ver"
                                        :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::COOPERADORA_PAGOS_ESTUDIANTE"
                                        :id-legajos="$l->id"
                                        tag="a"
                                        class="inline">
                                        <span class="inline-flex cursor-pointer items-center rounded-lg border border-primary-200 bg-white px-3 py-1 text-xs font-semibold text-primary-700 hover:bg-primary-50">
                                            Ver pagos
                                        </span>
                                    </x-nav-contexto-estudiante>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">{{ $legajos->links() }}</div>
    @endif
</div>
