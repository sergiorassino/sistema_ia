<?php

namespace App\Livewire\CalificacionesSecundario;

use App\Models\Curso;
use App\Models\Matricula;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Consulta de calificaciones (nivel secundario): elige curso y abre el PDF de boletín.
 * Acceso libre para usuarios autenticados del portal.
 */
class ConsultaCalificacionesSecundario extends Component
{
    /** Curso seleccionado (`cursos.Id`) dentro del contexto de sesión. */
    public ?int $cursoId = null;

    public function updatedCursoId(mixed $value): void
    {
        $this->cursoId = ((int) $value) > 0 ? (int) $value : null;
    }

    /**
     * @return Collection<int, Matricula>
     */
    public function matriculasDelCurso(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        $ctx = schoolCtx();

        $cursoOk = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', (int) $this->cursoId)
            ->exists();

        if (! $cursoOk) {
            return collect();
        }

        return Matricula::query()
            ->with('legajo')
            ->where('idCursos', (int) $this->cursoId)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->get()
            ->sortBy(function (Matricula $m) {
                $a = mb_strtolower((string) ($m->legajo?->apellido ?? ''));
                $n = mb_strtolower((string) ($m->legajo?->nombre ?? ''));

                return [$a, $n];
            })
            ->values();
    }

    public function cursos(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    public function render()
    {
        $cursos = $this->cursos();
        $matriculas = $this->matriculasDelCurso();

        return view('livewire.calificaciones-secundario.consulta-calificaciones-secundario', [
            'cursos' => $cursos,
            'matriculas' => $matriculas,
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Consulta de calificaciones (secundario)']);
    }
}
