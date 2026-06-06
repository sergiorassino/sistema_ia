<colgroup>
    @foreach ($libroMatriculaColumnas as $col)
        <col class="{{ $col['cls'] }}" style="width: {{ $col['width'] }}">
    @endforeach
</colgroup>
