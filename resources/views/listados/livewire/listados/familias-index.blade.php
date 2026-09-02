@php
    use App\Support\Alumnos\ArancelesEscolares;
    use App\Support\Listados\ListadoFamiliasConsulta;
@endphp

<div class="se-page max-w-[90rem] mx-auto">
    <section class="se-hero mb-6">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Estudiantes</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Listado de familias</h1>
                <p class="text-sm text-white/80 max-w-2xl">
                    Familias con estudiantes matriculados en el ciclo lectivo {{ schoolCtx()->terlecAno() }}@if (! $mostrarFiltroNivel) ({{ schoolCtx()->nivelNombre() }})@endif.
                    Curso y sección del año en curso.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a href="{{ $this->urlListadoPdf() }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   @class([
                       'inline-flex items-center justify-center rounded-2xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors',
                       'border-white/20 bg-white text-primary-700 hover:bg-accent-50' => ! $familias->isEmpty(),
                       'pointer-events-none border-white/10 bg-white/20 text-white/50' => $familias->isEmpty(),
                   ])
                   title="Listado PDF con los filtros actuales">
                    Exportar PDF
                </a>
                <a href="{{ $this->urlListadoExcel() }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   @class([
                       'inline-flex items-center justify-center rounded-2xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors',
                       'border-white/20 bg-white text-primary-700 hover:bg-accent-50' => ! $familias->isEmpty(),
                       'pointer-events-none border-white/10 bg-white/20 text-white/50' => $familias->isEmpty(),
                   ])
                   title="Listado Excel con los filtros actuales">
                    Exportar Excel
                </a>
            </div>
        </div>
    </section>

    <div class="se-toolbar mb-4 sm:items-end">
        <div class="flex-1 max-w-xl">
            <label for="listado-familias-buscar" class="form-label">Búsqueda</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input wire:model.live.debounce.400ms="search"
                       id="listado-familias-buscar"
                       type="search"
                       placeholder="Familia, responsable, email, apellido, nombre o DNI…"
                       class="form-input pl-9"
                       autocomplete="off">
            </div>
        </div>
        @if ($mostrarFiltroNivel)
            <div class="w-full sm:w-56 shrink-0">
                <label for="filtro-nivel-familias" class="form-label">Nivel</label>
                <select id="filtro-nivel-familias"
                        wire:model.live="idNivel"
                        class="form-input">
                    <option value="">Todos</option>
                    @foreach ($niveles as $nivel)
                        <option value="{{ (int) $nivel->id }}">{{ $nivel->nivel }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <p class="shrink-0 text-xs font-medium tabular-nums text-neutral-500 sm:pb-2">
            {{ $familias->total() }} familia{{ $familias->total() === 1 ? '' : 's' }}
        </p>
    </div>

    @if ($familias->isEmpty())
        <div class="se-card p-8 text-center text-sm text-neutral-600">
            @if (trim($search) !== '' || $idNivel !== '')
                No se encontraron familias con ese criterio.
            @else
                No hay familias con estudiantes matriculados en el ciclo lectivo activo.
            @endif
        </div>
    @else
        <div class="se-card overflow-hidden p-0">
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <table class="min-w-[72rem] w-full border-collapse [&_td]:border-b [&_td]:border-accent-200">
                        <thead class="bg-accent-50">
                            <tr>
                                <th class="table-header whitespace-nowrap">Familia</th>
                                <th class="table-header whitespace-nowrap">Responsable</th>
                                @if ($tieneDniResp)
                                    <th class="table-header whitespace-nowrap">DNI responsable</th>
                                @endif
                                <th class="table-header">Email</th>
                                <th class="table-header whitespace-nowrap">Apellido</th>
                                <th class="table-header whitespace-nowrap">Nombre</th>
                                <th class="table-header whitespace-nowrap">DNI</th>
                                <th class="table-header whitespace-nowrap">Curso</th>
                                <th class="table-header whitespace-nowrap">Sección</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($familias as $familia)
                                @php
                                    $estudiantes = $familia->legajos;
                                    $span = max(1, $estudiantes->count());
                                    $fondoGrupo = $loop->even ? 'bg-white' : 'bg-accent-50';
                                    $etiquetaFamilia = trim((string) ($familia->apellido ?? ''));
                                    $etiquetaResponsable = trim((string) ($familia->responsable ?? ''));
                                    $dniResp = $tieneDniResp
                                        ? ArancelesEscolares::formatearDni($familia->dniResp ?? '')
                                        : '';
                                    $email = trim((string) ($familia->email ?? ''));
                                @endphp
                                @forelse ($estudiantes as $estudiante)
                                    @php
                                        $cursoSeccion = ListadoFamiliasConsulta::cursoYSeccionDeLegajo($estudiante);
                                        $dniEst = ArancelesEscolares::formatearDni($estudiante->dni);
                                    @endphp
                                    <tr class="align-top {{ $fondoGrupo }}" wire:key="fam-{{ $familia->id }}-est-{{ $estudiante->id }}">
                                        @if ($loop->first)
                                            <td rowspan="{{ $span }}" class="table-cell align-top font-medium text-neutral-900">{{ $etiquetaFamilia !== '' ? $etiquetaFamilia : '—' }}</td>
                                            <td rowspan="{{ $span }}" class="table-cell align-top text-neutral-800">{{ $etiquetaResponsable !== '' ? $etiquetaResponsable : '—' }}</td>
                                            @if ($tieneDniResp)
                                                <td rowspan="{{ $span }}" class="table-cell align-top tabular-nums whitespace-nowrap text-neutral-700">{{ $dniResp !== '' ? $dniResp : '—' }}</td>
                                            @endif
                                            <td rowspan="{{ $span }}" class="table-cell align-top break-all text-neutral-700">{{ $email !== '' ? $email : '—' }}</td>
                                        @endif
                                        <td class="table-cell font-medium text-neutral-900">{{ trim((string) ($estudiante->apellido ?? '')) !== '' ? $estudiante->apellido : '—' }}</td>
                                        <td class="table-cell text-neutral-800">{{ trim((string) ($estudiante->nombre ?? '')) !== '' ? $estudiante->nombre : '—' }}</td>
                                        <td class="table-cell tabular-nums whitespace-nowrap text-neutral-700">{{ $dniEst !== '' ? $dniEst : '—' }}</td>
                                        <td class="table-cell whitespace-nowrap text-neutral-700">{{ $cursoSeccion['curso'] !== '' ? $cursoSeccion['curso'] : '—' }}</td>
                                        <td class="table-cell whitespace-nowrap text-neutral-700">{{ $cursoSeccion['seccion'] !== '' ? $cursoSeccion['seccion'] : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr class="align-top {{ $fondoGrupo }}" wire:key="fam-{{ $familia->id }}-vacia">
                                        <td class="table-cell align-top font-medium text-neutral-900">{{ $etiquetaFamilia !== '' ? $etiquetaFamilia : '—' }}</td>
                                        <td class="table-cell align-top text-neutral-800">{{ $etiquetaResponsable !== '' ? $etiquetaResponsable : '—' }}</td>
                                        @if ($tieneDniResp)
                                            <td class="table-cell align-top tabular-nums whitespace-nowrap text-neutral-700">{{ $dniResp !== '' ? $dniResp : '—' }}</td>
                                        @endif
                                        <td class="table-cell align-top break-all text-neutral-700">{{ $email !== '' ? $email : '—' }}</td>
                                        <td class="table-cell text-neutral-400 italic" colspan="5">Sin estudiantes en el ciclo activo</td>
                                    </tr>
                                @endforelse
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($familias->hasPages())
                <div class="se-matriz-list-footer">
                    {{ $familias->links('vendor.pagination.se-compact') }}
                </div>
            @endif
        </div>
    @endif
</div>
