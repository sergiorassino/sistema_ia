<div>
    <div class="se-page max-w-6xl mx-auto">
        <section class="se-hero mb-6">
            <div class="se-hero-inner flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Gestión masiva · Año {{ $ano }}</p>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl uppercase truncate" title="{{ $cuota->nombre }}">
                        {{ $cuota->nombre }}
                    </h1>
                    <p class="text-sm text-white/80">Ítems e importes propios de cada curso</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <button type="button"
                            wire:click="irAImportes"
                            class="inline-flex items-center justify-center rounded-lg border border-white/40 bg-white/10 px-3 py-1.5 text-[11px] font-semibold text-white transition hover:bg-white/20">
                        Importes por curso
                    </button>
                    <a href="{{ route('cuotas.detalle.index') }}"
                       wire:navigate
                       class="inline-flex items-center justify-center rounded-lg bg-white px-3 py-1.5 text-[11px] font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                        Volver
                    </a>
                </div>
            </div>
        </section>

        @if (! $tablasListas)
            <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
                @if ($esquemaAnterior)
                    Las tablas de discriminación están en una versión anterior. Hay que volver a crearlas: cada ítem, con su importe, pertenece a un curso.
                @else
                    Faltan las tablas de discriminación. Hay que crearlas antes de usar esta pantalla.
                @endif
            </div>
        @else
            <section class="se-card p-4 sm:p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-800">Discriminación del curso</h2>
                        <p class="mt-1 text-xs text-neutral-500">Cada curso tiene sus ítems. Un servicio especial se carga solo en el curso que lo usa.</p>
                    </div>
                    @if ($items !== [] && count($cursos) > 1 && $idCursoActivo > 0)
                        <button type="button"
                                wire:click="abrirCopiar"
                                wire:loading.attr="disabled"
                                wire:target="abrirCopiar"
                                class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm transition hover:border-primary-500 hover:bg-accent-50 disabled:opacity-60">
                            Copiar a otros cursos
                        </button>
                    @endif
                </div>

                @if ($cursos === [])
                    <p class="mt-6 text-sm text-neutral-500">No hay cursos en el ciclo lectivo activo.</p>
                @else
                    <div class="mt-4 grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
                        <div x-data="{ q: '' }" class="min-w-0">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso</label>
                            <input type="search"
                                   x-model="q"
                                   placeholder="Buscar curso"
                                   class="form-input mb-2 w-full text-sm"
                                   autocomplete="off">
                            <div class="max-h-80 overflow-y-auto rounded-2xl border border-accent-200">
                                @foreach ($cursos as $curso)
                                    @php $cantidadItems = (int) ($conteoPorCurso[$curso['id']] ?? 0); @endphp
                                    <button type="button"
                                            wire:click="seleccionarCurso({{ $curso['id'] }})"
                                            wire:key="curso-det-{{ $curso['id'] }}"
                                            data-label="{{ mb_strtolower($curso['label']) }}"
                                            x-show="q === '' || $el.dataset.label.includes(q.toLowerCase())"
                                            @class([
                                                'flex w-full items-center justify-between gap-2 border-b border-accent-100 px-3 py-2 text-left text-sm last:border-b-0',
                                                'bg-primary-600 font-semibold text-white' => $idCursoActivo === $curso['id'],
                                                'text-neutral-700 hover:bg-accent-50' => $idCursoActivo !== $curso['id'],
                                            ])>
                                        <span class="min-w-0 truncate">{{ $curso['label'] }}</span>
                                        <span @class([
                                            'shrink-0 text-[10px] tabular-nums',
                                            'text-white/80' => $idCursoActivo === $curso['id'],
                                            'text-neutral-400' => $idCursoActivo !== $curso['id'],
                                        ])>{{ $cantidadItems }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="min-w-0">
                            @if ($cursoActivoLabel !== '')
                                <p class="mb-3 text-sm font-semibold text-neutral-800">{{ $cursoActivoLabel }}</p>
                            @endif

                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                                <div class="min-w-0 flex-1">
                                    <label for="nuevo-item-detalle" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Nuevo ítem de este curso</label>
                                    <input id="nuevo-item-detalle"
                                           type="text"
                                           wire:model="nuevoNombre"
                                           wire:keydown.enter.prevent="agregarItem"
                                           maxlength="180"
                                           class="form-input w-full text-sm"
                                           placeholder="Ej. Servicio especial"
                                           autocomplete="off">
                                    @error('nuevoNombre')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="button"
                                        wire:click="agregarItem"
                                        wire:loading.attr="disabled"
                                        wire:target="agregarItem"
                                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60 sm:mt-5">
                                    Agregar
                                </button>
                            </div>

                            @if ($items === [])
                                <p class="mt-6 text-sm text-neutral-500">Este curso no tiene ítems.</p>
                            @else
                                <ul class="mt-4 divide-y divide-accent-200">
                                    @foreach ($items as $item)
                                        <li wire:key="item-detalle-{{ $item['id'] }}" class="flex flex-col gap-2 py-3 lg:flex-row lg:items-start">
                                            <span class="w-8 shrink-0 pt-2 text-xs font-semibold tabular-nums text-neutral-400">{{ $item['orden'] }}</span>
                                            <div class="min-w-0 flex-1">
                                                <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Ítem</label>
                                                <input type="text"
                                                       wire:model.blur="nombres.{{ $item['id'] }}"
                                                       maxlength="180"
                                                       class="form-input w-full text-sm"
                                                       aria-label="Nombre del ítem {{ $item['orden'] }}">
                                                @error('nombres.'.$item['id'])
                                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="w-full shrink-0 sm:w-40">
                                                <label for="monto-{{ $item['id'] }}" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Importe</label>
                                                <input id="monto-{{ $item['id'] }}"
                                                       type="text"
                                                       inputmode="decimal"
                                                       wire:model.live.debounce.400ms="montos.{{ $item['id'] }}"
                                                       class="form-input w-full text-right text-sm tabular-nums"
                                                       autocomplete="off">
                                                @error('montos.'.$item['id'])
                                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex shrink-0 items-center gap-1 lg:pt-5">
                                                <button type="button"
                                                        wire:click="moverItem({{ $item['id'] }}, -1)"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-accent-200 bg-white text-primary-700 hover:bg-accent-50"
                                                        title="Subir"
                                                        aria-label="Subir ítem">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                                    </svg>
                                                </button>
                                                <button type="button"
                                                        wire:click="moverItem({{ $item['id'] }}, 1)"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-accent-200 bg-white text-primary-700 hover:bg-accent-50"
                                                        title="Bajar"
                                                        aria-label="Bajar ítem">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                    </svg>
                                                </button>
                                                <button type="button"
                                                        x-on:click="window.seSwalConfirmar(@js('¿Eliminar el ítem «'.$item['nombre'].'» de este curso?'), 'Eliminar ítem', { confirmButtonText: 'Sí, eliminar' }).then((ok) => { if (ok) $wire.eliminarItem({{ (int) $item['id'] }}); })"
                                                        class="inline-flex h-9 items-center justify-center rounded-xl border border-red-200 bg-white px-3 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                    Eliminar
                                                </button>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>

                                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                                    <span class="font-semibold text-neutral-800">
                                        Subtotal
                                        @if ($subtotal !== null)
                                            $ {{ \App\Support\Cuotas\CuotasFormato::formatearImporte($subtotal) }}
                                        @else
                                            —
                                        @endif
                                    </span>
                                    @if ($importeCuota !== null)
                                        <span class="text-neutral-500">
                                            Importe de la cuota: $ {{ \App\Support\Cuotas\CuotasFormato::formatearImporte($importeCuota) }}
                                        </span>
                                    @endif
                                </div>
                                @if ($difiereImporte)
                                    <p class="mt-2 text-xs font-medium text-amber-700">El subtotal no coincide con el importe de la cuota de este curso.</p>
                                @endif

                                <button type="button"
                                        wire:click="guardarCurso"
                                        wire:loading.attr="disabled"
                                        wire:target="guardarCurso,seleccionarCurso"
                                        class="mt-4 inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                                    Guardar importes
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
            </section>
        @endif
    </div>

    @teleport('body')
        <div>
            @if ($modalCopiar)
                <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="copiar-detalle-titulo">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarCopiar"></div>
                    <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),40rem)]">
                        <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                            <h2 id="copiar-detalle-titulo" class="text-base font-semibold text-neutral-800">Copiar discriminación</h2>
                            <p class="mt-1 text-xs text-neutral-500">
                                Desde {{ $cursoActivoLabel !== '' ? $cursoActivoLabel : 'el curso elegido' }}. Reemplaza los ítems y los importes de los cursos marcados.
                            </p>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-3">
                            <div class="mb-3 flex gap-2">
                                <button type="button" wire:click="marcarTodosDestino" class="rounded-lg border border-accent-200 px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-accent-50">Marcar todos</button>
                                <button type="button" wire:click="limpiarDestino" class="rounded-lg border border-accent-200 px-3 py-1.5 text-xs font-semibold text-neutral-600 hover:bg-accent-50">Quitar todos</button>
                            </div>
                            <ul class="space-y-1">
                                @foreach ($cursos as $curso)
                                    @if ($curso['id'] !== $idCursoActivo)
                                        <li wire:key="dest-{{ $curso['id'] }}">
                                            <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50">
                                                <input type="checkbox" value="{{ $curso['id'] }}" wire:model="cursosDestino" class="rounded border-accent-200 text-primary-600 focus:ring-primary-500">
                                                <span class="text-sm text-neutral-800">{{ $curso['label'] }}</span>
                                            </label>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                            @error('cursosDestino')
                                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex shrink-0 justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-3">
                            <button type="button" wire:click="cerrarCopiar" class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-white">
                                Cancelar
                            </button>
                            <button type="button"
                                    wire:click="copiarACursos"
                                    wire:loading.attr="disabled"
                                    wire:target="copiarACursos"
                                    class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60">
                                Copiar discriminación
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
        $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
    </script>
    @endscript
</div>
