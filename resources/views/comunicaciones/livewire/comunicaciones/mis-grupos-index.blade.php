@php use App\Support\ComunicacionesRutasGestion; @endphp
<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-3">
                    <p class="se-eyebrow">Comunicaciones</p>
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Mis grupos</h2>
                        <p class="mt-2 max-w-2xl text-sm text-white/80">
                            {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($tablasOk)
                        <button type="button"
                                wire:click="abrirNuevo"
                                class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-white/50">
                            Nuevo grupo
                        </button>
                    @endif
                    <a href="{{ ComunicacionesRutasGestion::route('nuevo') }}"
                       class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                        Nuevo comunicado
                    </a>
                </div>
            </div>
        </section>

        @if (! $tablasOk)
            <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
                {{ $mensajeTabla }}
            </div>
        @else
            <div class="se-toolbar se-matriz-list-toolbar--angosta se-toolbar-pocos-campos">
                <div class="relative min-w-0 sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input type="search"
                           wire:model.live.debounce.400ms="buscar"
                           placeholder="Nombre del grupo…"
                           class="form-input w-full pl-9"
                           autocomplete="off"
                           aria-label="Buscar grupo">
                </div>
                @if ($registros)
                    <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                        {{ $registros->total() }} grupos
                    </p>
                @endif
            </div>

            <div class="se-card overflow-hidden">
                <div class="se-cierre-anual-body-wrap se-matriz-list-scroll se-grid-angosta-wrap" tabindex="0">
                    <table class="se-matriz-list-tabla se-grid-pocos-campos">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Integrantes</th>
                                <th>Cantidad</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        @if ($registros)
                            @forelse ($registros as $g)
                                <tr wire:key="com-grupo-{{ $g->id }}">
                                    <td class="font-semibold text-neutral-900">{{ $g->nombre }}</td>
                                    <td class="max-w-[28rem] text-[10px] font-normal leading-snug text-neutral-700">
                                        @php $nombres = $nombresPorGrupo[(int) $g->id] ?? []; @endphp
                                        @if ($nombres === [])
                                            —
                                        @else
                                            {{ implode(' · ', $nombres) }}
                                        @endif
                                    </td>
                                    <td class="tabular-nums">{{ (int) ($g->miembros_count ?? 0) }}</td>
                                    <td class="text-right">
                                        <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                            <button type="button"
                                                    wire:click="abrirEditar({{ (int) $g->id }})"
                                                    class="inline-flex items-center rounded-lg bg-primary-600 px-2 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-primary-700">
                                                Editar
                                            </button>
                                            <button type="button"
                                                    x-on:click="seSwalConfirmar('¿Eliminar este grupo de destinatarios?', 'Confirmar').then(ok => ok && $wire.eliminar({{ (int) $g->id }}))"
                                                    class="inline-flex items-center rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-red-700 ring-1 ring-red-200 transition hover:bg-red-50">
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-12 text-center text-sm text-neutral-500">
                                        No hay grupos en este nivel. Cree uno para reutilizar destinatarios al enviar comunicados.
                                    </td>
                                </tr>
                            @endforelse
                        @endif
                        </tbody>
                    </table>
                </div>

                @if ($registros && $registros->hasPages())
                    <div class="se-matriz-list-footer">
                        {{ $registros->links('vendor.pagination.se-compact') }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    @teleport('body')
        <div>
            @if ($modalFormAbierto)
                <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog" aria-modal="true" aria-labelledby="com-grupo-form-titulo">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalForm"></div>

                    <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),40rem)]">
                        <div class="shrink-0 border-b border-accent-200 bg-accent-50/60 px-4 py-2.5 sm:px-5 sm:py-3">
                            <p id="com-grupo-form-titulo" class="text-sm font-bold text-neutral-900">
                                {{ $editId ? 'Editar grupo' : 'Nuevo grupo' }}
                            </p>
                            <p class="mt-0.5 text-[11px] leading-snug text-neutral-600">
                                El grupo queda en este nivel y solo lo usa usted. Puede incluir estudiantes y personal (directivos, preceptores, profesores, etc.) juntos.
                            </p>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 sm:px-5 sm:py-4 space-y-4">
                            <div>
                                <label for="com-grupo-nombre" class="form-label">Nombre</label>
                                <input id="com-grupo-nombre"
                                       type="text"
                                       wire:model="nombre"
                                       maxlength="100"
                                       placeholder="Ej. Consejo de 3.º, Coordinadores…"
                                       class="form-input mt-1.5" />
                                @error('nombre') <p class="form-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <span class="form-label">Integrantes</span>
                                <p class="mt-1 text-xs text-neutral-500">Estudiantes matriculados y personal del nivel, en un mismo grupo.</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <button type="button"
                                            wire:click="abrirModalMiembros"
                                            class="inline-flex items-center justify-center rounded-xl border border-primary-500 bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                                        Elegir integrantes…
                                    </button>
                                    @if (! empty($miembrosSeleccionados))
                                        <span class="text-xs font-medium text-neutral-600">{{ count($miembrosSeleccionados) }} seleccionado(s)</span>
                                    @endif
                                </div>
                                @error('miembrosSeleccionados') <p class="form-error">{{ $message }}</p> @enderror

                                @if (! empty($miembrosSeleccionados))
                                    <div class="mt-2 rounded-lg border border-accent-200 bg-white px-2 py-1.5">
                                        <div class="max-h-32 overflow-y-auto text-[11px] leading-snug text-neutral-800">
                                            @foreach ($miembrosSeleccionados as $m)
                                                <span class="mr-2 inline-flex max-w-full items-baseline gap-0.5 align-top">
                                                    <span class="break-words">{{ $m['label'] }}@if (! empty($m['rol_label'])) <span class="text-neutral-500">({{ $m['rol_label'] }})</span>@endif</span>
                                                    <button type="button"
                                                            wire:click="removeMiembro('{{ $m['tipo'] }}', {{ (int) $m['id'] }})"
                                                            class="shrink-0 text-neutral-400 hover:text-red-600"
                                                            title="Quitar">×</button>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 border-t border-accent-200 bg-accent-50/40 px-4 py-2.5 sm:flex-row sm:justify-end sm:px-5 sm:py-3">
                            <button type="button"
                                    wire:click="cerrarModalForm"
                                    class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:w-auto">
                                Cancelar
                            </button>
                            <button type="button"
                                    wire:click="guardar"
                                    wire:loading.attr="disabled"
                                    class="inline-flex w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 sm:w-auto disabled:opacity-60">
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($modalMiembrosAbierto)
                <div class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog" aria-modal="true" aria-labelledby="com-grupo-miembros-titulo">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalMiembros"></div>

                    <div class="relative z-10 my-auto flex w-full max-w-xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),34rem)]">
                        <div class="border-b border-accent-200 bg-accent-50/60 px-4 py-2.5 sm:px-5 sm:py-3">
                            <p id="com-grupo-miembros-titulo" class="text-sm font-bold text-neutral-900">Elegir integrantes</p>
                            <p class="mt-0.5 text-[11px] leading-snug text-neutral-600">Marcá estudiantes y/o personal. El filtro no pierde las selecciones fuera de la vista.</p>
                        </div>

                        <div class="border-b border-accent-100 bg-white px-4 py-2 sm:px-5 sm:py-2.5">
                            <label for="com-grupo-miembros-filtro" class="form-label">Filtrar listado</label>
                            <input id="com-grupo-miembros-filtro"
                                   type="text"
                                   wire:model.live.debounce.400ms="modalMiembrosFiltro"
                                   placeholder="Apellido, nombre o DNI…"
                                   class="form-input mt-1.5" />
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (['todos' => 'Todos', 'estudiantes' => 'Estudiantes', 'personal' => 'Personal'] as $val => $lab)
                                    <button type="button"
                                            wire:click="cambiarVistaMiembros('{{ $val }}')"
                                            @class([
                                                'inline-flex rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                                                'border-primary-500 bg-primary-600 text-white' => $modalMiembrosVista === $val,
                                                'border-accent-200 bg-white text-primary-800 hover:bg-accent-50' => $modalMiembrosVista !== $val,
                                            ])>
                                        {{ $lab }}
                                    </button>
                                @endforeach
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="button"
                                        wire:click="modalMiembrosSeleccionarTodosVisibles"
                                        class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                                    Marcar visibles
                                </button>
                                <button type="button"
                                        wire:click="modalMiembrosQuitarVisibles"
                                        class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                                    Desmarcar visibles
                                </button>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-1 sm:px-5">
                            @forelse ($modalMiembrosLista as $m)
                                <label wire:key="com-grupo-miem-{{ $m['clave'] }}"
                                       class="flex cursor-pointer items-start gap-2 border-b border-accent-100 py-1 last:border-b-0 hover:bg-accent-50/60">
                                    <input type="checkbox"
                                           wire:model="modalMiembrosMarcados"
                                           value="{{ $m['clave'] }}"
                                           class="mt-0.5 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                    <span class="min-w-0 flex-1 text-sm leading-tight text-neutral-900">
                                        <span class="font-semibold">{{ $m['label'] }}</span>
                                        @if (! empty($m['rol_label']))
                                            <span class="ml-1 text-[11px] font-medium leading-tight text-primary-700">{{ $m['rol_label'] }}</span>
                                        @endif
                                        @if (! empty($m['dni']))
                                            <span class="ml-1 text-[11px] font-normal leading-tight text-neutral-400">DNI {{ $m['dni'] }}</span>
                                        @endif
                                    </span>
                                </label>
                            @empty
                                <p class="py-8 text-center text-sm text-neutral-500">No hay personas que coincidan con el filtro.</p>
                            @endforelse
                        </div>

                        <div class="flex flex-col gap-2 border-t border-accent-200 bg-accent-50/40 px-4 py-2.5 sm:flex-row sm:justify-end sm:px-5 sm:py-3">
                            <button type="button"
                                    wire:click="cerrarModalMiembros"
                                    class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:w-auto">
                                Cancelar
                            </button>
                            <button type="button"
                                    wire:click="aplicarModalMiembros"
                                    class="inline-flex w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 sm:w-auto">
                                Aplicar selección
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', (e) => {
            const msg = (e && e.mensaje) ? e.mensaje : 'Listo.';
            if (typeof window.seSwalExito === 'function') window.seSwalExito(msg);
        });
        $wire.on('se-swal-error', (e) => {
            const msg = (e && e.mensaje) ? e.mensaje : 'Error.';
            if (typeof window.seSwalError === 'function') window.seSwalError(msg);
        });
    </script>
    @endscript
</div>
