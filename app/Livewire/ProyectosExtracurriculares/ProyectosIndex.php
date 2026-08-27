<?php

namespace App\Livewire\ProyectosExtracurriculares;

use App\Support\ProyectosExtracurriculares\ExtActividadesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class ProyectosIndex extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    public string $buscar = '';

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function eliminar(int $id): void
    {
        if (! RateLimiter::attempt('ext-proy-del-'.(auth()->id() ?? 0), 20, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        ExtActividadesService::eliminar($id, (int) Auth::id());
        $this->dispatch('se-swal-exito', mensaje: 'Proyecto eliminado.');
        $this->resetPage();
    }

    public function render()
    {
        $tablasOk = ExtActividadesService::tablasDisponibles();
        $q = null;
        if ($tablasOk) {
            $q = ExtActividadesService::scopedQuery((int) Auth::id())
                ->with(['fechas', 'tipoRegistro'])
                ->orderByDesc('id');
            $t = trim($this->buscar);
            if ($t !== '') {
                $q->where('nombre', 'like', '%'.$t.'%');
            }
        }

        return view('livewire.proyectos-extracurriculares.index-docente', [
            'tablasOk' => $tablasOk,
            'mensajeTabla' => $tablasOk ? '' : ExtActividadesService::mensajeTablasFaltantes(),
            'registros' => $tablasOk ? $q->paginate(self::POR_PAGINA) : null,
        ])->layout('layouts.docente', ['pageTitle' => 'Proyectos extracurriculares']);
    }
}
