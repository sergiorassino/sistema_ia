<?php

namespace App\Livewire\Aspirantes;

use App\Models\Aspicurso;
use App\Models\Aspirante;
use App\Models\Aspiento;
use App\Support\PermisosIaCatalog;
use Livewire\Component;

class InstanciaIndex extends Component
{
    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASPIRANTES_GESTION), 403);

        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            abort(403, 'Contexto incompleto.');
        }
    }

    public function eliminar(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASPIRANTES_GESTION), 403);

        $instancia = $this->instanciaDelNivel($id);

        if (Aspirante::query()->where('idAspiento', (int) $instancia->getKey())->exists()) {
            session()->flash('status', 'No se puede borrar: hay aspirantes registrados en esta instancia.');

            return;
        }

        Aspicurso::query()->where('idAspiento', (int) $instancia->getKey())->delete();
        $instancia->delete();

        session()->flash('status', 'Instancia eliminada.');
    }

    protected function instanciaDelNivel(int $id): Aspiento
    {
        $i = Aspiento::query()->whereKey($id)->firstOrFail();
        abort_unless((int) $i->idNivel === (int) schoolCtx()->idNivel, 403);

        return $i;
    }

    public function render()
    {
        $ctx = schoolCtx();

        $instancias = Aspiento::query()
            ->with('terlec')
            ->withCount('aspirantes')
            ->where('idNivel', $ctx->idNivel)
            ->orderByDesc('Id')
            ->get();

        return view('livewire.aspirantes.instancia-index', [
            'instancias'      => $instancias,
            'idTerlecActivo'  => $ctx->idTerlec,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Aspirantes — Instancias de registro']);
    }
}
