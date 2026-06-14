<div class="se-page">
    @if (session('success'))
        <div class="se-soft-card border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cooperadora</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Proveedores</h2>
            </div>
            <a href="{{ route('cooperadora.proveedores.nuevo') }}" class="btn-primary shrink-0">+ Nuevo proveedor</a>
        </div>
    </section>

    <div class="se-toolbar flex-wrap gap-3">
        <div class="min-w-[12rem] flex-1">
            <label class="se-label">Buscar</label>
            <input type="search" wire:model.live.debounce.300ms="buscar" class="se-input w-full" placeholder="Nombre o CUIT">
        </div>
        <label class="flex items-center gap-2 self-end rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm">
            <input type="checkbox" wire:model.live="soloActivos" class="rounded border-accent-300 text-primary-600"> Solo activos
        </label>
    </div>

    <div class="w-full overflow-x-auto se-grid-angosta-wrap">
        <div class="gf min-w-[36rem]">
            <div class="gf-head">
                <div class="gf-th flex-1">Nombre</div>
                <div class="gf-th w-32">CUIT</div>
                <div class="gf-th w-28">Teléfono</div>
                <div class="gf-th w-20 text-center">Activo</div>
                <div class="gf-th-right w-40">Acciones</div>
            </div>
            @forelse ($proveedores as $proveedor)
                <div class="gf-row gf-row-hover" wire:key="prov-{{ $proveedor->id }}">
                    <div class="gf-td flex-1 font-medium">{{ $proveedor->nombre }}</div>
                    <div class="gf-td w-32">{{ $proveedor->cuit ?? '—' }}</div>
                    <div class="gf-td w-28">{{ $proveedor->telefono ?? '—' }}</div>
                    <div class="gf-td w-20 text-center">{{ $proveedor->activo ? 'Sí' : 'No' }}</div>
                    <div class="gf-td-actions w-40">
                        <a href="{{ route('cooperadora.proveedores.editar', $proveedor->id) }}" class="btn-secondary btn-sm">Editar</a>
                        <button type="button" wire:click="toggleActivo({{ $proveedor->id }})" class="btn-secondary btn-sm">
                            {{ $proveedor->activo ? 'Desactivar' : 'Activar' }}
                        </button>
                    </div>
                </div>
            @empty
                <div class="gf-empty">No hay proveedores.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">{{ $proveedores->links() }}</div>
</div>
