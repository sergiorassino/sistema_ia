<?php

namespace App\Livewire\CalificacionesInicial;

use App\Models\Matricula;
use App\Support\CalificacionesInicial\CalificacionesInicialObservacionesDatos;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\NivelSistema;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Listado de alumnos del curso del espacio curricular elegido.
 */
class CargaObservacionesInicialAlumnos extends Component
{
    public int $idMateria;

    public string $materiaNombre = '';

    public string $cursoLabel = '';

    public function mount(int $materia): void
    {
        abort_unless(tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA), 403, 'Sin permiso para calificaciones.');
        abort_unless(
            NivelSistema::esInicial((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel inicial.'
        );
        CalificacionesInicialObservacionesDatos::abortSiColumnasInexistentes();

        $ctx = schoolCtx();
        $mat = CalificacionesInicialObservacionesDatos::materiaEnContexto(
            $materia,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );
        abort_if($mat === null, 404);

        $this->idMateria = (int) $mat->id;
        $this->materiaNombre = (string) $mat->materia;
        $this->cursoLabel = (string) $mat->cursoLabel;
    }

    /**
     * @return Collection<int, Matricula>
     */
    public function matriculas(): Collection
    {
        $ctx = schoolCtx();
        $mat = CalificacionesInicialObservacionesDatos::materiaEnContexto(
            $this->idMateria,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );
        if ($mat === null) {
            return collect();
        }

        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );

        return Matricula::query()
            ->with('legajo')
            ->where('idCursos', (int) $mat->idCursos)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereIn('idCondiciones', $idsCondiciones)
            ->whereNull('fechaBaja')
            ->get()
            ->sortBy(function (Matricula $m) {
                $a = mb_strtolower((string) ($m->legajo?->apellido ?? ''));
                $n = mb_strtolower((string) ($m->legajo?->nombre ?? ''));

                return [$a, $n];
            })
            ->values();
    }

    public function render()
    {
        return view('livewire.calificaciones-inicial.carga-observaciones-inicial-alumnos', [
            'matriculas' => $this->matriculas(),
        ])->layout('layouts.app', ['pageTitle' => 'Carga de observaciones — alumnos']);
    }
}
