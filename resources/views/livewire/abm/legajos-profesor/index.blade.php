<div>
    <div class="se-page">
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-3">
                    <p class="se-eyebrow">Docentes</p>
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Legajos del docente</h2>
                        <p class="mt-2 max-w-2xl text-sm text-white/80">
                            {{ schoolCtx()->nivelNombre() }} · Un registro de <span class="font-mono">profesores</span> por nivel
                        </p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                        <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Registros</span>
                        <span class="text-xl font-bold tabular-nums">{{ $profesores->total() }}</span>
                    </span>
                    @if (puedeModificarLegajosDocentes())
                        <a href="{{ route('abm.legajos-profesor.create') }}"
                           class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Nuevo legajo
                        </a>
                    @endif
                </div>
            </div>
        </section>

        <div class="se-toolbar">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input wire:model.live.debounce.400ms="search" type="search" placeholder="Buscar por apellido, nombre o DNI…" class="form-input pl-9">
            </div>
            <div class="sm:w-64">
                <label for="filtroRol" class="sr-only">Filtrar por rol</label>
                <select id="filtroRol" wire:model.live="filtroRol" class="form-select">
                    <option value="">Activos (excluye «Sin Rol»)</option>
                    <option value="todos">Todos los roles</option>
                    <option disabled>──────────</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r->id }}">{{ $r->tipo }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="se-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse">
                    <thead class="bg-accent-50">
                        <tr>
                            <th class="table-header">Docente</th>
                            <th class="table-header w-32">DNI</th>
                            <th class="table-header w-40">Rol</th>
                            <th class="table-header min-w-[14rem] text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-200 bg-white">
                        @forelse ($profesores as $p)
                            <tr id="prof-{{ $p->id }}"
                                x-data="{ focus: {{ (int) $focusId === (int) $p->id ? 'true' : 'false' }} }"
                                x-init="if (focus) { $nextTick(() => { const el = document.getElementById('prof-{{ $p->id }}'); el?.scrollIntoView({ block: 'center' }); el?.classList.add('ring-2','ring-primary-400','bg-primary-50/60'); }); }"
                                class="align-top transition-colors hover:bg-accent-50/60">
                                <td class="table-cell">
                                    <div class="font-semibold text-neutral-900">{{ $p->apellido }}, {{ $p->nombre }}</div>
                                </td>
                                <td class="table-cell font-mono text-neutral-700">{{ $p->dni }}</td>
                                <td class="table-cell text-neutral-700">{{ $p->tipo?->tipo ?? '—' }}</td>
                                <td class="table-cell text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if (puedeModificarLegajosDocentes())
                                            <a href="{{ route('abm.legajos-profesor.edit', $p->id) }}" class="btn-secondary btn-sm">Editar</a>
                                            @if (tienePermiso(\App\Support\PermisosIaCatalog::LEGAJOS_DOCENTES_VER_CONTRASEÑA))
                                                <button type="button" wire:click="verPassword({{ $p->id }})" class="btn-secondary btn-sm">Ver Pwrd</button>
                                            @endif
                                            <button type="button" wire:click="confirmDelete({{ $p->id }})" class="btn-danger btn-sm">Eliminar</button>
                                        @else
                                            <a href="{{ route('abm.legajos-profesor.edit', $p->id) }}" class="btn-secondary btn-sm">Ver</a>
                                            @if (tienePermiso(\App\Support\PermisosIaCatalog::LEGAJOS_DOCENTES_VER_CONTRASEÑA))
                                                <button type="button" wire:click="verPassword({{ $p->id }})" class="btn-secondary btn-sm">Ver Pwrd</button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-cell py-10 text-center text-neutral-500">
                                    No hay legajos en este nivel.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($profesores->hasPages())
                <div class="border-t border-accent-200 bg-accent-50/70 px-4 py-3">
                    {{ $profesores->links('vendor.pagination.se') }}
                </div>
            @endif
        </div>

        @if ($showConfirm)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/60 p-4 backdrop-blur-sm">
                <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                    <div class="px-6 py-5">
                        <h3 class="mb-2 text-base font-semibold text-neutral-900">{{ $puedeEliminar ? 'Confirmar eliminación' : 'No se puede eliminar' }}</h3>
                        <p class="text-sm text-neutral-600">{{ $deleteInfo }}</p>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/70 px-6 py-4">
                        <button wire:click="$set('showConfirm', false)" class="btn-secondary">{{ $puedeEliminar ? 'Cancelar' : 'Cerrar' }}</button>
                        @if ($puedeEliminar)
                            <button wire:click="delete" wire:loading.attr="disabled" class="btn-danger">Eliminar</button>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($showPasswordModal)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="legajo-docente-pwrd-titulo">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarPasswordModal"></div>
                <div class="relative z-10 my-auto w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="border-b border-accent-200 px-6 py-5">
                        <h3 id="legajo-docente-pwrd-titulo" class="text-base font-semibold text-neutral-900">Contraseña del docente</h3>
                        <p class="mt-1 text-sm text-neutral-600">{{ $passwordModalDocente }}</p>
                    </div>
                    <div class="px-6 py-5">
                        @if ($passwordModalEncriptada)
                            <p class="text-sm leading-relaxed text-neutral-600">{{ $passwordModalTexto }}</p>
                        @else
                            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-500">Contraseña</p>
                            <p class="mt-2 rounded-xl border border-accent-200 bg-accent-50 px-4 py-3 font-mono text-lg font-semibold tracking-wide text-neutral-900">{{ $passwordModalTexto }}</p>
                        @endif
                    </div>
                    <div class="flex justify-end border-t border-accent-200 bg-accent-50/70 px-6 py-4">
                        <button type="button" wire:click="cerrarPasswordModal" class="btn-secondary">Cerrar</button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>

@script
<script>
    $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
</script>
@endscript
