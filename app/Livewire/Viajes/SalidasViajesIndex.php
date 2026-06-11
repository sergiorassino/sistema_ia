<?php

namespace App\Livewire\Viajes;

use App\Models\SalidaViaje;
use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class SalidasViajesIndex extends Component
{
    public string $search = '';

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::VIAJES_SALIDAS_EDUCATIVAS), 403);
        MenuSecretariaPerfil::abortSiNoViajesSalidasEducativas();

        $ctx = schoolCtx();
        if ($ctx->idNivel < 1 || $ctx->idTerlec < 1) {
            abort(403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
        }
    }

    public function eliminarViaje(int $id): void
    {
        $key = 'salidas-viajes:delete:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $viaje = SalidaViaje::queryEnContexto()->findOrFail($id);
        $viaje->delete();
        $this->dispatch('se-swal-exito', mensaje: 'Viaje eliminado.');
    }

    public function render()
    {
        $termino = trim($this->search);

        $viajes = SalidaViaje::queryEnContexto()
            ->when($termino !== '', function ($q) use ($termino) {
                $q->where(function ($sub) use ($termino) {
                    $sub->where('titulo', 'like', '%'.$termino.'%');
                    if (Schema::hasColumn('salidasviajes', 'texto')) {
                        $sub->orWhere('texto', 'like', '%'.$termino.'%');
                    }
                });
            })
            ->orderByDesc('id')
            ->get();

        return view('livewire.viajes.salidas-viajes-index', [
            'viajes' => $viajes,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Salidas educativas']);
    }
}
