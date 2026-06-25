<div class="se-page">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Gestión académica</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Legajos de estudiantes</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ route('abm.legajos.buscar-familias') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Buscar familias
                </a>
                @if (puedeModificarLegajosEstudiantes())
                    <a href="{{ route('abm.legajos.carga-por-curso') }}"
                       title="Permite cargar uno o más datos del legajo, por curso, sin tener que entrar legajo por legajo"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6h16M4 10h16M4 14h10M4 18h6"/>
                        </svg>
                        Carga por curso
                    </a>
                @endif
                <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Registros</span>
                    <span class="text-xl font-bold tabular-nums">{{ $legajos->total() }}</span>
                </span>
                @if (puedeModificarLegajosEstudiantes())
                    <a href="{{ route('abm.legajos.create') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 focus:outline-none focus:ring-2 focus:ring-white/60">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nuevo legajo
                    </a>
                @endif
            </div>
        </div>
    </section>

    <div class="se-toolbar">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <input wire:model.live.debounce.400ms="search"
                   type="search"
                   placeholder="Buscar por apellido, nombre o DNI..."
                   class="form-input pl-9">
        </div>
        <label class="inline-flex cursor-pointer items-center gap-3 rounded-xl border border-accent-200 bg-accent-50 px-3 py-2 text-sm font-medium text-neutral-700">
            <input wire:model.live="soloMatricula" type="checkbox" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
            Solo año actual
        </label>
        <label class="inline-flex cursor-pointer items-center gap-3 rounded-xl border border-accent-200 bg-accent-50 px-3 py-2 text-sm font-medium text-neutral-700">
            <input wire:model.live="soloMiNivel" type="checkbox" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
            Solo año actual de mi nivel
        </label>
    </div>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="se-section-title">Listado</p>
                    <p class="mt-1 text-sm text-neutral-600">Datos principales y recorrido de matriculación.</p>
                </div>
                <p class="text-xs font-medium text-neutral-500">{{ $legajos->count() }} visibles en esta página</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead class="bg-accent-50">
                    <tr>
                        <th class="table-header w-[min(30%,20rem)]">Estudiante</th>
                        <th class="table-header w-32">DNI</th>
                        <th class="table-header">Matriculaciones en la escuela</th>
                        <th class="table-header w-36"></th>
                        <th class="table-header w-40 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-accent-200 bg-white">
                    @forelse ($legajos as $l)
                        <tr id="legajo-{{ $l->id }}"
                            x-data="{ focus: {{ (int) $focusId === (int) $l->id ? 'true' : 'false' }} }"
                            x-init="if (focus) { $nextTick(() => { const el = document.getElementById('legajo-{{ $l->id }}'); el?.scrollIntoView({ block: 'center' }); el?.classList.add('ring-2','ring-primary-400','bg-primary-50/60'); el?.querySelector('a[data-focus-target]')?.focus(); }); }"
                            class="align-top transition-colors hover:bg-accent-50/60">
                            <td class="table-cell">
                                <div class="flex items-center gap-3">
                                    <div class="se-icon-badge h-10 w-10 text-sm font-bold">
                                        {{ mb_substr((string) $l->apellido, 0, 1) }}{{ mb_substr((string) $l->nombre, 0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-neutral-900" title="ID {{ $l->id }}">{{ $l->apellido }}, {{ $l->nombre }}</div>
                                        <div class="mt-0.5 text-xs text-neutral-500">Legajo {{ $l->legajo ?: 'sin número' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="table-cell font-mono text-neutral-700">
                                {{ $l->dni }}
                            </td>
                            <td class="table-cell py-2">
                                @if ($l->matriculas->isEmpty())
                                    <span class="se-pill text-neutral-500">Sin matrículas</span>
                                @else
                                    <div class="flex max-w-xs flex-col gap-0.5">
                                        @foreach ($l->matriculas as $mat)
                                            @php
                                                $esCicloActivo = (int) ($mat->idTerlec ?? 0) === (int) schoolCtx()->idTerlec;
                                                $nivelClase = \App\Support\MatriculaNivelEstilo::claseChipPorNombreNivel($mat->nivel?->nivel);
                                            @endphp
                                            <div @class([
                                                'se-mat-nivel-chip',
                                                $nivelClase,
                                                'se-mat-ciclo-activo' => $esCicloActivo,
                                            ]) title="ID {{ $mat->id }}">
                                                <div class="flex min-w-0 items-center gap-1">
                                                    <span class="shrink-0 font-mono font-bold tabular-nums">{{ $mat->terlec?->ano ?? '—' }}</span>
                                                    <span class="shrink-0 opacity-50">·</span>
                                                    <span class="shrink-0 font-semibold">
                                                        {{ $mat->nivel?->nivel ?: '—' }}
                                                    </span>
                                                    <span class="shrink-0 opacity-50">·</span>
                                                    <span class="min-w-0 truncate font-medium">
                                                        {{ $mat->curso?->cursec ? trim($mat->curso->cursec) : '—' }}
                                                    </span>
                                                    @if ($mat->condicion?->condicion)
                                                        <span class="shrink-0 opacity-50">·</span>
                                                        <span class="min-w-0 truncate opacity-80">
                                                            {{ $mat->condicion->condicion }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="table-cell align-top">
                                @if (puedeModificarLegajosEstudiantes())
                                    <x-nav-contexto-estudiante
                                        destino="abm.legajos.edit"
                                        :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::LEGAJO_ABM"
                                        :id-legajos="$l->id"
                                        :abrir-matriculas="true"
                                        class="inline">
                                        <span class="btn-primary btn-sm whitespace-nowrap">Gestionar matrículas</span>
                                    </x-nav-contexto-estudiante>
                                @endif
                            </td>
                            <td class="table-cell text-right">
                                <div class="flex flex-col items-stretch justify-end gap-2 sm:flex-row sm:items-center">
                                    @if (puedeModificarLegajosEstudiantes())
                                        <x-nav-contexto-estudiante
                                            destino="abm.legajos.edit"
                                            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::LEGAJO_ABM"
                                            :id-legajos="$l->id"
                                            class="inline">
                                            <span data-focus-target class="btn-secondary btn-sm">Editar</span>
                                        </x-nav-contexto-estudiante>
                                        <button wire:click="confirmDelete({{ $l->id }})"
                                                class="btn-danger btn-sm">Eliminar</button>
                                    @else
                                        <x-nav-contexto-estudiante
                                            destino="abm.legajos.edit"
                                            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::LEGAJO_ABM"
                                            :id-legajos="$l->id"
                                            class="inline">
                                            <span data-focus-target class="btn-secondary btn-sm">Ver</span>
                                        </x-nav-contexto-estudiante>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center gap-3">
                                    <div class="se-icon-badge">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8M8 11h8M8 15h5M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-neutral-700">
                                        @if ($search)
                                            No se encontraron legajos para "{{ $search }}".
                                        @else
                                            No hay legajos registrados.
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($legajos->hasPages())
            <div class="border-t border-accent-200 bg-accent-50/70 px-4 py-3">
                {{ $legajos->links('vendor.pagination.se') }}
            </div>
        @endif
    </div>

    @if ($showConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="px-6 py-5">
                    <div class="flex items-start gap-4">
                        <div @class([
                            'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl',
                            'bg-red-100 text-red-600' => $deleteId,
                            'bg-amber-100 text-amber-700' => ! $deleteId,
                        ])>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-neutral-900">
                                {{ $deleteId ? 'Confirmar eliminación' : 'No se puede eliminar' }}
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-neutral-600">{{ $deleteInfo }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/70 px-6 py-4">
                    <button wire:click="$set('showConfirm', false)" class="btn-secondary">
                        {{ $deleteId ? 'Cancelar' : 'Cerrar' }}
                    </button>
                    @if ($deleteId)
                        <button wire:click="delete" wire:loading.attr="disabled" class="btn-danger">
                            <span wire:loading.remove wire:target="delete">Eliminar</span>
                            <span wire:loading wire:target="delete">Eliminando...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
