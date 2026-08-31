<div class="se-page max-w-7xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Configuración · Permisos del sistema</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Permisos por Usuario</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Usuarios del nivel actual con al menos un permiso concedido. Solo consulta.
                </p>
            </div>
        </div>
    </section>

    <div class="se-card space-y-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Nivel</p>
                <p class="text-sm font-semibold text-neutral-800">{{ schoolCtx()->nivelNombre() }}</p>
            </div>
            <div class="w-full sm:max-w-xs">
                <label class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Buscar</label>
                <input type="text"
                       wire:model.live.debounce.300ms="q"
                       placeholder="Apellido, nombre o DNI…"
                       class="mt-1 w-full rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>
        </div>

        <p class="text-xs text-neutral-500">
            {{ $totalUsuarios }} usuario(s) con permisos otorgados.
            @if (tienePermiso(0))
                Para editar permisos, use
                <a href="{{ route('admin.permisos') }}" class="font-semibold text-primary-700 hover:underline">Asignación de Permisos de Usuario</a>.
            @endif
            @if (tienePermiso(\App\Support\PermisosIaCatalog::PERMISOS_POR_TAREA))
                Vista por módulo:
                <a href="{{ route('admin.permisos-por-tarea') }}" class="font-semibold text-primary-700 hover:underline">Permisos por Tarea</a>.
            @endif
        </p>

        <div class="overflow-hidden rounded-2xl border border-accent-200 bg-white">
            <div class="hidden border-b border-accent-200 bg-accent-50 px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wider text-neutral-600 md:grid md:grid-cols-12 md:gap-4">
                <div class="md:col-span-4">Usuario</div>
                <div class="md:col-span-8">Permisos concedidos</div>
            </div>

            <ul class="divide-y divide-accent-200" wire:loading.class="opacity-60">
                @forelse ($filas as $fila)
                    @php($u = $fila['profesor'])
                    <li class="px-4 py-3 hover:bg-accent-50/40">
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-12 md:items-start md:gap-4">
                            <div class="min-w-0 md:col-span-4">
                                <p class="text-sm font-semibold text-neutral-900">
                                    {{ trim($u->apellido . ', ' . $u->nombre) }}
                                </p>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-neutral-600">
                                    <span>DNI {{ $u->dni }}</span>
                                    <span class="se-pill bg-accent-50 text-neutral-700">
                                        {{ $u->tipo?->tipo ?? 'Sin rol' }}
                                    </span>
                                </div>
                            </div>
                            <div class="min-w-0 md:col-span-8">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($fila['permisos'] as $etiqueta)
                                        <span class="inline-block max-w-full truncate rounded-md border border-accent-200 bg-accent-50/80 px-1.5 py-0.5 text-[10px] leading-snug text-neutral-700"
                                              title="{{ $etiqueta }}">
                                            {{ $etiqueta }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-sm text-neutral-600">
                        No hay usuarios con permisos otorgados para mostrar.
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
