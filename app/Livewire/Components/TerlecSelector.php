<?php

namespace App\Livewire\Components;

use App\Models\Terlec;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Selector de año lectivo aislado: no se reordena al re-renderizar el formulario padre (p. ej. login con DNI live).
 */
class TerlecSelector extends Component
{
    #[Modelable]
    public int|string $value = '';

    public string $emptyLabel = '— Seleccione año —';

    public string $emptyValue = '';

    public string $inputId = 'idTerlec';

    public string $selectClass = 'form-select';

    public function render()
    {
        return view('livewire.components.terlec-selector', [
            'terlecs' => Terlec::paraSelector(),
        ]);
    }
}
