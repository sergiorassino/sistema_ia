@php
    $gruposVenc = [
        1 => ['corto' => 'Hasta 1º vto.', 'largo' => 'Hasta el 1º vencimiento'],
        2 => ['corto' => '1º – 2º vto.', 'largo' => 'Entre el 1º y 2º vencimiento'],
        3 => ['corto' => '2º – 3º vto.', 'largo' => 'Entre el 2º y 3º vencimiento'],
        4 => ['corto' => 'Después 3º vto.', 'largo' => 'Después del 3º vencimiento'],
    ];
@endphp

<div class="se-cuotas-importes-fill">
    <div class="se-cuotas-importes-grid">
        <section class="se-hero se-cuotas-importes-hero">
            <div class="se-hero-inner flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-0.5">
                    <p class="se-eyebrow !text-[10px]">Gestión masiva · Año {{ $ano }}</p>
                    <h1 class="text-lg font-bold tracking-tight text-white sm:text-xl uppercase truncate" title="{{ $cuota->nombre }}">
                        {{ $cuota->nombre }}
                    </h1>
                    <p class="text-xs text-white/80">Importes y fórmulas por sala / grado / curso</p>
                </div>
                <a href="{{ route('cuotas.importes.index') }}"
                   wire:navigate
                   class="inline-flex shrink-0 items-center justify-center rounded-lg bg-white px-3 py-1.5 text-[11px] font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                    Volver
                </a>
            </div>
        </section>

        <div class="se-toolbar se-cuotas-importes-toolbar" x-data x-init="$nextTick(() => $refs.cuotasImportesFormBuscar?.focus())">
            <div class="relative w-full max-w-md">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input wire:model.live.debounce.300ms="search"
                       type="search"
                       x-ref="cuotasImportesFormBuscar"
                       placeholder="Búsqueda por curso"
                       class="form-input pl-9 text-sm py-1.5"
                       autocomplete="off">
            </div>
        </div>

        <div class="se-card se-cuotas-importes-card">
            <div class="border-b border-accent-200 bg-accent-50/80 px-3 py-2.5 sm:px-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Columna % / $</p>
                <ul class="mt-1.5 flex flex-col gap-1 text-[11px] leading-snug text-neutral-600 sm:flex-row sm:flex-wrap sm:gap-x-5 sm:gap-y-1">
                    @foreach ($leyendasPorcan as $clave => $texto)
                        <li>
                            <span class="font-semibold text-primary-700">{{ $clave }}</span>
                            <span class="text-neutral-400" aria-hidden="true"> — </span>
                            {{ $texto }}
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="se-cuotas-importes-scroll" tabindex="0">
                <table class="se-cuotas-importes-tabla">
                    <colgroup>
                        <col class="se-cii-col-cuota">
                        <col class="se-cii-col-curso">
                        <col class="se-cii-col-importe">
                        @foreach ($gruposVenc as $grupo)
                            <col class="se-cii-col-bon">
                            <col class="se-cii-col-valor">
                            <col class="se-cii-col-porcan">
                        @endforeach
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col" class="se-cii-th se-cii-th-principal">Nombre de la cuota</th>
                            <th scope="col" class="se-cii-th se-cii-th-principal">Sala / Grado / Curso</th>
                            <th scope="col" class="se-cii-th se-cii-th-principal">Importe</th>
                            @foreach ($gruposVenc as $grupo)
                                <th scope="col" colspan="3" class="se-cii-th se-cii-th-venc" title="{{ $grupo['largo'] }}">
                                    <div class="se-cii-venc-head">
                                        <div class="se-cii-venc-title">{{ $grupo['corto'] }}</div>
                                        <div class="se-cii-venc-subs" aria-hidden="true">
                                            <span class="se-cii-venc-sub" title="Bonificación o interés">Bon/Int</span>
                                            <span class="se-cii-venc-sub">Valor</span>
                                            <span class="se-cii-venc-sub">% / $</span>
                                        </div>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody data-se-cii-tbody>
                        @forelse ($filas as $key => $row)
                            <tr class="se-cii-tr" wire:key="cuota-importe-row-{{ $key }}">
                                <td class="se-cii-td se-cii-td-text" title="{{ $cuota->nombre }}">{{ $cuota->nombre }}</td>
                                <td class="se-cii-td se-cii-td-text" title="ID del registro: {{ $row['id'] }}">{{ $row['cursoLabel'] }}</td>
                                <td class="se-cii-td">
                                    <input type="text"
                                           inputmode="decimal"
                                           value="{{ $row['importe'] }}"
                                           wire:key="cii-{{ $key }}-importe"
                                           data-se-cii-nav
                                           data-se-cii-decimal
                                           data-se-cii-row-key="{{ $key }}"
                                           data-se-cii-field="importe"
                                           class="se-cii-input @error('draft.'.$key.'.importe') se-cii-input--err @enderror"
                                           title="Importe base">
                                    @error('draft.'.$key.'.importe')
                                        <div class="se-cii-field-err">{{ $message }}</div>
                                    @enderror
                                </td>
                                @foreach ([1, 2, 3, 4] as $num)
                                    @php
                                        $signo = "signo{$num}v";
                                        $valor = "valor{$num}v";
                                        $porcan = "porcan{$num}v";
                                    @endphp
                                    <td class="se-cii-td se-cii-td-bon">
                                        <select wire:model.live="draft.{{ $key }}.{{ $signo }}"
                                                data-se-cii-nav
                                                class="se-cii-select se-cii-select--signo @error('draft.'.$key.'.'.$signo) se-cii-input--err @enderror"
                                                title="{{ $opcionesSigno[$row[$signo]] ?? '' }}">
                                            @foreach ($opcionesSigno as $val => $etiq)
                                                <option value="{{ $val }}">{{ $val }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="se-cii-td">
                                        <input type="text"
                                               inputmode="decimal"
                                               value="{{ $row[$valor] }}"
                                               wire:key="cii-{{ $key }}-{{ $valor }}"
                                               data-se-cii-nav
                                               data-se-cii-decimal
                                               data-se-cii-valor
                                               data-se-cii-row-key="{{ $key }}"
                                               data-se-cii-field="{{ $valor }}"
                                               class="se-cii-input se-cii-input--center @error('draft.'.$key.'.'.$valor) se-cii-input--err @enderror">
                                        @error('draft.'.$key.'.'.$valor)
                                            <div class="se-cii-field-err">{{ $message }}</div>
                                        @enderror
                                    </td>
                                    <td class="se-cii-td">
                                        <select wire:model.live="draft.{{ $key }}.{{ $porcan }}"
                                                data-se-cii-nav
                                                class="se-cii-select @error('draft.'.$key.'.'.$porcan) se-cii-input--err @enderror">
                                            @foreach ($opcionesPorcan as $val => $etiq)
                                                <option value="{{ $val }}">{{ $etiq }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="se-cii-empty">
                                    @if (trim($search) !== '')
                                        No hay cursos que coincidan con la búsqueda.
                                    @else
                                        No hay importes cargados para esta cuota. Los registros se generan al crear la plantilla o al emitir cuotas masivamente.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            $wire.on('se-swal-exito', (event) => {
                const mensaje = mensajeDeEvento(event, 'Operación realizada correctamente.');
                if (typeof window.seSwalExito === 'function') {
                    window.seSwalExito(mensaje);
                }
            });

            $wire.on('se-swal-error', (event) => {
                const mensaje = mensajeDeEvento(event, 'No se pudo completar la operación.');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                }
            });
        })();
    </script>
    @endscript
</div>
