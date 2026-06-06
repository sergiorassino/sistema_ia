<div class="se-page !max-w-none min-w-0">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="se-soft-card border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Docentes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Inasistencias docentes</h2>
                <p class="text-sm text-white/80">Año lectivo {{ $anoLectivo }} · {{ schoolCtx()->nivelNombre() }}</p>
            </div>
        </div>
    </section>

    <div class="se-toolbar flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <div class="relative min-w-0 flex-1 sm:min-w-[12rem]">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar apellido, nombre o DNI…" class="form-input w-full">
        </div>
        <a href="{{ route('docentes.inasistencias.envio-masivo') }}"
           class="btn-secondary shrink-0 text-center text-[10px] leading-tight uppercase tracking-wide">
            Envío masivo por correo
        </a>
        <a href="{{ route('docentes.inasistencias.ranking') }}"
           class="btn-secondary shrink-0 text-center text-[10px] leading-tight uppercase tracking-wide lg:max-w-[11rem]">
            Ranking de inasistencias por materia y curso
        </a>
        <p class="shrink-0 text-[10px] leading-snug text-neutral-600 lg:max-w-[14rem] w-full sm:w-auto">
            <strong>T</strong> total · <strong>J/I</strong> justif./injust. · <strong>Mx</strong> máx. · <strong>D</strong> desc.
        </p>
    </div>

    <div class="se-card min-w-0 overflow-hidden">
        <table class="se-inas-docentes-table">
            <colgroup>
                <col style="width: 5%">
                <col style="width: 18%">
                <col style="width: 7%">
                <col style="width: 19%">
                <col style="width: 6%">
                <col style="width: 7.5%">
                <col style="width: 7.5%">
                <col style="width: 7.5%">
                <col style="width: 7.5%">
                <col style="width: 7.5%">
                <col style="width: 7.5%">
            </colgroup>
            <thead class="bg-accent-50">
                <tr>
                    <th class="table-header text-center leading-tight">Cargo</th>
                    <th class="table-header">Docente</th>
                    <th class="table-header text-center">DNI</th>
                    <th class="table-header">Horas por cargo</th>
                    <th class="table-header text-center leading-tight">
                        <span class="block">Inasist.</span>
                        <span class="block">del año</span>
                    </th>
                    @foreach ($bimestres as $b)
                        <th class="table-header text-center" title="{{ $b['titulo'] }}">{{ $b['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-accent-200 bg-white">
                @forelse ($profesores as $p)
                    @php
                        $idProf = (int) $p->id;
                        $cargos = $cargosPorProfesor[$idProf] ?? [];
                        $cargosTexto = collect($cargos)->map(function ($c) {
                            $n = ! empty($c['nivel']) ? $c['nivel'].': ' : '';
                            return $n.$c['cargo'].' '.$c['cant'].'h';
                        })->implode(' · ');
                    @endphp
                    <tr id="profesor-{{ $p->id }}" class="hover:bg-accent-50/60 align-top">
                        <td class="table-cell text-center">
                            @if (\App\Support\InasistenciasDocentes::tieneCargos())
                                <a href="{{ route('docentes.inasistencias.cargos', $p->id) }}" class="btn-secondary btn-sm se-inas-doc-btn leading-tight">CARGO</a>
                            @endif
                        </td>
                        <td class="table-cell max-w-0 truncate font-semibold text-neutral-900" title="{{ $p->apellido }} {{ $p->nombre }}">
                            {{ $p->apellido }} {{ $p->nombre }}
                        </td>
                        <td class="table-cell text-center font-mono tabular-nums">{{ number_format((float) $p->dni, 0, ',', '.') }}</td>
                        <td class="table-cell max-w-0 truncate text-[10px] leading-snug text-neutral-700" title="{{ $cargosTexto }}">
                            @forelse ($cargos as $c)
                                @if (! empty($c['nivel']))
                                    <span class="text-neutral-500">{{ $c['nivel'] }}:</span>
                                @endif
                                {{ $c['cargo'] }} {{ $c['cant'] }}h
                                @if (! $loop->last)
                                    <br>
                                @endif
                            @empty
                                —
                            @endforelse
                        </td>
                        <td class="table-cell text-center">
                            <a href="{{ route('docentes.inasistencias.show', $p->id) }}" class="btn-primary btn-sm se-inas-doc-btn mx-auto leading-[1.15]">
                                <span class="block">INASIST.</span>
                                <span class="block">DEL AÑO</span>
                            </a>
                        </td>
                        @foreach ($bimestres as $num => $b)
                            @php
                                $res = $resumenPorProfesor[$idProf][$num] ?? ['total' => 0, 'justificadas' => 0, 'injustificadas' => 0, 'maxFaltasPosibles' => 0, 'totalDescuento' => 0, 'tieneFaltasDescuento' => false];
                                $desc = ($res['totalDescuento'] ?? 0) > 0;
                            @endphp
                            <td class="table-cell text-center {{ $desc ? 'bg-red-300 ring-2 ring-inset ring-red-500' : 'bg-accent-50/80' }}">
                                <a href="{{ route('docentes.inasistencias.informe', ['idProfesor' => $p->id, 'bimestre' => $num, 'anio' => $anoLectivo]) }}"
                                   class="block rounded px-0.5 py-0.5 font-medium leading-[1.2] tabular-nums hover:text-primary-700 {{ $desc ? 'text-red-950 hover:text-red-900' : 'text-neutral-800' }}"
                                   title="{{ $b['titulo'] }} — T {{ (int) $res['total'] }}, J {{ (int) $res['justificadas'] }}, I {{ (int) $res['injustificadas'] }}, Mx {{ (int) $res['maxFaltasPosibles'] }}, D {{ (int) $res['totalDescuento'] }}">
                                    <span class="block">T{{ (int) $res['total'] }}</span>
                                    <span class="block text-[10px]">J{{ (int) $res['justificadas'] }} I{{ (int) $res['injustificadas'] }}</span>
                                    <span class="block text-[10px]">Mx{{ (int) $res['maxFaltasPosibles'] }} D{{ (int) $res['totalDescuento'] }}</span>
                                </a>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 5 + count($bimestres) }}" class="table-cell py-10 text-center text-neutral-500">
                            No hay docentes que cumplan el filtro en este nivel.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
