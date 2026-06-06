<div class="se-page max-w-5xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Configuración</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Canales de comunicación</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Quién puede iniciar y responder comunicados, y por qué medios.
                    @if ($idNivel > 0 && $nivelNombre !== '')
                        <span class="mt-1 block font-medium text-white">Nivel: {{ $nivelNombre }}</span>
                    @endif
                </p>
            </div>
        </div>
    </section>

    @if ($idNivel <= 0)
        <div class="se-soft-card border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Seleccione un <strong>nivel activo</strong> en el menú de secretaría para configurar los canales de ese nivel.
        </div>
    @endif

    @if (session('success'))
        <div class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="se-toolbar flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-neutral-600">
            Canales del nivel activo. Defina pares <span class="font-medium text-neutral-800">emisor → receptor</span>; primario y secundario pueden diferir.
        </p>
        @if (! $mostrandoFormNuevo && $idNivel > 0)
            <button type="button" wire:click="abrirFormNuevo" class="btn-primary btn-sm shrink-0">
                Agregar canal
            </button>
        @endif
    </div>

    @if ($mostrandoFormNuevo)
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50 px-4 py-3">
                <h3 class="text-sm font-semibold text-neutral-800">Nuevo canal</h3>
                <p class="mt-0.5 text-xs text-neutral-500">Solo combinaciones que aún no existan para este nivel.</p>
            </div>
            <div class="space-y-4 p-4 sm:p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="nuevo-rol-emisor" class="mb-1.5 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">De (emisor)</label>
                        <select id="nuevo-rol-emisor" wire:model.live="nuevoRolEmisor"
                                class="w-full rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30">
                            @foreach ($etiquetas as $clave => $etiqueta)
                                <option value="{{ $clave }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        @error('nuevoRolEmisor')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="nuevo-rol-receptor" class="mb-1.5 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Para (receptor)</label>
                        <select id="nuevo-rol-receptor" wire:model.live="nuevoRolReceptor"
                                class="w-full rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30">
                            @foreach ($etiquetas as $clave => $etiqueta)
                                <option value="{{ $clave }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        @error('nuevoRolReceptor')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex flex-wrap gap-6">
                    <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-neutral-800">
                        <input type="checkbox" wire:model="nuevoPuedeIniciar" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                        Puede iniciar
                    </label>
                    <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-neutral-800">
                        <input type="checkbox" wire:model="nuevoPuedeResponder" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                        Puede responder
                    </label>
                    <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-neutral-800">
                        <input type="checkbox" wire:model="nuevoActivo" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                        Activo
                    </label>
                </div>

                <div>
                    <span class="mb-2 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Medios permitidos</span>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($mediosDisponibles as $medio)
                            <label class="flex cursor-pointer select-none items-center gap-1.5 text-sm text-neutral-800">
                                <input type="checkbox"
                                       wire:click="toggleMedioNuevo('{{ $medio }}')"
                                       @checked(in_array($medio, $nuevoMedios))
                                       class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                {{ ucfirst($medio) }}
                            </label>
                        @endforeach
                    </div>
                    @error('nuevoMedios')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-2 border-t border-accent-100 pt-4">
                    <button type="button" wire:click="guardarNuevo" class="btn-primary btn-sm">Crear canal</button>
                    <button type="button" wire:click="cancelarFormNuevo" class="btn-secondary btn-sm">Cancelar</button>
                </div>
            </div>
        </div>
    @endif

    <div class="se-card overflow-hidden">
        <div class="w-full overflow-x-auto">
            <div class="flex justify-start">
                <table class="min-w-[720px] divide-y divide-accent-200 text-sm sm:min-w-full">
                    <thead class="bg-accent-50">
                        <tr>
                            <th class="table-header">De</th>
                            <th class="table-header">Para</th>
                            <th class="table-header text-center">Inicia</th>
                            <th class="table-header text-center">Responde</th>
                            <th class="table-header">Medios</th>
                            <th class="table-header text-center">Activo</th>
                            <th class="table-header w-52 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-200 bg-white">
                        @forelse ($canales as $canal)
                            <tr wire:key="com-canal-{{ $canal->id }}" @class([
                                'bg-amber-50/50' => $editandoId === $canal->id,
                                'hover:bg-accent-50/50' => $editandoId !== $canal->id,
                            ])>
                                <td class="table-cell font-semibold text-neutral-900">{{ $etiquetas[$canal->rol_emisor] ?? $canal->rol_emisor }}</td>
                                <td class="table-cell text-neutral-700">{{ $etiquetas[$canal->rol_receptor] ?? $canal->rol_receptor }}</td>

                                @if ($editandoId === $canal->id)
                                    <td class="table-cell text-center">
                                        <input type="checkbox" wire:model="editPuedeIniciar" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    </td>
                                    <td class="table-cell text-center">
                                        <input type="checkbox" wire:model="editPuedeResponder" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    </td>
                                    <td class="table-cell">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($mediosDisponibles as $medio)
                                                <label class="flex cursor-pointer select-none items-center gap-1.5 text-xs">
                                                    <input type="checkbox"
                                                           wire:click="toggleMedio('{{ $medio }}')"
                                                           @checked(in_array($medio, $editMedios))
                                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                                    {{ ucfirst($medio) }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="table-cell text-center">
                                        <input type="checkbox" wire:model="editActivo" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    </td>
                                    <td class="table-cell">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button" wire:click="guardar" class="btn-primary btn-sm">Guardar</button>
                                            <button type="button" wire:click="cancelarEdicion" class="btn-secondary btn-sm">Cancelar</button>
                                        </div>
                                    </td>
                                @else
                                    <td class="table-cell text-center">
                                        @if ($canal->puede_iniciar)
                                            <svg class="mx-auto h-4 w-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        @else
                                            <svg class="mx-auto h-4 w-4 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        @endif
                                    </td>
                                    <td class="table-cell text-center">
                                        @if ($canal->puede_responder)
                                            <svg class="mx-auto h-4 w-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        @else
                                            <svg class="mx-auto h-4 w-4 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        @endif
                                    </td>
                                    <td class="table-cell">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($canal->medios_permitidos ?? [] as $medio)
                                                <span class="se-pill text-[10px]">{{ ucfirst($medio) }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="table-cell text-center">
                                        @if ($canal->activo)
                                            <span class="inline-block h-2 w-2 rounded-full bg-primary-500"></span>
                                        @else
                                            <span class="inline-block h-2 w-2 rounded-full bg-neutral-300"></span>
                                        @endif
                                    </td>
                                    <td class="table-cell text-right">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            <button type="button" wire:click="iniciarEdicion({{ $canal->id }})"
                                                    class="btn-secondary btn-sm shrink-0">
                                                Editar
                                            </button>
                                            <button type="button" wire:click="confirmarEliminar({{ $canal->id }})"
                                                    class="btn-danger btn-sm shrink-0">
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="table-cell py-8 text-center text-neutral-500">
                                    @if ($idNivel <= 0)
                                        Elija un nivel en el contexto de secretaría.
                                    @else
                                        No hay canales configurados para este nivel. Use «Agregar canal» o ejecute la migración de datos.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="text-xs text-neutral-500">
        Los cambios aplican solo al nivel activo. Al cambiar de nivel en secretaría verá la parametrización correspondiente.
    </p>

    @if ($showConfirmEliminar)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-neutral-900/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
                            <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="mb-1 text-base font-semibold text-neutral-800">Eliminar canal</h3>
                            <p class="text-sm text-neutral-600">
                                ¿Eliminar el canal <span class="font-semibold text-neutral-800">{{ $eliminarEtiqueta }}</span>?
                                No podrá usarse para nuevos comunicados; los hilos ya existentes no se borran.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/70 px-6 py-4">
                    <button type="button" wire:click="cerrarConfirmEliminar" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="eliminarCanal" wire:loading.attr="disabled" class="btn-danger">
                        <span wire:loading.remove wire:target="eliminarCanal">Eliminar</span>
                        <span wire:loading wire:target="eliminarCanal">Eliminando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
