<thead>
    <tr class="fila-columnas">
        @foreach ($libroMatriculaColumnas as $col)
            <th
                class="{{ $col['cls'] }}{{ ($col['nowrap'] ?? false) ? ' col-nowrap' : '' }}"
                style="width: {{ $col['width'] }}"
            >{{ $col['label'] }}</th>
        @endforeach
    </tr>
</thead>
