<div>
    <div class="se-page max-w-6xl">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Configuración · Permisos del sistema</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Permisos de Usuarios</h2>
                    <p class="max-w-2xl text-sm text-white/80">
                        Editá los permisos de personal de secretaría y administración del nivel actual. No se listan docentes con rol Profesor/a.
                    </p>
                </div>
                <button type="button"
                        wire:click="abrirModalCopiar"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Copiar permisos
                </button>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <aside class="lg:col-span-4">
                <div class="se-card space-y-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Usuarios</p>
                        <p class="text-sm font-semibold text-neutral-800">Nivel: {{ schoolCtx()->nivelNombre() }}</p>
                    </div>

                    <div class="space-y-2">
                        <label for="permisos-usuario-select" class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">
                            Elegir usuario
                        </label>
                        <select id="permisos-usuario-select"
                                wire:model.live="profesorId"
                                class="w-full rounded-xl border border-accent-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">— Seleccionar —</option>
                            @foreach ($usuarios as $u)
                                <option value="{{ $u->id }}">
                                    {{ trim($u->apellido . ', ' . $u->nombre) }} · DNI {{ $u->dni }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label for="permisos-usuario-buscar" class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Buscar</label>
                        <input id="permisos-usuario-buscar"
                               type="text"
                               wire:model.live.debounce.300ms="q"
                               placeholder="Apellido, nombre o DNI…"
                               class="w-full rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>

                    <div class="max-h-[420px] overflow-y-auto rounded-2xl border border-accent-200 bg-white">
                        <ul class="divide-y divide-accent-200">
                            @forelse ($usuarios as $u)
                                <li wire:key="permisos-usuario-{{ $u->id }}">
                                    <button type="button"
                                            wire:click="seleccionarProfesor({{ (int) $u->id }})"
                                            @class([
                                                'w-full text-left px-4 py-3 hover:bg-accent-50/60 transition-colors',
                                                'bg-[rgba(64,132,141,0.10)]' => (int) $profesorId === (int) $u->id,
                                            ])>
                                        <p class="text-sm font-semibold text-neutral-900">
                                            {{ trim($u->apellido . ', ' . $u->nombre) }}
                                        </p>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-neutral-600">
                                            <span>DNI: {{ $u->dni }}</span>
                                            <span class="se-pill bg-accent-50 text-neutral-700">
                                                {{ $u->tipo?->tipo ?? 'Sin rol asignado' }}
                                            </span>
                                        </div>
                                    </button>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-sm text-neutral-600">
                                    No hay usuarios para mostrar.
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </aside>

            <main class="lg:col-span-8">
                <div class="se-card">
                    @if (! $profesorSeleccionado)
                        <div class="rounded-2xl border border-dashed border-[#C1D7DA] bg-white/80 p-8 text-center text-sm text-neutral-600">
                            Seleccione un usuario para editar sus permisos.
                        </div>
                    @else
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Usuario seleccionado</p>
                            <p class="mt-1 text-lg font-bold text-neutral-900">
                                {{ trim($profesorSeleccionado->apellido . ', ' . $profesorSeleccionado->nombre) }}
                            </p>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-neutral-600">
                                <span>DNI: {{ $profesorSeleccionado->dni }}</span>
                                <span class="se-pill bg-accent-50 text-neutral-700">
                                    Rol: {{ $profesorSeleccionado->tipo?->tipo ?? 'Sin rol asignado' }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-neutral-500">Los cambios se guardan al marcar o desmarcar cada permiso.</p>
                        </div>

                        <livewire:administracion.permisos.permisos-usuario-editor
                            :profesor-id="$profesorId"
                            wire:key="permisos-editor-{{ $profesorId }}" />
                    @endif
                </div>
            </main>
        </div>
    </div>

    {{-- @if ANTES de @teleport (como historial-examenes). Un @teleport vacío rompe clics Livewire en el resto de la pantalla. --}}
    @if ($showModalCopiar)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="modal-copiar-permisos-titulo"
                 wire:key="modal-copiar-permisos">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                     wire:click="cerrarModalCopiar"
                     aria-hidden="true"></div>

                <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),36rem)]"
                     wire:click.stop>
                    <div class="shrink-0 border-b border-accent-200 px-5 py-4 sm:px-6">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 id="modal-copiar-permisos-titulo" class="text-base font-semibold text-neutral-900">
                                    Copiar permisos entre usuarios
                                </h3>
                                <p class="mt-1 text-xs text-neutral-600">
                                    Nivel: <span class="font-semibold">{{ schoolCtx()->nivelNombre() }}</span>.
                                    Se reemplazarán todos los permisos del usuario destino por los del origen.
                                </p>
                            </div>
                            <button type="button"
                                    wire:click="cerrarModalCopiar"
                                    class="shrink-0 text-neutral-400 transition hover:text-neutral-700"
                                    aria-label="Cerrar">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4 sm:px-6">
                        <div class="space-y-2">
                            <label for="copiar-origen" class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">
                                Copiar desde (origen)
                            </label>
                            <select id="copiar-origen"
                                    wire:model.live="copiarOrigenId"
                                    class="w-full rounded-xl border border-accent-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">— Seleccionar usuario —</option>
                                @foreach ($usuariosModal as $u)
                                    <option value="{{ $u->id }}">
                                        {{ trim($u->apellido . ', ' . $u->nombre) }}
                                        · DNI {{ $u->dni }}
                                        · {{ $u->tipo?->tipo ?? 'Sin rol' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('copiarOrigenId')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="copiar-destino" class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">
                                Copiar hacia (destino)
                            </label>
                            <select id="copiar-destino"
                                    wire:model.live="copiarDestinoId"
                                    class="w-full rounded-xl border border-accent-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">— Seleccionar usuario —</option>
                                @foreach ($usuariosModal as $u)
                                    @if ((int) $copiarOrigenId !== (int) $u->id)
                                        <option value="{{ $u->id }}">
                                            {{ trim($u->apellido . ', ' . $u->nombre) }}
                                            · DNI {{ $u->dni }}
                                            · {{ $u->tipo?->tipo ?? 'Sin rol' }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            @error('copiarDestinoId')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($usuariosModal->isEmpty())
                            <p class="rounded-xl border border-dashed border-accent-200 bg-accent-50/50 px-4 py-3 text-sm text-neutral-600">
                                No hay usuarios con rol en este nivel para copiar permisos.
                            </p>
                        @endif
                    </div>

                    @php
                        $uOrigenModal = $usuariosModal->firstWhere('id', (int) $copiarOrigenId);
                        $uDestinoModal = $usuariosModal->firstWhere('id', (int) $copiarDestinoId);
                        $nombreOrigenCopiar = $uOrigenModal
                            ? trim($uOrigenModal->apellido . ', ' . $uOrigenModal->nombre)
                            : '';
                        $nombreDestinoCopiar = $uDestinoModal
                            ? trim($uDestinoModal->apellido . ', ' . $uDestinoModal->nombre)
                            : '';
                        $puedeConfirmarCopiar = $nombreOrigenCopiar !== '' && $nombreDestinoCopiar !== '';
                        $mensajeConfirmarCopiar = $puedeConfirmarCopiar
                            ? "Se copiarán todos los permisos de «{$nombreOrigenCopiar}» hacia «{$nombreDestinoCopiar}». Los permisos actuales del destino se reemplazarán por completo."
                            : 'Seleccione el usuario origen y el usuario destino antes de continuar.';
                    @endphp

                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-4 sm:px-6">
                        <button type="button"
                                wire:click="cerrarModalCopiar"
                                class="btn-secondary">
                            Cancelar
                        </button>
                        <button type="button"
                                class="btn-primary"
                                wire:loading.attr="disabled"
                                wire:target="copiarPermisos"
                                x-on:click="
                                    @if ($puedeConfirmarCopiar)
                                        window.seSwalConfirmar(@js($mensajeConfirmarCopiar), @js('Confirmar copia de permisos'), { confirmButtonText: 'Sí, copiar' }).then((ok) => { if (ok) $wire.copiarPermisos(); })
                                    @else
                                        window.seSwalAviso(@js($mensajeConfirmarCopiar))
                                    @endif
                                ">
                            <span wire:loading.remove wire:target="copiarPermisos">Copiar permisos</span>
                            <span wire:loading wire:target="copiarPermisos">Copiando…</span>
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
