<div class="se-page max-w-7xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Configuración · Permisos del sistema</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Permisos por Tarea</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Módulos y funciones del nivel actual con al menos un usuario habilitado. Solo consulta.
                </p>
            </div>
        </div>
    </section>

    <div class="se-card space-y-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Nivel</p>
                <p class="text-sm font-semibold text-neutral-800">{{ schoolCtx()->nivelNombre() }}</p>
            </div>
            <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-end lg:max-w-2xl">
                <div class="w-full sm:max-w-[14rem]">
                    <label for="permisos-tarea-tema" class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Tema</label>
                    <select id="permisos-tarea-tema"
                            wire:model.live="tema"
                            class="mt-1 w-full rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Todos</option>
                        @foreach ($temas as $nombreTema)
                            <option value="{{ $nombreTema }}">{{ $nombreTema }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0 flex-1">
                    <label for="permisos-tarea-buscar" class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Buscar</label>
                    <input id="permisos-tarea-buscar"
                           type="text"
                           wire:model.live.debounce.300ms="q"
                           placeholder="Módulo, función, apellido, nombre o DNI…"
                           class="mt-1 w-full rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
            </div>
        </div>

        <p class="text-xs text-neutral-500">
            {{ $totalTareas }} módulo(s) o función(es) con permisos otorgados
            @if ($totalTareas !== $totalAsignadas)
                (de {{ $totalAsignadas }})
            @endif
            .
            @if (tienePermiso(0))
                Para editar permisos, use
                <a href="{{ route('admin.permisos') }}" class="font-semibold text-primary-700 hover:underline">Asignación de Permisos de Usuario</a>.
            @endif
            @if (tienePermiso(14))
                Vista por persona:
                <a href="{{ route('admin.permisos-por-usuario') }}" class="font-semibold text-primary-700 hover:underline">Permisos por Usuario</a>.
            @endif
        </p>

        <div class="space-y-4" wire:loading.class="opacity-60">
            @forelse ($porTema as $nombreTema => $filasTema)
                <section class="overflow-hidden rounded-2xl border border-accent-200 bg-white" wire:key="tema-{{ $nombreTema }}">
                    <div class="flex items-center justify-between border-b border-accent-200 bg-accent-50 px-4 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-700">{{ $nombreTema }}</p>
                        <span class="se-pill bg-white text-neutral-700">{{ $filasTema->count() }}</span>
                    </div>

                    <div class="hidden border-b border-accent-200 bg-white px-4 py-2 text-[10px] font-semibold uppercase tracking-wider text-neutral-600 md:grid md:grid-cols-12 md:gap-4">
                        <div class="md:col-span-5">Módulo / función</div>
                        <div class="md:col-span-7">Usuarios con permiso</div>
                    </div>

                    <ul class="divide-y divide-accent-200">
                        @foreach ($filasTema as $fila)
                            <li class="px-4 py-3 hover:bg-accent-50/40" wire:key="tarea-{{ $fila['orden'] }}">
                                <div class="grid grid-cols-1 gap-2 md:grid-cols-12 md:items-start md:gap-4">
                                    <div class="min-w-0 md:col-span-5">
                                        <p class="text-sm font-semibold text-neutral-900">
                                            {{ $fila['descripcion'] }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-neutral-500">Orden {{ $fila['orden'] }}</p>
                                    </div>
                                    <div class="min-w-0 md:col-span-7">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($fila['usuarios'] as $u)
                                                <span class="inline-block max-w-full truncate rounded-md border border-accent-200 bg-accent-50/80 px-1.5 py-0.5 text-[10px] leading-snug text-neutral-700"
                                                      title="{{ trim($u->apellido . ', ' . $u->nombre) }} · DNI {{ $u->dni }} · {{ $u->tipo?->tipo ?? 'Sin rol' }}">
                                                    {{ trim($u->apellido . ', ' . $u->nombre) }}
                                                </span>
                                            @endforeach
                                        </div>
                                        <p class="mt-1 text-[10px] text-neutral-500">
                                            {{ count($fila['usuarios']) }} usuario(s)
                                        </p>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <div class="rounded-2xl border border-dashed border-accent-200 bg-white px-4 py-10 text-center text-sm text-neutral-600">
                    @if ($totalAsignadas === 0)
                        No hay módulos con permisos otorgados para mostrar.
                    @else
                        Ningún módulo coincide con la búsqueda.
                    @endif
                </div>
            @endforelse
        </div>
    </div>
</div>
