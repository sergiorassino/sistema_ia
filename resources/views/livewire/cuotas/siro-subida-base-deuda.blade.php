<div class="se-page max-w-7xl mx-auto"
     x-data
     x-on:siro-subida-confirmar.window="window.seSwalConfirmar($event.detail.mensaje, $event.detail.titulo ?? 'Confirmar').then(ok => ok && (window.location.href = @js(route('cuotas.siro-subida.archivo'))))">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Medios de pago · SIRO</p>
                <h1 class="flex items-center gap-2.5 text-xl font-bold tracking-tight text-white sm:text-2xl">
                    <svg class="h-6 w-6 shrink-0 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>Subida base de deuda SIRO</span>
                </h1>
                <p class="text-xs text-white/75">
                    Ciclo lectivo {{ $ano }} · 280 caracteres por registro · Suba el archivo al portal SIRO
                    <strong class="font-semibold">sin abrirlo ni guardarlo en el Bloc de notas</strong> (evita BOM UTF-8).
                </p>
            </div>
            @if ($paso === 2)
                <button type="button"
                        wire:click="volverAFiltros"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    ← Volver a filtros
                </button>
            @endif
        </div>
    </section>

    @if ($paso === 1)
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <p class="text-sm text-neutral-700">
                    Active cada filtro con la casilla y mueva ítems a la lista de seleccionados.
                    Luego pulse <strong>Aceptar</strong> para ver la grilla previa al envío.
                </p>
            </div>

            <div class="px-4 py-2 sm:px-5">
                <x-siro-shuttle-filtro
                    titulo="Cuotas"
                    tipo="cuotas"
                    :habilitado="$chkCuotas"
                    wire-habilitado="chkCuotas"
                    :disponibles="$cuotasDisponibles"
                    :seleccionados="$cuotasSeleccionados"
                    marcadas-izq-wire="marcadasCuotasIzq"
                    marcadas-der-wire="marcadasCuotasDer" />

                <x-siro-shuttle-filtro
                    titulo="Cursos"
                    tipo="cursos"
                    :habilitado="$chkCursos"
                    wire-habilitado="chkCursos"
                    :disponibles="$cursosDisponibles"
                    :seleccionados="$cursosSeleccionados"
                    marcadas-izq-wire="marcadasCursosIzq"
                    marcadas-der-wire="marcadasCursosDer" />

                <x-siro-shuttle-filtro
                    titulo="Excluir alumnos"
                    tipo="excluir"
                    :habilitado="$chkExcluirAlumnos"
                    wire-habilitado="chkExcluirAlumnos"
                    :disponibles="$alumnosExcluirDisponibles"
                    :seleccionados="$alumnosExcluirSeleccionados"
                    marcadas-izq-wire="marcadasExcluirIzq"
                    marcadas-der-wire="marcadasExcluirDer" />

                <x-siro-shuttle-filtro
                    titulo="Incluir alumnos"
                    tipo="incluir"
                    :habilitado="$chkIncluirAlumnos"
                    wire-habilitado="chkIncluirAlumnos"
                    :disponibles="$alumnosIncluirDisponibles"
                    :seleccionados="$alumnosIncluirSeleccionados"
                    marcadas-izq-wire="marcadasIncluirIzq"
                    marcadas-der-wire="marcadasIncluirDer"
                    nota="Si se habilita, solo incluirá a los alumnos seleccionados, con la cuota elegida en el filtro por cuota." />
            </div>

            <div class="border-t border-accent-200 bg-accent-50/50 px-4 py-4 sm:px-5">
                <button type="button"
                        wire:click="aceptarFiltros"
                        wire:loading.attr="disabled"
                        wire:target="aceptarFiltros"
                        class="mx-auto flex w-full max-w-md items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span wire:loading.remove wire:target="aceptarFiltros">Aceptar</span>
                    <span wire:loading wire:target="aceptarFiltros">Procesando…</span>
                </button>
            </div>
        </div>
    @else
        <div class="se-toolbar mb-4 flex flex-wrap items-center gap-2">
            @if ($cantidadSubeSiro > 0)
                <button type="button"
                        x-on:click="$dispatch('siro-subida-confirmar', {
                            mensaje: 'Se generará el archivo para subir a SIRO con {{ $cantidadSubeSiro }} registro(s) y se actualizará el contador de subida de cada cuota. ¿Continuar?',
                            titulo: 'Procesar y descargar'
                        })"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Procesar y Generar Archivo de Pagos para subir a SIRO
                </button>
            @else
                <button type="button"
                        disabled
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-neutral-300 px-4 py-2.5 text-sm font-semibold text-neutral-600 cursor-not-allowed">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Procesar y Generar Archivo de Pagos para subir a SIRO
                </button>
            @endif
        </div>

        <div class="se-card overflow-hidden">
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <table class="se-matriz-list-tabla min-w-[64rem] text-xs">
                        <thead>
                            <tr class="bg-accent-50 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                <th class="px-2 py-2 text-right w-10">#</th>
                                <th class="px-2 py-2 text-left">Apellido y nombre</th>
                                <th class="px-2 py-2 text-left">DNI</th>
                                <th class="px-2 py-2 text-left">Curso</th>
                                <th class="px-2 py-2 text-left">Cuota</th>
                                <th class="px-2 py-2 text-right">Año</th>
                                <th class="px-2 py-2 text-right">Faltapa</th>
                                <th class="px-2 py-2 text-left">Venc 1</th>
                                <th class="px-2 py-2 text-left">Venc 2</th>
                                <th class="px-2 py-2 text-left">Obs</th>
                                <th class="px-2 py-2 text-right">Legajo</th>
                                <th class="px-2 py-2 text-center">Bloqmatr</th>
                                <th class="px-2 py-2 text-center">Bloqadmi</th>
                                <th class="px-2 py-2 text-center" title="Indica si el registro se incluirá en el archivo para subir a SIRO">SIRO</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100">
                            @forelse ($filasGrilla as $i => $fila)
                                <tr wire:key="siro-fila-{{ $fila['id'] }}"
                                    @class([
                                        'hover:bg-accent-50/70',
                                        'bg-red-50/40' => ! $fila['subeSiro'],
                                    ])>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-neutral-500">{{ $i + 1 }}</td>
                                    <td class="px-2 py-1.5">{{ trim($fila['apellido'].' '.$fila['nombre']) }}</td>
                                    <td class="px-2 py-1.5 tabular-nums">{{ $fila['dni'] }}</td>
                                    <td class="px-2 py-1.5">{{ $fila['curso'] }}</td>
                                    <td class="px-2 py-1.5">{{ $fila['cuotaNombre'] }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ $fila['ano'] }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ $fila['faltapa'] }}</td>
                                    <td class="px-2 py-1.5 tabular-nums">{{ $fila['venc1'] }}</td>
                                    <td class="px-2 py-1.5 tabular-nums">{{ $fila['venc2'] }}</td>
                                    <td class="px-2 py-1.5 max-w-[10rem] truncate" title="{{ $fila['obs'] }}">{{ $fila['obs'] }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($fila['idLegajos'], 0, ',', '.') }}</td>
                                    <td class="px-2 py-1.5 text-center tabular-nums">{{ $fila['bloqmatr'] }}</td>
                                    <td class="px-2 py-1.5 text-center tabular-nums">{{ $fila['bloqadmi'] }}</td>
                                    <td class="px-2 py-1.5 text-center">
                                        @if ($fila['subeSiro'])
                                            <span class="se-pill bg-primary-100 text-primary-800 text-[10px]" title="Se incluirá en el archivo de subida a SIRO">Sí</span>
                                        @else
                                            <span class="se-pill text-[10px]" title="{{ $fila['motivoExclusion'] }}">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="px-4 py-8 text-center text-sm text-neutral-500">
                                        No hay registros con los filtros aplicados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
                <div class="flex flex-col gap-1 text-sm text-neutral-700 sm:items-end">
                    <p>
                        <span class="font-semibold text-neutral-800">Cantidad de registros para subir a SIRO:</span>
                        <span class="tabular-nums">{{ $cantidadSubeSiro }}</span>
                    </p>
                    <p>
                        <span class="font-semibold text-neutral-800">Cantidad de registros que no suben a SIRO:</span>
                        <span class="tabular-nums">{{ $cantidadNoSubeSiro }}</span>
                    </p>
                    @if (count($filasGrilla) > 0)
                        <p class="text-xs text-neutral-500 tabular-nums">
                            [1 a {{ count($filasGrilla) }} de {{ count($filasGrilla) }}]
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        $wire.on('se-swal-error', ({ mensaje, titulo }) => window.seSwalError(mensaje, titulo ?? 'Error'));
    </script>
    @endscript
</div>
