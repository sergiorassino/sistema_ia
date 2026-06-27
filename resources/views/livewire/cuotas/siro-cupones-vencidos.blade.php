<div @class([
        'min-w-0',
        'se-cierre-anual-fill se-matriz-list-fill' => $paso === 2,
        'se-page max-w-7xl mx-auto' => $paso === 1,
    ])
     x-data
     x-on:siro-cupones-vencidos-confirmar.window="window.seSwalConfirmar($event.detail.mensaje, $event.detail.titulo ?? 'Confirmar').then(ok => ok && (window.location.href = @js(route('cuotas.siro-cupones-vencidos.archivo'))))">
    @if ($paso === 1)
        <section class="se-hero mb-4">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-0.5">
                    <p class="se-eyebrow">Medios de pago · SIRO</p>
                    <h1 class="flex items-center gap-2.5 text-xl font-bold tracking-tight text-white sm:text-2xl">
                        <svg class="h-6 w-6 shrink-0 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Actualizar cupones vencidos y subir</span>
                    </h1>
                    <p class="text-xs text-white/75">
                        Ciclo lectivo {{ $ano }} · Cupones con 2.º vencimiento vencido · 280 caracteres por registro ·
                        Suba el archivo al portal SIRO
                        <strong class="font-semibold">sin abrirlo ni guardarlo en el Bloc de notas</strong> (evita BOM UTF-8).
                    </p>
                </div>
            </div>
        </section>

        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <p class="text-sm text-neutral-700">
                    Indique la fecha de actualización de vencimiento, active cada filtro con la casilla y mueva ítems a la lista de seleccionados.
                    Luego pulse <strong>Aceptar</strong> para ver la grilla previa al envío.
                </p>
            </div>

            <div class="border-b border-accent-200 px-4 py-4 sm:px-5">
                <div class="mx-auto max-w-xs">
                    <label for="fechaActualizarAl" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500/90">
                        Actualizar al
                    </label>
                    <input id="fechaActualizarAl"
                           type="date"
                           wire:model="fechaActualizarAl"
                           class="w-full rounded-xl border border-accent-200 bg-white px-3 py-2.5 text-sm text-neutral-800 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                    @error('fechaActualizarAl')
                        <p class="mt-1 text-center text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
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
        @include('livewire.cuotas.partials.siro-subida-paso-grilla', [
            'titulo' => 'Actualizar cupones vencidos y subir',
            'subtitulo' => "Ciclo lectivo {$ano} · Cupones con 2.º vencimiento vencido · Vista previa del archivo",
            'confirmEvent' => 'siro-cupones-vencidos-confirmar',
            'confirmMensaje' => "Se actualizará el vencimiento de {$cantidadSubeSiro} cupón(es) a la fecha indicada, se generará el archivo para subir a SIRO y se actualizará el contador de subida de cada cuota. ¿Continuar?",
            'filasGrilla' => $filasGrilla,
            'cantidadSubeSiro' => $cantidadSubeSiro,
            'cantidadNoSubeSiro' => $cantidadNoSubeSiro,
            'filaKeyPrefix' => 'siro-cv-fila',
        ])
    @endif

    @script
    <script>
        $wire.on('se-swal-error', ({ mensaje, titulo }) => window.seSwalError(mensaje, titulo ?? 'Error'));
    </script>
    @endscript
</div>
