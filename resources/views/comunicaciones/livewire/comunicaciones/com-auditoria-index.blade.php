<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Comunicación institucional</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Auditoría de bandejas</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <a href="{{ route('comunicaciones.index') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver a bandeja
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="se-section-title">Filtros</p>
            <p class="mt-1 text-sm text-neutral-600">
                Registros de borrado de mensajes y de marcas leído / no leído realizadas por estudiantes,
                profesores o personal en sus bandejas de comunicación.
            </p>
        </div>

        <div class="space-y-6 border-t border-accent-100 bg-accent-50/30 p-5 sm:p-6">
            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label for="aud-categoria" class="form-label">Tipo de usuario</label>
                    <select id="aud-categoria" wire:model.live="filtroCategoria" class="form-select mt-1.5">
                        <option value="todos">Todos</option>
                        <option value="estudiante">Estudiante / familia</option>
                        <option value="profesor">Profesor/a</option>
                        <option value="personal">Personal</option>
                    </select>
                </div>
                <div>
                    <label for="aud-accion" class="form-label">Acción</label>
                    <select id="aud-accion" wire:model.live="filtroAccion" class="form-select mt-1.5">
                        <option value="todos">Todas</option>
                        <option value="borrar">Borrados</option>
                        <option value="leido">Marcó como leído</option>
                        <option value="no_leido">Marcó como no leído</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <label for="aud-usuario" class="form-label">Buscar usuario</label>
                    <div class="relative mt-1.5">
                        <input id="aud-usuario"
                               type="text"
                               wire:model.live.debounce.250ms="usuarioSearch"
                               placeholder="Apellido, nombre o DNI…"
                               class="form-input" />
                        @if (! empty($usuarioResults))
                            <div class="absolute z-20 mt-2 max-h-48 w-full overflow-y-auto rounded-2xl border border-accent-200 bg-white shadow-lg">
                                @foreach ($usuarioResults as $u)
                                    <button type="button"
                                            wire:click="selectUsuario(@js($u['tipo']), {{ (int) $u['id'] }}, @js($u['label']))"
                                            class="block w-full border-b border-accent-100 px-3 py-2.5 text-left text-sm transition last:border-b-0 hover:bg-accent-50">
                                        <span class="font-semibold text-neutral-900">{{ $u['label'] }}</span>
                                        <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-neutral-400">
                                            @switch($u['categoria'] ?? '')
                                                @case('estudiante') Estudiante @break
                                                @case('profesor') Profesor/a @break
                                                @case('personal') Personal @break
                                                @default Usuario
                                            @endswitch
                                        </span>
                                        @if (! empty($u['dni']))
                                            <span class="ml-1 text-xs text-neutral-400">DNI {{ $u['dni'] }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-500">
                        <span>
                            <span class="font-semibold text-neutral-700">Usuario:</span>
                            @if ($idProfesorObjetivo || $idLegajoObjetivo)
                                {{ $usuarioObjetivoLabel }}
                            @else
                                Todos
                            @endif
                        </span>
                        @if ($idProfesorObjetivo || $idLegajoObjetivo)
                            <button type="button"
                                    wire:click="limpiarUsuario"
                                    class="font-semibold text-primary-700 underline-offset-2 hover:underline">
                                Quitar filtro
                            </button>
                        @endif
                    </div>
                </div>
                <div>
                    <label for="aud-periodo" class="form-label">Año lectivo</label>
                    <select id="aud-periodo" wire:model.live="periodo" class="form-select mt-1.5">
                        <option value="actual">Actual</option>
                        <option value="historico">Toda la historia</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="border-t border-accent-200">
            @if ($registros->isEmpty())
                <div class="px-5 py-12 text-center text-sm text-neutral-500">
                    No hay registros de auditoría con los filtros seleccionados.
                </div>
            @else
                <div class="w-full overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-accent-200 bg-accent-50 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="px-4 py-3">Fecha y hora</th>
                                <th class="px-4 py-3">Usuario</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Acción</th>
                                <th class="px-4 py-3">Portal</th>
                                <th class="px-4 py-3">Remitente</th>
                                <th class="px-4 py-3">Destinatario</th>
                                <th class="px-4 py-3">Comunicado</th>
                                <th class="px-4 py-3">Mensaje</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100">
                            @foreach ($registros as $r)
                                <tr class="hover:bg-accent-50/80" wire:key="aud-{{ $r->id }}">
                                    <td class="whitespace-nowrap px-4 py-3 text-neutral-700">
                                        {{ $r->created_at?->format('d/m/Y H:i:s') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold text-neutral-900">{{ $r->nombre_actor_snapshot }}</span>
                                        @if ($r->dni_actor_snapshot)
                                            <span class="block text-xs text-neutral-400">DNI {{ $r->dni_actor_snapshot }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-neutral-600">
                                        {{ \App\Models\ComAuditoria::etiquetaCategoria($r->actor_categoria) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'se-pill text-[10px]',
                                            'bg-red-50 text-red-800' => in_array($r->accion, ['borrar_mensaje', 'borrar_hilo'], true),
                                            'bg-primary-50 text-primary-800' => $r->accion === 'marcar_leido',
                                            'bg-amber-50 text-amber-900' => $r->accion === 'marcar_no_leido',
                                        ])>
                                            {{ \App\Models\ComAuditoria::etiquetaAccion($r->accion) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-neutral-600">
                                        {{ \App\Models\ComAuditoria::etiquetaPortal($r->portal) }}
                                    </td>
                                    <td class="max-w-[10rem] px-4 py-3 text-neutral-600">
                                        @if (! empty($r->mensaje_remitente_snapshot))
                                            <span class="line-clamp-2 text-xs" title="{{ $r->mensaje_remitente_snapshot }}">
                                                {{ $r->mensaje_remitente_snapshot }}
                                            </span>
                                        @else
                                            <span class="text-xs text-neutral-400">—</span>
                                        @endif
                                    </td>
                                    <td class="max-w-[10rem] px-4 py-3 text-neutral-600">
                                        @if (! empty($r->mensaje_destinatario_snapshot))
                                            <span class="line-clamp-2 text-xs" title="{{ $r->mensaje_destinatario_snapshot }}">
                                                {{ $r->mensaje_destinatario_snapshot }}
                                            </span>
                                        @else
                                            <span class="text-xs text-neutral-400">—</span>
                                        @endif
                                    </td>
                                    <td class="max-w-[12rem] px-4 py-3">
                                        <span class="line-clamp-2 font-medium text-neutral-800" title="{{ $r->hilo_asunto_snapshot }}">
                                            {{ $r->hilo_asunto_snapshot }}
                                        </span>
                                        <span class="text-xs text-neutral-400">Hilo #{{ $r->id_hilo }}</span>
                                    </td>
                                    <td class="max-w-[14rem] px-4 py-3 text-neutral-600">
                                        @if ($r->id_mensaje)
                                            <span class="text-xs text-neutral-400">#{{ $r->id_mensaje }}</span>
                                            @if ($r->mensaje_contenido_snapshot)
                                                <p class="mt-0.5 line-clamp-2 text-xs" title="{{ $r->mensaje_contenido_snapshot }}">
                                                    {{ $r->mensaje_contenido_snapshot }}
                                                </p>
                                            @endif
                                        @else
                                            <span class="text-xs text-neutral-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-accent-200 px-5 py-3">
                    {{ $registros->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
