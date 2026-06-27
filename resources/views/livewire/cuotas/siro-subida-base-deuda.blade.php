<div @class([
        'min-w-0',
        'se-cierre-anual-fill se-matriz-list-fill' => $paso === 2,
        'se-page max-w-7xl mx-auto' => $paso === 1,
    ])
     x-data
     x-on:siro-subida-confirmar.window="window.seSwalConfirmar($event.detail.mensaje, $event.detail.titulo ?? 'Confirmar').then(ok => ok && (window.location.href = @js(route('cuotas.siro-subida.archivo'))))">
    @if ($paso === 1)
        <section class="se-hero mb-4">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-0.5">
                    <p class="se-eyebrow">Medios de pago · SIRO</p>
                    <h1 class="flex items-center gap-2.5 text-xl font-bold tracking-tight text-white sm:text-2xl">
                        <svg class="h-6 w-6 shrink-0 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <span>Subida base de deuda SIRO</span>
                    </h1>
                    <p class="text-xs text-white/75">
                        Ciclo lectivo {{ $ano }} · 280 caracteres por registro · Suba el archivo al portal SIRO
                        <strong class="font-semibold">sin abrirlo ni guardarlo en el Bloc de notas</strong> (evita BOM UTF-8).
                    </p>
                </div>
            </div>
        </section>

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
        @include('livewire.cuotas.partials.siro-subida-paso-grilla', [
            'titulo' => 'Subida base de deuda SIRO',
            'subtitulo' => "Ciclo lectivo {$ano} · 280 caracteres por registro · Vista previa del archivo",
            'confirmEvent' => 'siro-subida-confirmar',
            'confirmMensaje' => "Se generará el archivo para subir a SIRO con {$cantidadSubeSiro} registro(s) y se actualizará el contador de subida de cada cuota. ¿Continuar?",
            'filasGrilla' => $filasGrilla,
            'cantidadSubeSiro' => $cantidadSubeSiro,
            'cantidadNoSubeSiro' => $cantidadNoSubeSiro,
            'filaKeyPrefix' => 'siro-fila',
        ])
    @endif

    @script
    <script>
        $wire.on('se-swal-error', ({ mensaje, titulo }) => window.seSwalError(mensaje, titulo ?? 'Error'));
    </script>
    @endscript
</div>
