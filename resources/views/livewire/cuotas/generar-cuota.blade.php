@php
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Cuotas\GeneracionCuotaEstudianteService;
@endphp

<div class="se-page max-w-4xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Gestión de aranceles</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Generar cuota</h1>
                @if ($encabezado)
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/90 sm:text-sm">
                        {{ $encabezado['apellido'] }} {{ $encabezado['nombre'] }}
                        <span class="font-normal text-white/75">— {{ $encabezado['dni'] }}</span>
                    </p>
                    <p class="text-xs text-white/75">
                        {{ $encabezado['curso'] }} · Ciclo {{ $encabezado['terlecAno'] }}
                    </p>
                @endif
            </div>
            <x-volver-cuotas-estudiante
                :id-legajos="$idLegajo"
                class="inline-flex shrink-0 items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20" />
        </div>
    </section>

    @if (! $esRegular)
        <div class="se-card p-6 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-2xl">
            El/La estudiante no es <strong>regular</strong> en el ciclo lectivo activo. Solo se pueden generar cuotas a estudiantes regulares.
        </div>
    @elseif ($plantillas->isEmpty())
        <div class="se-card p-6 text-sm text-neutral-600">
            No hay plantillas de cuota del ciclo {{ $encabezado['terlecAno'] ?? schoolCtx()->terlecAno() }}
            con importe definido para el curso del estudiante.
            Revise los importes en <a href="{{ route('cuotas.importes.index') }}" wire:navigate class="text-primary-700 font-semibold hover:underline">Importes por curso</a>.
        </div>
    @else
        <form wire:submit="generar" class="se-card overflow-hidden p-0">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <p class="text-sm text-neutral-700">
                    Elija la cuota del año que desea generar. Las filas ya generadas no pueden volver a emitirse.
                </p>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-accent-200 bg-accent-50 text-left">
                            <th scope="col" class="w-10 px-3 py-2.5"></th>
                            <th scope="col" class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Cuota</th>
                            <th scope="col" class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Tipo</th>
                            <th scope="col" class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Mes</th>
                            <th scope="col" class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">1.er venc.</th>
                            <th scope="col" class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500 text-right">Importe est.</th>
                            <th scope="col" class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500 w-28">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plantillas as $fila)
                            @php
                                $deshabilitada = $fila['yaGenerada'];
                                $esMatricula = (int) $fila['idCuotastipo'] === GeneracionCuotaEstudianteService::TIPO_MATRICULA;
                            @endphp
                            <tr @class([
                                'border-b border-accent-100',
                                'opacity-50' => $deshabilitada,
                                'bg-primary-50/40' => ! $deshabilitada && $idCuota === $fila['id'],
                            ]) wire:key="plantilla-{{ $fila['id'] }}">
                                <td class="px-3 py-2.5 align-middle text-center">
                                    <input type="radio"
                                           wire:model.live="idCuota"
                                           name="idCuota"
                                           value="{{ $fila['id'] }}"
                                           @disabled($deshabilitada)
                                           class="h-4 w-4 border-accent-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed">
                                </td>
                                <td class="px-3 py-2.5 font-semibold text-primary-900 uppercase">{{ $fila['nombre'] }}</td>
                                <td class="px-3 py-2.5 text-neutral-700">{{ $fila['tipoNombre'] }}</td>
                                <td class="px-3 py-2.5 text-neutral-600">{{ $fila['mes'] }}</td>
                                <td class="px-3 py-2.5 tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearFecha($fila['venc1']) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums whitespace-nowrap font-medium">
                                    {{ CuotasFormato::formatearImporte($fila['importeEstimado']) }}
                                    @if ($esMatricula && $fila['importeEstimado'] < $fila['importeBase'])
                                        <span class="block text-[10px] font-normal text-neutral-500"
                                              title="Descuenta pagos de reservas">
                                            Base {{ CuotasFormato::formatearImporte($fila['importeBase']) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($deshabilitada)
                                        <span class="se-pill bg-neutral-100 text-neutral-600">Ya generada</span>
                                    @else
                                        <span class="se-pill bg-accent-100 text-primary-800">Pendiente</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @error('idCuota')
                <p class="px-4 py-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
                <x-volver-cuotas-estudiante
                    :id-legajos="$idLegajo"
                    etiqueta="Cancelar"
                    class="inline-flex items-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-accent-50" />
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="generar">Generar cuota</span>
                    <span wire:loading wire:target="generar">Generando…</span>
                </button>
            </div>
        </form>
    @endif
</div>
