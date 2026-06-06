<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Listados</p>
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                            Alumnos por curso
                        </h2>
                    </div>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Cursos en el colegio</span>
                    <span class="text-xl font-bold tabular-nums">{{ $cursos->count() }}</span>
                </span>
                @if ($cursos->isNotEmpty())
                    <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                        <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Seleccionados para PDF</span>
                        <span class="text-xl font-bold tabular-nums">{{ count($cursosElegidos) }}</span>
                    </span>
                    <a href="{{ $this->excelUrlCompleto }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       @class([
                           'inline-flex items-center justify-center rounded-2xl border px-4 py-3 text-sm font-semibold shadow-sm transition-colors',
                           'border-white/20 bg-white text-primary-700 hover:bg-accent-50' => $this->puedeExportarExcelCompleto(),
                           'pointer-events-none border-white/10 bg-white/20 text-white/50' => ! $this->puedeExportarExcelCompleto(),
                       ])
                       title="Todos los cursos del ciclo, todas las columnas del legajo (solapas). Archivo Estudiantes{{ schoolCtx()->terlecAno() }}.xlsx">
                        Exportar Excel
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if ($cursos->isEmpty())
        <div class="se-card p-8">
            <div class="flex flex-col items-center justify-center gap-4 text-center sm:flex-row sm:text-left">
                <div class="se-icon-badge h-14 w-14">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <div class="max-w-md">
                    <p class="text-sm font-semibold text-neutral-800">Sin cursos en este contexto</p>
                    <p class="mt-1 text-sm text-neutral-600">No hay cursos cargados para el nivel y año lectivo activos.</p>
                </div>
            </div>
        </div>
    @else
        <div class="se-toolbar">
            <div class="min-w-0 flex-1 space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-neutral-500">Condición de matrícula en el PDF</p>
                <div class="flex flex-wrap gap-2">
                    <label @class([
                        'inline-flex cursor-pointer items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2',
                        'border-primary-500 bg-primary-600 text-white' => $filtroCondicion === 'regulares',
                        'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' => $filtroCondicion !== 'regulares',
                    ])>
                        <input type="radio" name="filtro-condicion" value="regulares" class="sr-only" wire:model.live="filtroCondicion">
                        Regulares
                    </label>
                    <label @class([
                        'inline-flex cursor-pointer items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2',
                        'border-primary-500 bg-primary-600 text-white' => $filtroCondicion === 'salidos',
                        'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' => $filtroCondicion !== 'salidos',
                    ])>
                        <input type="radio" name="filtro-condicion" value="salidos" class="sr-only" wire:model.live="filtroCondicion">
                        Salidos
                    </label>
                    <label @class([
                        'inline-flex cursor-pointer items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2',
                        'border-primary-500 bg-primary-600 text-white' => $filtroCondicion === 'todos',
                        'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' => $filtroCondicion !== 'todos',
                    ])>
                        <input type="radio" name="filtro-condicion" value="todos" class="sr-only" wire:model.live="filtroCondicion">
                        Todas
                    </label>
                </div>
            </div>
        </div>

        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-neutral-700">
                        Elija los cursos que incluirá en el PDF y el Excel de esta selección.
                    </p>
                    <span class="se-pill shrink-0 tabular-nums">
                        {{ $cantidadSeleccionados }} de {{ $cursos->count() }} seleccionados
                    </span>
                </div>
            </div>

            <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
                <label for="filtro-cursos-listado" class="form-label">Buscar curso</label>
                <input id="filtro-cursos-listado"
                       type="search"
                       wire:model.live.debounce.300ms="filtroCursos"
                       placeholder="Nivel o nombre del curso…"
                       class="form-input max-w-xl" />
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button"
                            wire:click="seleccionarTodosCursos"
                            class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                        Todos
                    </button>
                    <button type="button"
                            wire:click="quitarTodosCursos"
                            class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                        Ninguno
                    </button>
                </div>
            </div>

            @if ($cursosPorNivel === [])
                <div class="px-4 py-6 text-sm text-neutral-600 sm:px-5">
                    @if (trim($filtroCursos) !== '')
                        Ningún curso coincide con la búsqueda.
                    @else
                        No hay cursos para mostrar.
                    @endif
                </div>
            @else
                @php
                    $cantidadNiveles = count($cursosPorNivel);
                @endphp
                <div class="max-h-[min(65dvh,36rem)] overflow-y-auto px-3 py-2 sm:px-4">
                    <div @class([
                        'mx-auto w-full max-w-md' => $cantidadNiveles === 1,
                        'overflow-x-auto' => $cantidadNiveles > 1,
                    ])>
                        <div @class([
                            'grid w-full gap-3' => $cantidadNiveles === 1,
                            'grid min-w-full gap-3' => $cantidadNiveles > 1,
                        ])
                             @if ($cantidadNiveles > 1)
                                 style="grid-template-columns: repeat({{ $cantidadNiveles }}, minmax(12.5rem, 1fr));"
                             @endif>
                            @foreach ($cursosPorNivel as $bloqueNivel)
                                <section wire:key="listado-nivel-{{ $bloqueNivel['idNivel'] }}"
                                         class="flex min-w-0 flex-col rounded-lg border border-accent-200/90 bg-accent-50/20">
                                    <div class="border-b border-accent-200/80 px-2.5 py-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide leading-snug text-primary-800">
                                            {{ $bloqueNivel['nivelNombre'] }}
                                            <span class="font-normal text-neutral-500 tabular-nums">
                                                ({{ $bloqueNivel['seleccionados'] }}/{{ $bloqueNivel['total'] }})
                                            </span>
                                        </p>
                                        <div class="mt-1.5 flex flex-col gap-1">
                                            <button type="button"
                                                    wire:click="marcarNivel({{ $bloqueNivel['idNivel'] }})"
                                                    class="inline-flex w-full justify-center rounded-md border border-accent-200 bg-white px-2 py-1 text-xs font-semibold leading-snug text-primary-800 hover:bg-accent-50">
                                                Marcar nivel
                                            </button>
                                            <button type="button"
                                                    wire:click="quitarNivel({{ $bloqueNivel['idNivel'] }})"
                                                    class="inline-flex w-full justify-center rounded-md border border-accent-200 bg-white px-2 py-1 text-xs font-semibold leading-snug text-neutral-600 hover:bg-accent-50">
                                                Quitar nivel
                                            </button>
                                        </div>
                                    </div>
                                    <ul class="min-h-0 flex-1 list-none divide-y divide-accent-100/80 px-2 py-1">
                                        @foreach ($bloqueNivel['cursos'] as $cursoItem)
                                            <li wire:key="listado-curso-{{ $cursoItem['id'] }}">
                                                <label class="flex cursor-pointer items-center gap-2 py-1.5 transition-colors hover:bg-accent-50/70">
                                                    <input type="checkbox"
                                                           wire:model.live="cursosElegidos"
                                                           value="{{ $cursoItem['id'] }}"
                                                           class="h-4 w-4 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                                    <span class="min-w-0 flex-1 text-sm leading-normal text-neutral-800">
                                                        {{ $cursoItem['etiqueta'] }}
                                                    </span>
                                                </label>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if ($cantidadSeleccionados > 0)
                <div class="border-t border-accent-200 bg-accent-50/40 px-4 py-3 sm:px-5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Seleccionados para PDF</p>
                    <div class="mt-2 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                        @foreach ($cursosSeleccionadosResumen as $chip)
                            <span class="inline-flex max-w-full items-center gap-1 rounded-lg border border-primary-200 bg-white px-2 py-1 text-xs font-medium leading-snug text-neutral-800">
                                <span class="truncate">{{ $chip['label'] }}</span>
                                <button type="button"
                                        wire:click="quitarCurso({{ $chip['id'] }})"
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
                        <p class="se-section-title">Columnas del PDF</p>
                        <p class="mt-1 text-sm text-neutral-600">Un bloque por cada solapa (orden <span class="font-mono">solapas_legajo.orden</span>, título <span class="font-mono">solapas_legajo.nombre</span>). Dentro: columnas de <span class="font-mono">campos_legajo</span> visibles para listado en esa solapa. Slug <span class="font-mono">alumno</span>: incluye apellido, nombre y DNI. Matrícula y condición de cursada no forman parte del legajo y no se eligen aquí.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="seleccionarSoloDefecto" class="btn-secondary btn-sm whitespace-nowrap">
                            Solo apellido, nombre y DNI
                        </button>
                        <button type="button" wire:click="seleccionarTodos" class="btn-secondary btn-sm whitespace-nowrap">
                            Marcar todos
                        </button>
                    </div>
                </div>
            </div>

            <div class="border-t border-accent-100 bg-accent-50/40 px-5 py-4">
                <div class="flex flex-col gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-neutral-500">Plantillas guardadas</p>
                        <p class="mt-1 text-sm text-neutral-600">Elija una plantilla para cargar sus columnas y condición. Puede actualizar o eliminar la seleccionada, o guardar la configuración actual como plantilla nueva.</p>
                    </div>

                    @if (count($plantillas) > 0)
                        <div class="se-toolbar-pocos-campos flex flex-wrap gap-2">
                            <button type="button"
                                    wire:click="actualizarPlantilla"
                                    @class([
                                        'btn-secondary btn-sm whitespace-nowrap',
                                        'pointer-events-none opacity-50' => $plantillaSeleccionada === null,
                                    ])
                                    @disabled($plantillaSeleccionada === null)>
                                Actualizar seleccionada
                            </button>
                            <button type="button"
                                    x-on:click="seSwalConfirmar('¿Eliminar la plantilla seleccionada?', 'Eliminar plantilla').then(ok => ok && $wire.eliminarPlantillaSeleccionada())"
                                    @class([
                                        'btn-secondary btn-sm whitespace-nowrap text-red-700 hover:border-red-200 hover:bg-red-50',
                                        'pointer-events-none opacity-50' => $plantillaSeleccionada === null,
                                    ])
                                    @disabled($plantillaSeleccionada === null)>
                                Eliminar seleccionada
                            </button>
                        </div>

                        <div class="w-full overflow-x-auto rounded-2xl border border-accent-200 bg-white">
                            <table class="min-w-full border-collapse text-sm">
                                <thead class="bg-accent-50">
                                    <tr>
                                        <th class="table-header w-12 text-center" scope="col">
                                            <span class="sr-only">Seleccionar</span>
                                        </th>
                                        <th class="table-header min-w-[10rem]" scope="col">Nombre</th>
                                        <th class="table-header w-28" scope="col">Condición</th>
                                        <th class="table-header min-w-[14rem]" scope="col">Campos</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-accent-200">
                                    @foreach ($plantillas as $plantilla)
                                        <tr wire:key="plantilla-{{ $plantilla['id'] }}"
                                            wire:click="$set('plantillaSeleccionada', {{ $plantilla['id'] }})"
                                            @class([
                                                'cursor-pointer transition-colors hover:bg-accent-50/70',
                                                'bg-primary-50/60' => $plantillaSeleccionada === $plantilla['id'],
                                            ])>
                                            <td class="table-cell text-center" wire:click.stop>
                                                <input type="radio"
                                                       name="plantilla-listado"
                                                       value="{{ $plantilla['id'] }}"
                                                       wire:model.live="plantillaSeleccionada"
                                                       class="h-4 w-4 border-accent-300 text-primary-600 focus:ring-primary-500"
                                                       aria-label="Usar plantilla {{ $plantilla['nombre'] }}" />
                                            </td>
                                            <td class="table-cell font-semibold text-neutral-900">
                                                {{ $plantilla['nombre'] }}
                                            </td>
                                            <td class="table-cell text-neutral-700">
                                                <span class="se-pill">{{ $plantilla['condicionEtiqueta'] }}</span>
                                            </td>
                                            <td class="table-cell text-neutral-600">
                                                @if ($plantilla['camposCantidad'] > 0)
                                                    {{ implode(', ', $plantilla['camposEtiquetas']) }}
                                                @else
                                                    <span class="text-neutral-400">Sin columnas</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-neutral-500">Todavía no hay plantillas guardadas para este nivel.</p>
                    @endif

                    <div class="flex flex-col gap-2 border-t border-accent-200/80 pt-4 sm:flex-row sm:items-end">
                        <div class="min-w-0 flex-1">
                            <label for="nombre-plantilla-listado" class="form-label">Nombre de la plantilla</label>
                            <input id="nombre-plantilla-listado"
                                   type="text"
                                   maxlength="120"
                                   wire:model="nombrePlantilla"
                                   placeholder="Ej.: Padrón con sexo y fecha de nac."
                                   class="form-input max-w-md" />
                            @error('nombrePlantilla')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="guardarComoPlantilla" class="btn-primary btn-sm whitespace-nowrap">
                                Guardar como nueva
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="max-h-[22rem] overflow-y-auto border-t border-accent-100 bg-white p-5 sm:p-6">
                <div class="space-y-6">
                    @foreach ($camposPorGrupo as $bloque)
                        <fieldset class="min-w-0 rounded-2xl border border-accent-200 bg-accent-50/50 p-4">
                            <legend class="mb-3 w-full border-b border-accent-200 pb-2 text-[11px] font-bold uppercase tracking-[0.12em] text-primary-700">{{ $bloque['titulo'] }}</legend>
                            @if (count($bloque['items']) === 0)
                                <p class="text-sm text-neutral-500">No hay campos asignados a esta solapa en parametrización (<span class="font-mono">campos_legajo</span>).</p>
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
                <label for="subtitulo-listado-curso" class="form-label">Subtítulo del listado (PDF)</label>
                <input id="subtitulo-listado-curso"
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
                                'pointer-events-none opacity-50' => ! $this->puedeGenerarPdf(),
                            ])
                            @disabled(! $this->puedeGenerarPdf())
                            x-on:click="
                                const base = $el.dataset.pdfBase;
                                if (!base || base === '#') return;
                                const input = document.getElementById('subtitulo-listado-curso');
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
                    <a class="btn-secondary @if(!$this->puedeGenerarPdf()) pointer-events-none opacity-50 @endif"
                       target="_blank"
                       rel="noopener noreferrer"
                       href="{{ $this->excelUrlSeleccion }}">
                        Descargar Excel (selección)
                    </a>
                    @if (!$this->puedeGenerarPdf())
                        <span class="text-sm text-neutral-500">Para PDF o Excel con selección, marque al menos un curso.</span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            $wire.on('se-swal-exito', (event) => {
                const mensaje = mensajeDeEvento(event, 'Operación realizada correctamente.');
                if (typeof window.seSwalExito === 'function') {
                    window.seSwalExito(mensaje);
                }
            });

            $wire.on('se-swal-error', (event) => {
                const mensaje = mensajeDeEvento(event, 'No se pudo completar la operación.');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                }
            });
        })();
    </script>
    @endscript
</div>
