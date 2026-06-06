<div>
    <select wire:model="value"
            id="{{ $inputId }}"
            @class([$selectClass])>
        <option value="{{ $emptyValue }}">{{ $emptyLabel }}</option>
        @foreach ($terlecs as $t)
            <option value="{{ $t->id }}">{{ $t->ano }}</option>
        @endforeach
    </select>
</div>
