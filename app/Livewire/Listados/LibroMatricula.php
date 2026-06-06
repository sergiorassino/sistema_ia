<?php

namespace App\Livewire\Listados;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LibroMatricula extends Component
{
    public string $inscriptosAl = '';

    public function mount(): void
    {
        $this->inscriptosAl = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'inscriptosAl' => ['required', 'date'],
        ];
    }

    public function render()
    {
        return view('listados::livewire.listados.libro-matricula');
    }
}
