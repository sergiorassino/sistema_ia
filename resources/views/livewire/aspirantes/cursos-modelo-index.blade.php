<div class="se-page max-w-4xl" x-data>
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Aspirantes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Cursos modelo</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Cargá los cursos sin sección que ofrece el colegio en este nivel
                    (p. ej. "Sala de 4", "Primero", "Segundo"). Estos son los cursos que aparecerán como opciones
                    en la instancia de registro y en el formulario del aspirante.
                </p>
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
            <p class="se-section-title">Agregar curso modelo</p>
        </div>
        <form wire:submit.prevent="agregar" class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_8rem_auto] sm:items-end">
            <div>
                <label class="se-label">Nombre</label>
                <input type="text" wire:model.defer="nuevoNombre" maxlength="80"
                       placeholder="Sala de 4 / Primero / Segundo…"
                       class="form-input w-full">
                @error('nuevoNombre')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="se-label">Orden</label>
                <input type="number" min="0" max="999" wire:model.defer="nuevoOrden" class="form-input w-full">
                @error('nuevoOrden')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <button type="submit" class="btn-primary">Agregar</button>
            </div>
        </form>
    </div>

    <div class="se-card overflow-hidden">
        <div class="w-full overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-accent-50">
                    <tr>
                        <th class="table-header w-20">Orden</th>
                        <th class="table-header">Nombre</th>
                        <th class="table-header w-24">Activo</th>
                        <th class="table-header w-24 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-accent-200 bg-white">
                    @forelse ($cursos as $c)
                        <tr wire:key="curso-modelo-{{ $c->id }}">
                            <td class="table-cell">
                                <input type="number" min="0" max="999"
                                       value="{{ $c->orden }}"
                                       x-on:change="$wire.setOrden({{ $c->id }}, $event.target.value)"
                                       class="form-input w-20 text-sm py-1.5">
                            </td>
                            <td class="table-cell min-w-[12rem]">
                                <input type="text" maxlength="80"
                                       value="{{ $c->nombre }}"
                                       x-on:blur="$wire.setNombre({{ $c->id }}, $event.target.value)"
                                       class="form-input w-full max-w-sm text-sm py-1.5">
                            </td>
                            <td class="table-cell text-center">
                                <input type="checkbox"
                                       @checked($c->activo)
                                       x-on:change="$wire.setActivo({{ $c->id }}, $event.target.checked)"
                                       class="form-checkbox h-4 w-4 text-primary-600">
                            </td>
                            <td class="table-cell text-right">
                                <button type="button"
                                        wire:click="eliminar({{ $c->id }})"
                                        wire:confirm="¿Eliminar este curso modelo? Si hay instancias con este curso seleccionado se quitará de ellas."
                                        class="btn-secondary btn-sm text-red-700 border-red-200 hover:bg-red-50">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="table-cell py-10 text-center text-neutral-500">
                                Aún no cargaste cursos modelo. Empezá agregando el primero arriba.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
