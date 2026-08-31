<div class="mt-6 space-y-6">
    @foreach ($porTema as $tema => $items)
        <section class="rounded-2xl border border-accent-200 bg-white">
            <div class="flex items-center justify-between border-b border-accent-200 bg-accent-50 px-4 py-3">
                <p class="text-sm font-bold uppercase tracking-wider text-neutral-900">{{ $tema }}</p>
                <p class="text-xs text-neutral-500">{{ count($items) }} permiso(s)</p>
            </div>
            <div class="divide-y divide-accent-200">
                @foreach ($items as $perm)
                    @php($reservadoAdmin = \App\Support\PermisosIaCatalog::esReservadoAdministrador((int) $perm->orden))
                    <label @class([
                               'flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-accent-50/40',
                               'bg-amber-50 hover:bg-amber-100/70' => $reservadoAdmin,
                           ])
                           wire:loading.class="opacity-60"
                           wire:target="togglePermiso">
                        <input type="checkbox"
                               class="mt-1 rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                               wire:click="togglePermiso({{ (int) $perm->orden }})"
                               wire:loading.attr="disabled"
                               wire:target="togglePermiso"
                               @checked(isset($permisosCadena[(int) $perm->orden]) && $permisosCadena[(int) $perm->orden] === '1')>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-normal text-neutral-700">
                                Orden {{ (int) $perm->orden }}
                            </span>
                            @include('livewire.administracion.permisos.partials.descripcion-permiso', [
                                'orden' => (int) $perm->orden,
                                'descripcion' => (string) $perm->descripcion,
                                'titulo' => false,
                            ])
                        </span>
                    </label>
                @endforeach
            </div>
        </section>
    @endforeach

    <p class="text-xs text-neutral-500">
        Nota: el acceso a esta pantalla está protegido por el permiso de orden 0 (ADMINISTRACIÓN / PERMISOS).
    </p>
</div>
