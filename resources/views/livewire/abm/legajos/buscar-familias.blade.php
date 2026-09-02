<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Legajos de estudiantes</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Buscar familias</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('abm.legajos') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver al listado
                </a>
            </div>
        </div>
    </section>

    <div class="se-toolbar">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <input wire:model.live.debounce.300ms="filtroFamilias"
                   id="filtro-buscar-familias"
                   type="search"
                   placeholder="Apellido, responsable, email o ID de familia…"
                   class="form-input pl-9"
                   autocomplete="off">
        </div>
        @if ($familiaSeleccionadaId)
            <button type="button" wire:click="limpiarSeleccion" class="btn-secondary whitespace-nowrap">
                Nueva búsqueda
            </button>
        @endif
    </div>

    <div @class(['grid gap-6', 'lg:grid-cols-2' => ! $familiaSeleccionadaId])>
        {{-- Resultados de búsqueda (solo mientras no hay familia elegida) --}}
        @if (! $familiaSeleccionadaId)
            <section class="se-card overflow-hidden">
                <div class="border-b border-accent-200 bg-accent-50/60 px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">Familias encontradas</h3>
                    <p class="mt-1 text-xs text-neutral-500">
                        Escriba al menos {{ $minCharsBusqueda }} caracteres. Haga clic en una fila para ver el detalle y los estudiantes vinculados.
                    </p>
                </div>
                <div class="px-5 py-4 sm:px-6" wire:key="resultados-buscar-familias-{{ md5($filtroFamilias) }}">
                    @php $filtroBusqueda = trim($filtroFamilias); @endphp
                    @if (mb_strlen($filtroBusqueda) < $minCharsBusqueda)
                        <p class="py-8 text-center text-sm text-neutral-500">
                            Ingrese al menos {{ $minCharsBusqueda }} caracteres para buscar familias.
                        </p>
                    @elseif ($familiasCoincidentes->isEmpty())
                        <p class="py-8 text-center text-sm text-neutral-500">
                            No hay familias que coincidan con «{{ $filtroBusqueda }}».
                        </p>
                    @else
                        <p class="mb-3 text-xs text-neutral-600">
                            {{ $familiasCoincidentes->count() }} coincidencia{{ $familiasCoincidentes->count() === 1 ? '' : 's' }}
                            @if ($familiasCoincidentes->count() >= 50)
                                <span class="text-neutral-400">(máximo 50; refine la búsqueda si falta alguna)</span>
                            @endif
                        </p>
                        <ul class="max-h-[28rem] divide-y divide-accent-200 overflow-y-auto rounded-xl border border-accent-200 bg-white">
                            @foreach ($familiasCoincidentes as $f)
                                <li>
                                    <button type="button"
                                            wire:click="seleccionarFamilia({{ $f->id }})"
                                            class="flex w-full flex-col items-start gap-0.5 px-4 py-3 text-left transition hover:bg-accent-50">
                                        <span class="text-sm font-medium text-neutral-900">
                                            {{ $f->apellido }}{{ $f->responsable ? ' – ' . $f->responsable : '' }}
                                        </span>
                                        <span class="text-xs text-neutral-500">
                                            ID {{ $f->id }}@if($f->email) · {{ $f->email }}@endif
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        @endif

        {{-- Datos de la familia --}}
        <section class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/60 px-5 py-4 sm:px-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">Datos de la familia</h3>
            </div>
            <div class="px-5 py-5 sm:px-6">
                @if ($familia)
                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($etiquetasFamilia as $columna => $etiqueta)
                            <div @class(['sm:col-span-2' => $columna === 'email'])>
                                <dt class="form-label">{{ $etiqueta }}</dt>
                                <dd class="text-sm font-medium text-neutral-900 break-all">
                                    @php $valor = $familia->{$columna}; @endphp
                                    {{ ($valor !== null && $valor !== '') ? $valor : '—' }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="text-sm text-neutral-500">
                        Seleccione una familia en la lista de resultados para ver sus datos.
                    </p>
                @endif
            </div>
        </section>
    </div>

    {{-- Estudiantes vinculados --}}
    <section class="se-card mt-6 overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="se-section-title">Estudiantes vinculados</p>
                    <p class="mt-1 text-sm text-neutral-600">
                        Legajos asociados a la familia seleccionada.
                    </p>
                </div>
                @if ($familia)
                    <p class="text-xs font-medium text-neutral-500">
                        {{ $hijos->count() }} estudiante{{ $hijos->count() === 1 ? '' : 's' }}
                    </p>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            @if (! $familia)
                <p class="px-6 py-12 text-center text-sm text-neutral-500">
                    Elija una familia para listar los legajos de sus hijos.
                </p>
            @elseif ($hijos->isEmpty())
                <p class="px-6 py-12 text-center text-sm text-neutral-500">
                    No hay legajos vinculados a esta familia.
                </p>
            @else
                <table class="min-w-full border-collapse">
                    <thead class="bg-accent-50">
                        <tr>
                            <th class="table-header w-[min(30%,20rem)]">Estudiante</th>
                            <th class="table-header w-32">DNI</th>
                            <th class="table-header">Matriculaciones</th>
                            <th class="table-header w-36 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-200 bg-white">
                        @foreach ($hijos as $h)
                            <tr class="align-top transition-colors hover:bg-accent-50/60">
                                <td class="table-cell">
                                    <div class="flex items-center gap-3">
                                        <div class="se-icon-badge h-10 w-10 text-sm font-bold">
                                            {{ mb_substr((string) $h->apellido, 0, 1) }}{{ mb_substr((string) $h->nombre, 0, 1) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-neutral-900">{{ $h->apellido }}, {{ $h->nombre }}</div>
                                            <div class="mt-0.5 text-xs text-neutral-500">Legajo {{ $h->legajo ?: 'sin número' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="table-cell font-mono text-neutral-700">{{ $h->dni }}</td>
                                <td class="table-cell py-2">
                                    @if ($h->matriculas->isEmpty())
                                        <span class="se-pill text-neutral-500">Sin matrículas</span>
                                    @else
                                        <div class="flex max-w-xs flex-col gap-0.5">
                                            @foreach ($h->matriculas as $mat)
                                                @php
                                                    $nivelNombre = mb_strtolower(trim((string) ($mat->nivel?->nivel ?? '')));
                                                    $esCicloActivo = (int) ($mat->idTerlec ?? 0) === (int) schoolCtx()->idTerlec;
                                                    $nivelClase = match (true) {
                                                        str_contains($nivelNombre, 'inicial') => 'se-mat-nivel-inicial',
                                                        str_contains($nivelNombre, 'primar') => 'se-mat-nivel-primario',
                                                        str_contains($nivelNombre, 'secund') => 'se-mat-nivel-secundario',
                                                        default => 'se-mat-nivel-default',
                                                    };
                                                @endphp
                                                <div @class([
                                                    'se-mat-nivel-chip',
                                                    $nivelClase,
                                                    'se-mat-ciclo-activo' => $esCicloActivo,
                                                ])>
                                                    <div class="flex min-w-0 items-center gap-1">
                                                        <span class="shrink-0 font-mono font-bold tabular-nums">{{ $mat->terlec?->ano ?? '—' }}</span>
                                                        <span class="shrink-0 opacity-50">·</span>
                                                        <span class="shrink-0 font-semibold" title="{{ $mat->nivel?->nivel }}">
                                                            {{ $mat->nivel?->nivel ?: '—' }}
                                                        </span>
                                                        <span class="shrink-0 opacity-50">·</span>
                                                        <span class="min-w-0 truncate font-medium" title="{{ $mat->curso?->cursec }}">
                                                            {{ $mat->curso?->cursec ? trim($mat->curso->cursec) : '—' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="table-cell text-right">
                                    <x-nav-contexto-estudiante
                                        destino="abm.legajos.edit"
                                        :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::LEGAJO_ABM"
                                        :id-legajos="$h->id"
                                        tag="a">
                                        <span class="btn-secondary btn-sm whitespace-nowrap">Ver legajo</span>
                                    </x-nav-contexto-estudiante>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</div>
