<div class="se-page max-w-5xl" x-data>
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Configuración</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Campos activos (Legajo del estudiante)</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Asigná cada campo a una solapa para el formulario del legajo y el listado PDF por curso.
                    El PDF agrupa las columnas por solapa (<span class="font-mono">solapas_legajo.nombre</span>).
                    En <strong>Etiqueta</strong> podés definir el texto del label que verá el usuario en cada solapa del legajo; si lo dejás vacío, se usa el nombre por defecto del sistema.
                    <strong>Apellido, nombre y DNI</strong> no se listan aquí: siempre aparecen en la solapa Alumno.
                    El listado sigue el orden de las columnas en la tabla <span class="font-mono">legajos</span>; la columna «Orden en solapa» define el orden dentro de cada solapa en el formulario.
                    Los campos sin solapa quedan ocultos del legajo y del PDF.
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
            <p class="se-section-title">Columnas de la tabla <span class="font-mono">legajos</span></p>
            <p class="mt-1 text-sm text-neutral-600">
                Seleccioná la solapa, el <strong>orden en solapa</strong> y opcionalmente la <strong>etiqueta</strong> visible en el formulario para cada columna (excepto apellido, nombre y DNI, fijos en la solapa Alumno).
                Si no asignás una solapa, el campo se oculta del legajo y del PDF.
            </p>
            @if ($baseDatosLegajos !== '')
                <p class="mt-2 text-xs text-neutral-500">
                    Origen del esquema: base <span class="font-mono">{{ $baseDatosLegajos }}</span>
                    · {{ $columnasEsquemaLegajos }} columna(s) en <span class="font-mono">legajos</span>.
                    Si no coincide con el colegio esperado, revisá <span class="font-mono">DB_DATABASE</span> / <span class="font-mono">TENANT_SLUG</span> en el servidor y ejecutá <span class="font-mono">php artisan config:clear</span>.
                </p>
            @endif
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" wire:click="sincronizarDesdeLegajos" wire:loading.attr="disabled" class="btn-primary btn-sm">
                    <span wire:loading.remove wire:target="sincronizarDesdeLegajos">Actualizar columnas desde esquema</span>
                    <span wire:loading wire:target="sincronizarDesdeLegajos">Comparando…</span>
                </button>
                <a href="{{ route('param.solapas-legajo') }}" class="btn-secondary btn-sm">Gestionar solapas</a>
            </div>
        </div>

        <div class="se-toolbar flex flex-col gap-3 border-b border-accent-200 bg-accent-50/60 px-5 py-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 flex-1 sm:max-w-md">
                <label for="filtro-solapa-campos-legajo" class="mb-1.5 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                    Ver columnas por solapa
                </label>
                <select id="filtro-solapa-campos-legajo" wire:model.live="filtroSolapa"
                        class="form-select w-full text-sm py-2">
                    <option value="">Todas las columnas</option>
                    <option value="__sin__">Sin solapa (ocultas del legajo)</option>
                    @foreach ($solapas as $s)
                        <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-neutral-500">
                    Filtrá por una solapa para ordenar solo esos campos y asignar el <strong>orden en solapa</strong> con más claridad.
                </p>
            </div>
        </div>

        <div class="w-full overflow-x-auto">
            <div class="flex justify-start">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-accent-50">
                        <tr>
                            <th class="table-header w-16">Pos.</th>
                            <th class="table-header">Columna</th>
                            <th class="table-header">Etiqueta</th>
                            <th class="table-header w-48">Solapa del legajo</th>
                            <th class="table-header w-28">Orden en solapa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-200 bg-white">
                        @forelse ($campos as $c)
                            <tr wire:key="campo-{{ $c->id }}" @class([
                                'bg-white',
                                'bg-accent-50/40' => $c->solapa_legajo_id !== null,
                            ])>
                                <td class="table-cell font-mono text-neutral-500">{{ $c->orden }}</td>
                                <td class="table-cell font-mono font-medium text-neutral-900">{{ $c->columna }}</td>
                                <td class="table-cell min-w-[12rem]">
                                    <input type="text" maxlength="100"
                                           value="{{ $c->etiqueta ?? '' }}"
                                           placeholder="Predeterminado"
                                           title="Texto del label en el formulario de legajo (máx. 100 caracteres). Vacío = catálogo del sistema."
                                           x-on:blur="$wire.setEtiqueta({{ $c->id }}, $event.target.value)"
                                           class="form-input w-full max-w-xs text-sm py-1.5 placeholder:text-neutral-400">
                                </td>
                                <td class="table-cell">
                                    <select
                                        x-on:change="$wire.setSolapa({{ $c->id }}, $event.target.value)"
                                        class="form-select text-sm py-1.5">
                                        <option value="">— Sin solapa (oculto) —</option>
                                        @foreach($solapas as $s)
                                            <option value="{{ $s->id }}"
                                                @selected($c->solapa_legajo_id === $s->id)>
                                                {{ $s->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="table-cell">
                                    <input type="number" min="0" max="999"
                                           value="{{ $c->orden_en_solapa }}"
                                           x-on:change="$wire.setOrden({{ $c->id }}, $event.target.value)"
                                           @if($c->solapa_legajo_id === null) disabled @endif
                                           class="form-input w-20 text-sm py-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="table-cell py-10 text-center text-neutral-500">
                                    @if ($filtroSolapa === '')
                                        No hay registros. Ejecutá «Actualizar columnas desde esquema».
                                    @elseif ($filtroSolapa === '__sin__')
                                        No hay columnas sin solapa asignada.
                                    @else
                                        No hay columnas asignadas a esta solapa.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
