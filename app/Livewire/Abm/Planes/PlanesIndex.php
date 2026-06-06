<?php

namespace App\Livewire\Abm\Planes;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class PlanesIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::PLANES_ESTUDIO;
    }

    public bool $showConfirm = false;

    public ?int $deleteId = null;
    public string $deleteInfo = '';

    public function confirmDelete(int $id): void
    {
        $ctx = schoolCtx();

        $plan = Plan::query()
            ->where('idNivel', $ctx->idNivel)
            ->findOrFail($id);

        $countCurplanes = DB::table('curplan')->where('idPlan', $id)->count();

        if ($countCurplanes > 0) {
            $this->deleteInfo = "No se puede eliminar \"{$plan->plan}\" porque tiene {$countCurplanes} cursos modelo asociados.";
            $this->deleteId = null;
        } else {
            $abrev = trim((string) ($plan->abrev ?? ''));
            $extra = $abrev !== '' ? " ({$abrev})" : '';
            $this->deleteId = $id;
            $this->deleteInfo = "¿Confirma eliminar el plan \"{$plan->plan}\"{$extra}?";
        }

        $this->showConfirm = true;
    }

    public function delete(): void
    {
        $key = 'planes:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirm = false;
            $this->reset('deleteId', 'deleteInfo');
            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId) {
            $ctx = schoolCtx();
            $plan = Plan::query()
                ->where('idNivel', $ctx->idNivel)
                ->findOrFail($this->deleteId);

            $nombre = (string) $plan->plan;
            $plan->delete();
            session()->flash('success', "Plan \"{$nombre}\" eliminado.");
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function render()
    {
        $ctx = schoolCtx();

        $planes = Plan::query()
            ->where('idNivel', $ctx->idNivel)
            ->orderBy('id')
            ->get(['id', 'plan', 'abrev']);

        return view('livewire.abm.planes.index', compact('planes'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de planes de estudio']);
    }
}

