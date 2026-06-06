<div>
    <label class="form-label">{{ $label ?? 'Sexo' }}</label>
    <select wire:model="sexo" class="form-select">
        <option value="">— Seleccione —</option>
        @forelse ($sexosOpciones ?? [] as $opcion)
            <option value="{{ $opcion->id }}">{{ $opcion->sexo }}</option>
        @empty
            <option value="" disabled>Sin catálogo de sexos cargado</option>
        @endforelse
    </select>
</div>
