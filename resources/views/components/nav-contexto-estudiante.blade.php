@props([
    'destino',
    'alcance',
    'matricula' => null,
    'curso' => null,
    'idLegajos' => null,
    'idCuotaGenerada' => null,
    'idCuotaPago' => null,
    'materia' => null,
    'tipo' => null,
    'desde' => null,
    'hasta' => null,
    'abrirMatriculas' => false,
    'buscar' => null,
    'vistaCuotas' => null,
    'tag' => 'button',
])

@php
    $tag = in_array($tag, ['button', 'a'], true) ? $tag : 'button';
@endphp

<form method="POST"
      action="{{ route('navegacion.contexto-estudiante') }}"
      {{ $attributes->class(['inline']) }}>
    @csrf
    <input type="hidden" name="destino" value="{{ $destino }}">
    <input type="hidden" name="alcance" value="{{ $alcance }}">
    @if ($matricula)
        <input type="hidden" name="matricula" value="{{ (int) $matricula }}">
    @endif
    @if ($curso)
        <input type="hidden" name="curso" value="{{ (int) $curso }}">
    @endif
    @if ($idLegajos)
        <input type="hidden" name="idLegajos" value="{{ (int) $idLegajos }}">
    @endif
    @if ($idCuotaGenerada)
        <input type="hidden" name="idCuotaGenerada" value="{{ (int) $idCuotaGenerada }}">
    @endif
    @if ($idCuotaPago)
        <input type="hidden" name="idCuotaPago" value="{{ (int) $idCuotaPago }}">
    @endif
    @if ($materia)
        <input type="hidden" name="materia" value="{{ (int) $materia }}">
    @endif
    @if ($tipo !== null && $tipo !== '')
        <input type="hidden" name="tipo" value="{{ $tipo }}">
    @endif
    @if ($desde)
        <input type="hidden" name="desde" value="{{ $desde }}">
    @endif
    @if ($hasta)
        <input type="hidden" name="hasta" value="{{ $hasta }}">
    @endif
    @if ($abrirMatriculas)
        <input type="hidden" name="abrir_matriculas" value="1">
    @endif
    @if ($buscar !== null && trim((string) $buscar) !== '')
        <input type="hidden" name="buscar" value="{{ trim((string) $buscar) }}">
    @endif
    @if ($vistaCuotas !== null && in_array($vistaCuotas, ['anio', 'historial'], true))
        <input type="hidden" name="vista_cuotas" value="{{ $vistaCuotas }}">
    @endif
    @if ($tag === 'a')
        <button type="submit" class="inline p-0 border-0 bg-transparent font-inherit text-inherit cursor-pointer">
            {{ $slot }}
        </button>
    @else
        <button type="submit">
            {{ $slot }}
        </button>
    @endif
</form>
