<div>
    <label class="form-label">{{ $label ?? 'Estado civil' }}</label>
    <select wire:model="estacivi" class="form-select">
        <option value="">— Seleccione —</option>
        @forelse ($estadosCivilesOpciones ?? [] as $opcion)
            <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
        @empty
            <option value="" disabled>Sin catálogo de estado civil cargado</option>
        @endforelse
    </select>
</div>
