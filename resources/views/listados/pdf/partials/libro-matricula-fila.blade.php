@php
    $filaDatos = $fila ?? null;
    $esVacia = (bool) ($filaVacia ?? false);
@endphp
<tr @if($esVacia) class="fila-vacia" @endif>
    @foreach ($libroMatriculaColumnas as $col)
        @php
            $cls = $col['cls'].(($col['nowrap'] ?? false) ? ' col-nowrap' : '');
            $cls .= $col['align'] === 'center' ? ' txt-center' : ' txt-left';
        @endphp
        <td class="{{ $cls }}" style="width: {{ $col['width'] }}">
            @if ($esVacia)
                @if ($loop->first)&nbsp;@endif
            @else
                {{ $filaDatos[$col['field']] ?? '' }}
            @endif
        </td>
    @endforeach
</tr>
