<?php

namespace App\Livewire\CalificacionesInicial;

use App\Livewire\Concerns\BloqueoEntradaCargaNotasOffSecretaria;
use App\Models\Matricula;
use App\Support\CalificacionesInicial\CalificacionesInicialObservacionesDatos;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\CalificacionesInicialPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Listado de alumnos del curso del espacio curricular elegido.
 */
class CargaObservacionesInicialAlumnos extends Component
{
    use BloqueoEntradaCargaNotasOffSecretaria;

    public bool $modoPortalDocente = false;

    public int $idMateria;

    public string $materiaNombre = '';

    public string $cursoLabel = '';

    public function mount(int $materia): void
    {
        $this->modoPortalDocente = CalificacionesInicialPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiMenuInactivo(CalificacionesInicialPortalDocente::MENU_OBSERVACIONES);
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para calificaciones.',
            );
        }

        CalificacionesInicialPortalDocente::abortSiNoEsInicial();
        CalificacionesInicialObservacionesDatos::abortSiColumnasInexistentes();

        $this->redirigirSiSecretariaCargaNotasOff($this->modoPortalDocente);

        $ctx = schoolCtx();
        $mat = CalificacionesInicialObservacionesDatos::materiaEnContexto(
            $materia,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );
        abort_if($mat === null, 404);

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiProfesorSinMateria(
                (int) $mat->id,
                (int) $mat->idCursos,
            );
        }

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
        ])->layout(CalificacionesInicialPortalDocente::layout(), ['pageTitle' => 'Carga de observaciones — alumnos']);
    }
}
