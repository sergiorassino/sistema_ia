@php
    $idFamilia = (int) $familia->id;
    $keyFila = (string) $idFamilia;
@endphp
@if ($puedeEditar)
    <td @if ($span > 1) rowspan="{{ $span }}" @endif class="table-cell se-lf-familia-grupo">
        <input wire:model.blur="filas.{{ $keyFila }}.apellido"
               wire:blur="guardarFamilia({{ $idFamilia }})"
               id="fam-{{ $idFamilia }}-apellido"
               type="text"
               maxlength="50"
               autocomplete="off"
               aria-label="Familia"
               class="form-input se-lf-campo py-0.5 text-xs w-full @error('filas.'.$keyFila.'.apellido') border-red-400 @enderror">
        @error('filas.'.$keyFila.'.apellido')
            <p class="form-error" title="{{ $message }}">{{ $message }}</p>
        @enderror
    </td>
    <td @if ($span > 1) rowspan="{{ $span }}" @endif class="table-cell se-lf-familia-grupo">
        <input wire:model.blur="filas.{{ $keyFila }}.responsable"
               wire:blur="guardarFamilia({{ $idFamilia }})"
               id="fam-{{ $idFamilia }}-responsable"
               type="text"
               maxlength="100"
               autocomplete="off"
               aria-label="Responsable"
               class="form-input se-lf-campo py-0.5 text-xs w-full @error('filas.'.$keyFila.'.responsable') border-red-400 @enderror">
        @error('filas.'.$keyFila.'.responsable')
            <p class="form-error" title="{{ $message }}">{{ $message }}</p>
        @enderror
    </td>
    @if ($tieneDniResp)
        <td @if ($span > 1) rowspan="{{ $span }}" @endif class="table-cell se-lf-familia-grupo se-lf-dni">
            <input wire:model.blur="filas.{{ $keyFila }}.dniResp"
                   wire:blur="guardarFamilia({{ $idFamilia }})"
                   x-on:blur="
                       const d = String($el.value).replace(/\D/g, '').slice(0, 11);
                       $el.value = d.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                   "
                   id="fam-{{ $idFamilia }}-dni"
                   type="text"
                   inputmode="numeric"
                   maxlength="14"
                   autocomplete="off"
                   aria-label="DNI del responsable"
                   class="form-input se-lf-campo py-0.5 text-xs w-full tabular-nums @error('filas.'.$keyFila.'.dniResp') border-red-400 @enderror">
            @error('filas.'.$keyFila.'.dniResp')
                <p class="form-error" title="{{ $message }}">{{ $message }}</p>
            @enderror
        </td>
    @endif
    <td @if ($span > 1) rowspan="{{ $span }}" @endif class="table-cell se-lf-familia-grupo">
        <input wire:model.blur="filas.{{ $keyFila }}.email"
               wire:blur="guardarFamilia({{ $idFamilia }})"
               id="fam-{{ $idFamilia }}-email"
               type="email"
               maxlength="150"
               autocomplete="off"
               aria-label="Email de la familia"
               class="form-input se-lf-campo py-0.5 text-xs w-full @error('filas.'.$keyFila.'.email') border-red-400 @enderror">
        @error('filas.'.$keyFila.'.email')
            <p class="form-error" title="{{ $message }}">{{ $message }}</p>
        @enderror
    </td>
@else
    <td @if ($span > 1) rowspan="{{ $span }}" @endif class="table-cell se-lf-familia-grupo font-medium text-neutral-900">{{ $etiquetaFamilia !== '' ? $etiquetaFamilia : '—' }}</td>
    <td @if ($span > 1) rowspan="{{ $span }}" @endif class="table-cell se-lf-familia-grupo text-neutral-800">{{ $etiquetaResponsable !== '' ? $etiquetaResponsable : '—' }}</td>
    @if ($tieneDniResp)
        <td @if ($span > 1) rowspan="{{ $span }}" @endif class="table-cell se-lf-familia-grupo se-lf-dni text-neutral-700">{{ $dniResp !== '' ? $dniResp : '—' }}</td>
    @endif
    <td @if ($span > 1) rowspan="{{ $span }}" @endif class="table-cell se-lf-familia-grupo break-all text-neutral-700">{{ $email !== '' ? $email : '—' }}</td>
@endif
