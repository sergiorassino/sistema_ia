<div class="se-page max-w-3xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Configuración</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Solapas del Legajo del docente</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Definí las pestañas del formulario de legajo docente. La solapa <strong>DOCENTE</strong> (slug <span class="font-mono">docente</span>) es obligatoria y no puede eliminarse.
                </p>
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <button wire:click="nuevo" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                    Nueva solapa
                </button>
            </div>
        </div>
    </section>

    @if (session('status'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="se-section-title">Solapas definidas</p>
            <p class="mt-1 text-sm text-neutral-600">
                El <span class="font-mono">slug</span> debe ser único (minúsculas, números, guiones y guiones bajos).
            </p>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-accent-50">
                    <tr>
                        <th class="table-header w-20">Orden</th>
                        <th class="table-header">Nombre</th>
                        <th class="table-header">Slug</th>
                        <th class="table-header text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-accent-200 bg-white">
                    @forelse ($solapas as $s)
                        <tr wire:key="solapa-prof-{{ $s->id }}">
                            <td class="table-cell font-mono text-neutral-500">{{ $s->orden }}</td>
                            <td class="table-cell font-semibold text-neutral-900">{{ $s->nombre }}</td>
                            <td class="table-cell font-mono text-neutral-600">{{ $s->slug }}</td>
                            <td class="table-cell text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="subir({{ $s->id }})" class="btn-secondary btn-sm" title="Subir">↑</button>
                                    <button wire:click="bajar({{ $s->id }})" class="btn-secondary btn-sm" title="Bajar">↓</button>
                                    <button wire:click="editar({{ $s->id }})" class="btn-secondary btn-sm">Editar</button>
                                    @if($s->slug !== 'docente')
                                        <button wire:click="confirmarEliminar({{ $s->id }})" class="btn-danger btn-sm">Eliminar</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="table-cell py-10 text-center text-neutral-500">No hay solapas definidas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="border-b border-accent-200 px-6 py-4">
                    <h3 class="text-base font-bold text-neutral-900">{{ $editId ? 'Editar solapa' : 'Nueva solapa' }}</h3>
                </div>
                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label class="form-label">Nombre *</label>
                        <input wire:model="nombre" type="text" maxlength="60" class="form-input @error('nombre') border-red-400 @enderror">
                        @error('nombre') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Slug *</label>
                        <input wire:model="slug" type="text" maxlength="30" class="form-input @error('slug') border-red-400 @enderror">
                        @error('slug') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/70 px-6 py-4">
                    <button wire:click="cerrarModal" class="btn-secondary">Cancelar</button>
                    <button wire:click="guardar" wire:loading.attr="disabled" class="btn-primary">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showConfirm)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-neutral-900/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="px-6 py-5">
                    <h3 class="mb-1 text-base font-semibold text-neutral-800">Eliminar solapa</h3>
                    <p class="text-sm text-neutral-600">¿Eliminar «{{ $deleteInfo }}»? Los campos quedarán sin solapa.</p>
                </div>
                <div class="flex justify-end gap-3 px-6 pb-5">
                    <button wire:click="$set('showConfirm', false)" class="btn-secondary">Cancelar</button>
                    <button wire:click="eliminar" class="btn-danger">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
