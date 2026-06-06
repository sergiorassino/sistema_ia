<div class="se-page max-w-5xl" x-data>
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Configuración</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Campos activos (Legajo del docente)</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Asigná cada columna de <span class="font-mono">profesores</span> a una solapa del formulario del legajo docente.
                    <strong>Apellido, nombre, DNI y rol</strong> no se listan aquí: siempre aparecen en la solapa DOCENTE.
                    Los campos sin solapa quedan ocultos del legajo.
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
            <p class="se-section-title">Columnas de la tabla <span class="font-mono">profesores</span></p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" wire:click="sincronizarDesdeProfesores" wire:loading.attr="disabled" class="btn-primary btn-sm">
                    <span wire:loading.remove wire:target="sincronizarDesdeProfesores">Actualizar columnas desde esquema</span>
                    <span wire:loading wire:target="sincronizarDesdeProfesores">Comparando…</span>
                </button>
                <a href="{{ route('param.solapas-legajo-profesor') }}" class="btn-secondary btn-sm">Gestionar solapas</a>
            </div>
        </div>

        <div class="se-toolbar flex flex-col gap-3 border-b border-accent-200 bg-accent-50/60 px-5 py-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1 sm:max-w-md">
                <label for="filtro-solapa-campos-prof" class="mb-1.5 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Ver por solapa</label>
                <select id="filtro-solapa-campos-prof" wire:model.live="filtroSolapa" class="form-select w-full text-sm py-2">
                    <option value="">Todas las columnas</option>
                    <option value="__sin__">Sin solapa (ocultas)</option>
                    @foreach ($solapas as $s)
                        <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-accent-50">
                    <tr>
                        <th class="table-header w-16">Orden</th>
                        <th class="table-header">Columna</th>
                        <th class="table-header">Etiqueta</th>
                        <th class="table-header w-48">Solapa</th>
                        <th class="table-header w-28">Orden en solapa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-accent-200 bg-white">
                    @forelse ($campos as $c)
                        <tr wire:key="campo-prof-{{ $c->id }}" @class(['bg-accent-50/40' => $c->solapa_legajo_profesor_id !== null])>
                            <td class="table-cell font-mono text-neutral-500">{{ $c->orden }}</td>
                            <td class="table-cell font-mono font-medium">{{ $c->columna }}</td>
                            <td class="table-cell">
                                <input type="text" maxlength="100" value="{{ $c->etiqueta ?? '' }}" placeholder="Predeterminado"
                                       x-on:blur="$wire.setEtiqueta({{ $c->id }}, $event.target.value)"
                                       class="form-input w-full max-w-xs text-sm py-1.5">
                            </td>
                            <td class="table-cell">
                                <select x-on:change="$wire.setSolapa({{ $c->id }}, $event.target.value)" class="form-select text-sm py-1.5">
                                    <option value="">— Sin solapa —</option>
                                    @foreach($solapas as $s)
                                        <option value="{{ $s->id }}" @selected($c->solapa_legajo_profesor_id === $s->id)>{{ $s->nombre }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="table-cell">
                                <input type="number" min="0" max="999" value="{{ $c->orden_en_solapa }}"
                                       x-on:change="$wire.setOrden({{ $c->id }}, $event.target.value)"
                                       @disabled($c->solapa_legajo_profesor_id === null)
                                       class="form-input w-20 text-sm py-1.5 disabled:opacity-40">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="table-cell py-10 text-center text-neutral-500">
                                No hay registros. Ejecutá «Actualizar columnas desde esquema».
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
