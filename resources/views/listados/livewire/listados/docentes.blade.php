<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Listados</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                        Listado de docentes
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Roles disponibles</span>
                    <span class="text-xl font-bold tabular-nums">{{ $roles->count() }}</span>
                </span>
                @if ($roles->isNotEmpty())
                    <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                        <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Seleccionados</span>
                        <span class="text-xl font-bold tabular-nums">{{ count($rolesElegidos) }}</span>
                    </span>
                    <a href="{{ $this->excelUrlCompleto }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       @class([
                           'inline-flex items-center justify-center rounded-2xl border px-4 py-3 text-sm font-semibold shadow-sm transition-colors',
                           'border-white/20 bg-white text-primary-700 hover:bg-accent-50' => $this->puedeExportarExcelCompleto(),
                           'pointer-events-none border-white/10 bg-white/20 text-white/50' => ! $this->puedeExportarExcelCompleto(),
                       ])
                       title="{{ $puedeVerDatosPersonales ? 'Todos los roles, todas las columnas del legajo docente. Archivo Docentes'.schoolCtx()->terlecAno().'.xlsx' : 'Todos los roles; solo apellido, nombre y DNI (sin permiso de edición de legajos docentes).' }}">
                        Exportar Excel
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if ($roles->isEmpty())
        <div class="se-card p-8">
            <div class="flex flex-col items-center justify-center gap-4 text-center sm:flex-row sm:text-left">
                <div class="se-icon-badge h-14 w-14">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <div class="max-w-md">
                    <p class="text-sm font-semibold text-neutral-800">Sin roles configurados</p>
                    <p class="mt-1 text-sm text-neutral-600">No hay tipos de profesor cargados en la tabla profesortipo.</p>
                </div>
            </div>
        </div>
    @else
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-neutral-700">
                        Elija los roles que incluirá en el PDF y el Excel de esta selección.
                    </p>
                    <span class="se-pill shrink-0 tabular-nums">
                        {{ $cantidadSeleccionados }} de {{ $roles->count() }} seleccionados
                    </span>
                </div>
            </div>

            <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
                <label for="filtro-roles-listado" class="form-label">Buscar rol</label>
                <input id="filtro-roles-listado"
                       type="search"
                       wire:model.live.debounce.300ms="filtroRoles"
                       placeholder="Nombre del rol…"
                       class="form-input max-w-xl" />
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button"
                            wire:click="seleccionarTodosRoles"
                            class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                        Todos
                    </button>
                    <button type="button"
                            wire:click="quitarTodosRoles"
                            class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                        Ninguno
                    </button>
                </div>
            </div>

            @if ($rolesFiltrados === [])
                <div class="px-4 py-6 text-sm text-neutral-600 sm:px-5">
                    @if (trim($filtroRoles) !== '')
                        Ningún rol coincide con la búsqueda.
                    @else
                        No hay roles para mostrar.
                    @endif
                </div>
            @else
                <div class="max-h-[min(40dvh,24rem)] overflow-y-auto px-3 py-2 sm:px-4">
                    <div class="mx-auto w-full max-w-md se-grid-angosta-wrap">
                        <ul class="min-w-0 flex-1 list-none divide-y divide-accent-100/80 rounded-lg border border-accent-200/90 bg-accent-50/20 px-2 py-1">
                            @foreach ($rolesFiltrados as $rolItem)
                                <li wire:key="listado-rol-{{ $rolItem['id'] }}">
                                    <label class="flex cursor-pointer items-center gap-2 py-2 transition-colors hover:bg-accent-50/70">
                                        <input type="checkbox"
                                               wire:model.live="rolesElegidos"
                                               value="{{ $rolItem['id'] }}"
                                               class="h-4 w-4 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                        <span class="min-w-0 flex-1 text-sm leading-normal text-neutral-800">
                                            {{ $rolItem['etiqueta'] }}
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if ($cantidadSeleccionados > 0)
                <div class="border-t border-accent-200 bg-accent-50/40 px-4 py-3 sm:px-5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Seleccionados</p>
                    <div class="mt-2 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                        @foreach ($rolesSeleccionadosResumen as $chip)
                            <span class="inline-flex max-w-full items-center gap-1 rounded-lg border border-primary-200 bg-white px-2 py-1 text-xs font-medium leading-snug text-neutral-800">
                                <span class="truncate">{{ $chip['label'] }}</span>
                                <button type="button"
                                        wire:click="quitarRol({{ $chip['id'] }})"
                                        class="shrink-0 text-neutral-400 hover:text-red-600"
                                        title="Quitar"
                                        aria-label="Quitar {{ $chip['label'] }}">×</button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="se-section-title">Columnas del listado</p>
                        @if ($puedeVerDatosPersonales)
                            <p class="mt-1 text-sm text-neutral-600">Un bloque por cada solapa del legajo docente (<span class="font-mono">solapas_legajo_profesor</span>). Dentro: columnas de <span class="font-mono">campos_profesores</span> asignadas a esa solapa. Slug <span class="font-mono">docente</span>: incluye apellido, nombre, DNI y rol.</p>
                        @else
                            <p class="mt-1 text-sm text-neutral-600">Sin permiso de edición de legajos docentes: solo se pueden listar, imprimir y exportar apellido, nombre y DNI.</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="seleccionarSoloDefecto" class="btn-secondary btn-sm whitespace-nowrap">
                            Solo apellido, nombre y DNI
                        </button>
                        @if ($puedeVerDatosPersonales)
                            <button type="button" wire:click="seleccionarTodos" class="btn-secondary btn-sm whitespace-nowrap">
                                Marcar todos
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="max-h-[22rem] overflow-y-auto border-t border-accent-100 bg-white p-5 sm:p-6">
                <div class="space-y-6">
                    @foreach ($camposPorGrupo as $bloque)
                        <fieldset class="min-w-0 rounded-2xl border border-accent-200 bg-accent-50/50 p-4">
                            <legend class="mb-3 w-full border-b border-accent-200 pb-2 text-[11px] font-bold uppercase tracking-[0.12em] text-primary-700">{{ $bloque['titulo'] }}</legend>
                            @if (count($bloque['items']) === 0)
                                <p class="text-sm text-neutral-500">No hay campos asignados a esta solapa en parametrización.</p>
                            @else
                                <div class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($bloque['items'] as $item)
                                        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-transparent px-2 py-1.5 text-sm text-neutral-800 transition-colors hover:bg-white/80 hover:border-accent-200/80">
                                            <input type="checkbox"
                                                   class="mt-0.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                                   wire:model.live="camposSeleccionados"
                                                   value="{{ $item['key'] }}">
                                            <span>{{ $item['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </fieldset>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-accent-200 bg-white px-5 py-4">
                <label for="subtitulo-listado-docentes" class="form-label">Subtítulo del listado (PDF)</label>
                <input id="subtitulo-listado-docentes"
                       type="text"
                       maxlength="200"
                       wire:model="subtituloListado"
                       placeholder="Opcional. Aparece en el PDF entre el encabezado y las columnas."
                       class="form-input max-w-2xl" />
                <p class="mt-1 text-xs text-neutral-500">No se guarda en la base de datos; se mantiene mientras permanezca en este módulo.</p>
            </div>

            <div class="flex flex-col gap-3 border-t border-accent-200 bg-accent-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button"
                            data-pdf-base="{{ $this->pdfUrlBase }}"
                            @class([
                                'btn-primary',
                                'pointer-events-none opacity-50' => ! $this->puedeGenerarExport(),
                            ])
                            @disabled(! $this->puedeGenerarExport())
                            x-on:click="
                                const base = $el.dataset.pdfBase;
                                if (!base || base === '#') return;
                                const input = document.getElementById('subtitulo-listado-docentes');
                                const texto = input ? String(input.value || '').trim().slice(0, 200) : '';
                                let url = base;
                                if (texto) {
                                    url += (url.includes('?') ? '&' : '?') + 'subtitulo=' + encodeURIComponent(texto);
                                }
                                $wire.set('subtituloListado', texto);
                                window.open(url, '_blank', 'noopener,noreferrer');
                            ">
                        Abrir PDF en pestaña nueva
                    </button>
                    <a class="btn-secondary @if(!$this->puedeGenerarExport()) pointer-events-none opacity-50 @endif"
                       target="_blank"
                       rel="noopener noreferrer"
                       href="{{ $this->excelUrlSeleccion }}">
                        Descargar Excel (selección)
                    </a>
                    @if (! $this->puedeGenerarExport())
                        <span class="text-sm text-neutral-500">Para PDF o Excel con selección, marque al menos un rol.</span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
