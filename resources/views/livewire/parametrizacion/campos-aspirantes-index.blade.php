@php
    $tieneOpcionesNivel = \Illuminate\Support\Facades\Schema::hasColumn('campos_aspirantes_nivel', 'opciones');
    $tieneAyudaNivel = \Illuminate\Support\Facades\Schema::hasColumn('campos_aspirantes_nivel', 'ayuda');
@endphp

<div class="se-page max-w-5xl" x-data>
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Configuración</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Campos activos (Aspirantes)</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Activá las columnas de <span class="font-mono">aspirantes</span> que querés que aparezcan en el formulario público de registro.
                    Elegí un <strong>nivel</strong>: visible, obligatorio, etiqueta, ayuda y opciones se guardan por nivel.
                    En <strong>Etiqueta</strong> definís el label (vacío = nombre de columna). En <strong>Ayuda</strong>, texto breve que verá la familia al inscribirse.
                    En <strong>Opciones</strong>, valores separados por
                    <span class="font-mono">#</span> (ej. <span class="font-mono">DNI#LC#LE</span>) arman un desplegable; vacío = texto libre.
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
            <p class="se-section-title">Columnas de la tabla <span class="font-mono">aspirantes</span></p>
            <p class="mt-1 text-sm text-neutral-600">
                Si agregaste o quitaste columnas en la tabla, usá «Actualizar columnas desde esquema» para que aparezcan acá.
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" wire:click="sincronizarDesdeAspirantes" wire:loading.attr="disabled" class="btn-primary btn-sm">
                    <span wire:loading.remove wire:target="sincronizarDesdeAspirantes">Actualizar columnas desde esquema</span>
                    <span wire:loading wire:target="sincronizarDesdeAspirantes">Comparando…</span>
                </button>
            </div>
        </div>

        <div class="se-toolbar flex flex-col gap-3 border-b border-accent-200 bg-accent-50/60 px-5 py-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 flex-1 sm:max-w-md">
                <label for="filtro-campos-aspirantes" class="mb-1.5 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                    Filtrar columnas
                </label>
                <select id="filtro-campos-aspirantes" wire:model.live="filtro"
                        class="form-select w-full text-sm py-2">
                    <option value="">Todas las columnas</option>
                    <option value="visibles">Solo visibles</option>
                    <option value="ocultas">Solo ocultas</option>
                </select>
            </div>

            <div class="min-w-0 flex-1 sm:max-w-xs">
                <label for="nivel-campos-aspirantes" class="mb-1.5 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                    Nivel
                </label>
                <select id="nivel-campos-aspirantes" wire:model.live="idNivel"
                        class="form-select w-full text-sm py-2">
                    <option value="">— Elegir nivel —</option>
                    @foreach ($niveles as $n)
                        <option value="{{ $n->id }}">{{ $n->nivel }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-neutral-500">
                    Visible, obligatorio, etiqueta, ayuda y opciones se guardan por nivel.
                </p>
            </div>
        </div>

        <div class="w-full overflow-x-auto">
            <div class="flex justify-start">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-accent-50">
                        <tr>
                            <th class="table-header w-20">Orden</th>
                            <th class="table-header">Columna</th>
                            <th class="table-header">Etiqueta</th>
                            @if ($tieneAyudaNivel)
                                <th class="table-header min-w-[14rem]">Ayuda</th>
                            @endif
                            @if ($tieneOpcionesNivel)
                                <th class="table-header min-w-[12rem]">Opciones (#)</th>
                            @endif
                            <th class="table-header w-24">Visible</th>
                            <th class="table-header w-28">Obligatorio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-200 bg-white">
                        @forelse ($campos as $c)
                            <tr wire:key="campo-asp-{{ $c->id }}" @class([
                                'bg-white',
                                'bg-accent-50/40' => $c->visible,
                            ])>
                                <td class="table-cell">
                                    <input type="number" min="0" max="999"
                                           value="{{ $c->orden }}"
                                           x-on:change="$wire.setOrden({{ $c->id }}, $event.target.value)"
                                           class="form-input w-20 text-sm py-1.5">
                                </td>
                                <td class="table-cell font-mono font-medium text-neutral-900">{{ $c->columna }}</td>
                                <td class="table-cell min-w-[14rem]">
                                    <input type="text" maxlength="100"
                                           value="{{ $c->etiqueta ?? '' }}"
                                           placeholder="{{ $c->columna }}"
                                           title="Texto del label en el formulario público (máx. 100 caracteres)."
                                           @disabled($idNivel <= 0)
                                           x-on:blur="$wire.setEtiqueta({{ $c->id }}, $event.target.value)"
                                           class="form-input w-full max-w-sm text-sm py-1.5 placeholder:text-neutral-400 disabled:opacity-50">
                                </td>
                                @if ($tieneAyudaNivel)
                                    <td class="table-cell min-w-[14rem] align-top">
                                        <textarea rows="2" maxlength="500"
                                                  placeholder="Ej.: Número sin puntos ni espacios"
                                                  title="Texto de ayuda visible en el formulario público (máx. 500 caracteres)."
                                                  @disabled($idNivel <= 0)
                                                  x-on:blur="$wire.setAyuda({{ $c->id }}, $event.target.value)"
                                                  class="form-input w-full max-w-md text-sm py-1.5 leading-relaxed placeholder:text-neutral-400 disabled:opacity-50">{{ $c->ayuda ?? '' }}</textarea>
                                    </td>
                                @endif
                                @if ($tieneOpcionesNivel)
                                    <td class="table-cell min-w-[12rem] align-top">
                                        <textarea rows="2" maxlength="500"
                                                  placeholder="Vacío = texto libre. Ej.: DNI#LC#LE"
                                                  title="Opciones del desplegable separadas por #. Vacío = campo de texto."
                                                  @disabled($idNivel <= 0)
                                                  x-on:blur="$wire.setOpciones({{ $c->id }}, $event.target.value)"
                                                  class="form-input w-full max-w-md text-sm py-1.5 font-mono leading-relaxed placeholder:text-neutral-400 disabled:opacity-50">{{ $c->opciones ?? '' }}</textarea>
                                    </td>
                                @endif
                                <td class="table-cell text-center">
                                    <input type="checkbox"
                                           @checked($c->visible)
                                           x-on:change="$wire.setVisible({{ $c->id }}, $event.target.checked)"
                                           class="form-checkbox h-4 w-4 text-primary-600">
                                </td>
                                <td class="table-cell text-center">
                                    <input type="checkbox"
                                           @checked($c->obligatorio)
                                           @disabled(! $c->visible)
                                           x-on:change="$wire.setObligatorio({{ $c->id }}, $event.target.checked)"
                                           class="form-checkbox h-4 w-4 text-primary-600 disabled:opacity-40">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 4 + ($tieneAyudaNivel ? 1 : 0) + ($tieneOpcionesNivel ? 1 : 0) + 2 }}" class="table-cell py-10 text-center text-neutral-500">
                                    No hay registros. Ejecutá «Actualizar columnas desde esquema».
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
