<?php

namespace App\Livewire\Abm\Curplan;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Models\Curplan;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class CurplanIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::CURSOS_MATERIAS_PLAN;
    }

    public bool $showConfirm = false;

    public ?int $deleteId = null;
    public string $deleteInfo = '';
    public function confirmDelete(int $id): void
    {
        $c = Curplan::with('plan')->findOrFail($id);

        $countCursos = DB::table('cursos')->where('idCurPlan', $id)->count();
        $countMaterias = DB::table('matplan')->where('idCurPlan', $id)->count();

        $total = $countCursos + $countMaterias;

        if ($total > 0) {
            $detail = collect([
                $countCursos ? "{$countCursos} cursos" : null,
                $countMaterias ? "{$countMaterias} materias" : null,
            ])->filter()->implode(', ');

            $this->deleteInfo = "No se puede eliminar \"{$c->curPlanCurso}\" porque tiene: {$detail}.";
            $this->deleteId = null;
        } else {
            $planLabel = trim((string) ($c->plan?->abrev ?? $c->plan?->plan ?? ''));
            $extra = $planLabel !== '' ? " (Plan {$planLabel})" : '';
            $this->deleteId = $id;
            $this->deleteInfo = "¿Confirma eliminar el curso modelo \"{$c->curPlanCurso}\"{$extra}?";
        }

        $this->showConfirm = true;
    }

    public function delete(): void
    {
        $key = 'curplan:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirm = false;
            $this->reset('deleteId', 'deleteInfo');
            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId) {
            $c = Curplan::findOrFail($this->deleteId);
            $nombre = (string) $c->curPlanCurso;
            $c->delete();
            session()->flash('success', "Curso modelo \"{$nombre}\" eliminado.");
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

        $curplanes = Curplan::query()
            ->with('plan')
            ->whereIn('idPlan', $planes->pluck('id'))
            ->orderBy('idPlan')
            ->orderBy('curPlanCurso')
            ->get();

        return view('livewire.abm.curplan.index', compact('curplanes', 'planes'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Cursos modelo (CurPlan)']);
    }
}

