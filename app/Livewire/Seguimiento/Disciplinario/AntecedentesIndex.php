<?php

namespace App\Livewire\Seguimiento\Disciplinario;

use App\Livewire\Seguimiento\Disciplinario\Concerns\RequiresPermisoSeguimientoDisciplinario;
use App\Models\Matricula;
use App\Models\Sancion;
use App\Support\Navegacion\ContextoEstudianteSesion;
use Illuminate\Support\Collection;
use Livewire\Component;

class AntecedentesIndex extends Component
{
    use RequiresPermisoSeguimientoDisciplinario;

    public int $idMatricula;

    public function mount(): void
    {
        $id = ContextoEstudianteSesion::matricula(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO_ANTECEDENTES);
        abort_if($id === null, 404);
        $this->idMatricula = $id;
    }

    private function matriculaBase(): Matricula
    {
        /** @var Matricula $m */
        $m = Matricula::query()
            ->with(['legajo', 'curso', 'terlec'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->findOrFail($this->idMatricula);

        return $m;
    }

    /** @return Collection<int, Sancion> */
    private function sancionesHistoricas(int $idLegajos): Collection
    {
        return Sancion::query()
            ->with(['tipo', 'profesor', 'matricula.terlec', 'matricula.curso'])
            ->join('matricula', 'matricula.id', '=', 'sanciones.idMatricula')
            ->where('matricula.idLegajos', $idLegajos)
            ->where('matricula.idNivel', schoolCtx()->idNivel)
            ->orderByDesc('matricula.idTerlec')
            ->orderByDesc('sanciones.fecha')
            ->orderByDesc('sanciones.id')
            ->select('sanciones.*')
            ->get();
    }

    public function render()
    {
        $base = $this->matriculaBase();

        $sanciones = $this->sancionesHistoricas((int) $base->idLegajos);
        $porAno = $sanciones->groupBy(fn (Sancion $s) => (int) ($s->matricula?->terlec?->ano ?? 0));

        return view('livewire.seguimiento.disciplinario.antecedentes', [
            'base' => $base,
            'porAno' => $porAno,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Antecedentes disciplinarios']);
    }
}

