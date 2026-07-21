<div class="se-page max-w-7xl">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="se-soft-card flex items-center gap-3 border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner space-y-3">
            <p class="se-eyebrow">Exámenes · Programas</p>
            <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Planificaciones y programas</h2>
            <p class="max-w-3xl text-sm text-white/85">
                Repositorio institucional de PDF. El nombre del archivo identifica colegio, año, nivel, curso, materia y tipo.
            </p>
            <div class="flex flex-wrap gap-4 text-xs text-white/80">
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-amber-100/90 text-amber-700">+</span>
                    Sin archivo
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-5 w-5 text-sky-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    Subido (pendiente de aprobación)
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    Aprobado (visible para estudiantes)
                </span>
            </div>
        </div>
    </section>

    @if (! $tablaDisponible)
        <div class="se-card mt-6 border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950" role="alert">
            <p class="font-semibold">Falta la tabla <code>doc_pp</code></p>
            <p class="mt-1">Ejecute el SQL de creación de la tabla en este tenant antes de usar el módulo.</p>
        </div>
    @else
        <div class="se-toolbar mt-6 flex-col !items-stretch gap-4 sm:flex-row sm:items-end">
            <div class="w-full max-w-md">
                <label for="docpp-busqueda" class="form-label">Búsqueda rápida</label>
                <div class="relative mt-1.5">
                    <input id="docpp-busqueda" type="search" wire:model.live.debounce.300ms="busqueda"
                           class="form-input w-full pl-10" placeholder="Materia o curso…" autocomplete="off">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
            <div class="w-full max-w-xs">
                <label for="docpp-orden" class="form-label">Ordenar por</label>
                <select id="docpp-orden" wire:model.live="orden" class="form-select mt-1.5 w-full">
                    <option value="{{ \App\Support\DocPp\DocPpConsulta::ORDEN_CURSO }}">Curso y materia</option>
                    <option value="{{ \App\Support\DocPp\DocPpConsulta::ORDEN_MATERIA }}">Materia</option>
                </select>
            </div>
        </div>

        <div class="se-card mt-6 overflow-hidden p-0">
            <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>

            @if ($filas->isEmpty())
                <div class="px-6 py-12 text-center text-sm text-neutral-600">
                    No hay materias para el contexto activo.
                </div>
            @else
                <div class="w-full overflow-x-auto">
                    <div class="flex justify-start">
                        <table class="min-w-[56rem] w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-accent-50 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                                    <th class="px-3 py-2.5 border-b border-accent-200 whitespace-nowrap">Año</th>
                                    <th class="px-3 py-2.5 border-b border-accent-200 whitespace-nowrap">Cursec</th>
                                    <th class="px-3 py-2.5 border-b border-accent-200">Materia</th>
                                    <th class="px-3 py-2.5 border-b border-accent-200 text-center bg-amber-50/80">Planific.</th>
                                    <th class="px-3 py-2.5 border-b border-accent-200 bg-amber-50/40">Obs. planif.</th>
                                    <th class="px-3 py-2.5 border-b border-accent-200 text-center bg-emerald-50/80">Programa</th>
                                    <th class="px-3 py-2.5 border-b border-accent-200 bg-emerald-50/40">Obs. prog.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filas as $fila)
                                    @php
                                        $estadoPlan = \App\Support\DocPp\DocPpConsulta::estadoCelda(
                                            isset($fila->plan_id) ? (int) $fila->plan_id : null,
                                            isset($fila->plan_aprobado) ? (int) $fila->plan_aprobado : null,
                                        );
                                        $estadoProg = \App\Support\DocPp\DocPpConsulta::estadoCelda(
                                            isset($fila->prog_id) ? (int) $fila->prog_id : null,
                                            isset($fila->prog_aprobado) ? (int) $fila->prog_aprobado : null,
                                        );
                                        $cursec = \App\Support\DocPp\DocPpConsulta::etiquetaCurso($fila);
                                    @endphp
                                    <tr wire:key="docpp-row-{{ $fila->id }}" class="border-b border-accent-100 hover:bg-accent-50/60">
                                        <td class="px-3 py-2 tabular-nums text-neutral-700 whitespace-nowrap">{{ number_format((int) $fila->ano_lectivo, 0, ',', '.') }}</td>
                                        <td class="px-3 py-2 font-medium text-neutral-800 whitespace-nowrap">{{ $cursec }}</td>
                                        <td class="px-3 py-2 text-neutral-800">{{ $fila->materia }}</td>
                                        <td class="px-3 py-2 text-center bg-amber-50/30">
                                            @include('livewire.doc-pp.partials.celda-estado', [
                                                'id' => $fila->id,
                                                'tipo' => \App\Support\DocPp\DocPpStorage::TIPO_PLAN,
                                                'estado' => $estadoPlan,
                                            ])
                                        </td>
                                        <td class="px-3 py-2 text-xs text-neutral-600 bg-amber-50/20 max-w-[10rem] truncate" title="{{ $fila->plan_obs ?? '' }}">{{ $fila->plan_obs ?? '' }}</td>
                                        <td class="px-3 py-2 text-center bg-emerald-50/30">
                                            @include('livewire.doc-pp.partials.celda-estado', [
                                                'id' => $fila->id,
                                                'tipo' => \App\Support\DocPp\DocPpStorage::TIPO_PROG,
                                                'estado' => $estadoProg,
                                            ])
                                        </td>
                                        <td class="px-3 py-2 text-xs text-neutral-600 bg-emerald-50/20 max-w-[10rem] truncate" title="{{ $fila->prog_obs ?? '' }}">{{ $fila->prog_obs ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($filas->hasPages())
                    <div class="se-matriz-list-footer">
                        {{ $filas->links('vendor.pagination.se-compact') }}
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
