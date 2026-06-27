<div class="se-matriz-list-card min-h-0">
    <div class="se-cierre-anual-grilla se-matriz-list-grilla se-matriz-list-grilla--unified">
        <div class="se-cierre-anual-body-wrap se-matriz-list-scroll" tabindex="0">
            <table class="se-matriz-list-tabla se-matriz-list-tabla--siro-subida min-w-[108rem] w-full text-xs">
                <thead>
                    <tr class="bg-accent-50 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                        <th class="px-2 py-2 text-right w-10" rowspan="2">#</th>
                        <th class="px-2 py-2 text-left" rowspan="2">Apellido y nombre</th>
                        <th class="px-2 py-2 text-left" rowspan="2">DNI</th>
                        <th class="px-2 py-2 text-left" rowspan="2">Curso</th>
                        <th class="px-2 py-2 text-left" rowspan="2">Cuota</th>
                        <th class="px-2 py-2 text-right" rowspan="2">Año</th>
                        <th class="px-2 py-2 text-right" rowspan="2">Faltapa</th>
                        <th class="px-2 py-2 text-left" rowspan="2">Venc. actualizado</th>
                        <th class="se-siro-subida-th-grupo px-2 py-2 text-center border-l border-accent-200" colspan="3">1.er vencimiento</th>
                        <th class="se-siro-subida-th-grupo px-2 py-2 text-center border-l border-accent-200" colspan="3">2.º vencimiento</th>
                        <th class="se-siro-subida-th-grupo px-2 py-2 text-center border-l border-accent-200" colspan="3">3.er vencimiento</th>
                        <th class="px-2 py-2 text-left border-l border-accent-200" rowspan="2">Obs</th>
                        <th class="se-siro-subida-th-bloq w-9 px-0.5 py-2 text-center" rowspan="2">
                            <span class="block text-[9px] leading-tight">Bloq</span>
                            <span class="block text-[9px] leading-tight">Matr</span>
                        </th>
                        <th class="se-siro-subida-th-bloq w-9 px-0.5 py-2 text-center" rowspan="2">
                            <span class="block text-[9px] leading-tight">Bloq</span>
                            <span class="block text-[9px] leading-tight">Admi</span>
                        </th>
                        <th class="px-2 py-2 text-center border-l border-accent-200" rowspan="2"
                            title="Indica si el registro se incluirá en el archivo para subir a SIRO">SIRO</th>
                    </tr>
                    <tr class="se-siro-subida-th-subfila bg-accent-50/80 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                        <th class="px-2 py-1.5 text-left border-l border-accent-200">Venc. Cuota</th>
                        <th class="px-2 py-1.5 text-left">SIRO fecha</th>
                        <th class="px-2 py-1.5 text-right">SIRO importe</th>
                        <th class="px-2 py-1.5 text-left border-l border-accent-200">Venc. Cuota</th>
                        <th class="px-2 py-1.5 text-left">SIRO fecha</th>
                        <th class="px-2 py-1.5 text-right">SIRO importe</th>
                        <th class="px-2 py-1.5 text-left border-l border-accent-200">Venc. Cuota</th>
                        <th class="px-2 py-1.5 text-left">SIRO fecha</th>
                        <th class="px-2 py-1.5 text-right">SIRO importe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-accent-100">
                    @forelse ($filasGrilla as $i => $fila)
                        <tr wire:key="{{ $filaKeyPrefix }}-{{ $fila['id'] }}"
                            @class([
                                'hover:bg-accent-50/70',
                                'bg-red-50/40' => ! $fila['subeSiro'],
                            ])>
                            <td class="px-2 py-1.5 text-right tabular-nums text-neutral-500">{{ $i + 1 }}</td>
                            <td class="px-2 py-1.5">
                                <span class="se-matriz-list-cell-truncate" title="{{ trim($fila['apellido'].' '.$fila['nombre']) }}">
                                    {{ trim($fila['apellido'].' '.$fila['nombre']) }}
                                </span>
                            </td>
                            <td class="px-2 py-1.5 tabular-nums">{{ $fila['dni'] }}</td>
                            <td class="px-2 py-1.5">
                                <span class="se-matriz-list-cell-truncate" title="{{ $fila['curso'] }}">{{ $fila['curso'] }}</span>
                            </td>
                            <td class="px-2 py-1.5">
                                <span class="se-matriz-list-cell-truncate" title="{{ $fila['cuotaNombre'] }}">{{ $fila['cuotaNombre'] }}</span>
                            </td>
                            <td class="px-2 py-1.5 text-right tabular-nums">{{ $fila['ano'] }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums">{{ $fila['faltapa'] }}</td>
                            <td class="px-2 py-1.5 tabular-nums whitespace-nowrap">{{ $fila['nueVenc'] ?: '—' }}</td>
                            <td class="px-2 py-1.5 tabular-nums border-l border-accent-100 whitespace-nowrap">{{ $fila['venc1'] ?: '—' }}</td>
                            <td class="px-2 py-1.5 tabular-nums whitespace-nowrap">{{ $fila['siroVenc1'] }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums whitespace-nowrap">{{ $fila['siroImporte1'] }}</td>
                            <td class="px-2 py-1.5 tabular-nums border-l border-accent-100 whitespace-nowrap">{{ $fila['venc2'] ?: '—' }}</td>
                            <td class="px-2 py-1.5 tabular-nums whitespace-nowrap">{{ $fila['siroVenc2'] }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums whitespace-nowrap">{{ $fila['siroImporte2'] }}</td>
                            <td class="px-2 py-1.5 tabular-nums border-l border-accent-100 whitespace-nowrap">{{ $fila['venc3'] ?: '—' }}</td>
                            <td class="px-2 py-1.5 tabular-nums whitespace-nowrap">{{ $fila['siroVenc3'] }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums whitespace-nowrap">{{ $fila['siroImporte3'] }}</td>
                            <td class="px-2 py-1.5 max-w-[10rem] truncate border-l border-accent-100" title="{{ $fila['obs'] }}">{{ $fila['obs'] }}</td>
                            <td class="w-9 px-0.5 py-1.5 text-center tabular-nums">{{ $fila['bloqmatr'] }}</td>
                            <td class="w-9 px-0.5 py-1.5 text-center tabular-nums">{{ $fila['bloqadmi'] }}</td>
                            <td class="px-2 py-1.5 text-center border-l border-accent-100">
                                @if ($fila['subeSiro'])
                                    <span class="se-pill bg-primary-100 text-primary-800 text-[10px]" title="Se incluirá en el archivo de subida a SIRO">Sí</span>
                                @else
                                    <span class="se-pill text-[10px]" title="{{ $fila['motivoExclusion'] }}">No</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="21" class="px-4 py-8 text-center text-sm text-neutral-500">
                                No hay registros con los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="se-matriz-list-footer">
            <div class="flex w-full flex-col gap-1 text-sm text-neutral-700 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <p class="text-xs text-neutral-500 tabular-nums">
                    @if (count($filasGrilla) > 0)
                        [1 a {{ count($filasGrilla) }} de {{ count($filasGrilla) }}]
                    @endif
                </p>
                <div class="flex flex-col gap-1 sm:items-end">
                    <p>
                        <span class="font-semibold text-neutral-800">Cantidad de registros para subir a SIRO:</span>
                        <span class="tabular-nums">{{ $cantidadSubeSiro }}</span>
                    </p>
                    <p>
                        <span class="font-semibold text-neutral-800">Cantidad de registros que no suben a SIRO:</span>
                        <span class="tabular-nums">{{ $cantidadNoSubeSiro }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
