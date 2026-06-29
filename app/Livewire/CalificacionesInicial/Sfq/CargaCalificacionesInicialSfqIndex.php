<?php

namespace App\Livewire\CalificacionesInicial\Sfq;

use App\Models\Curso;
use App\Support\CalificacionesInicial\CalificacionesInicialModulos;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqCatalogo;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqDatos;
use App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * SFQ — selección de curso y grilla de alumnos (ic01–ic06 + observaciones).
 */
class CargaCalificacionesInicialSfqIndex extends Component
{
    public ?int $cursoId = null;

    public bool $modoPortalDocente = false;

    public function mount(): void
    {
        CalificacionesInicialModulos::abortSiImplementacionInactiva(
            CalificacionesInicialModulos::CARGA_NOTAS,
            CalificacionesInicialSfqCatalogo::IMPLEMENTACION,
        );

        CalificacionesInicialSfqDatos::abortSiEsquemaIncompleto();

        $this->modoPortalDocente = CalificacionesInicialSfqPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            CalificacionesInicialSfqPortalDocente::abortSiMenuInactivo();
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                \App\Support\PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para cargar calificaciones.',
            );
        }

        CalificacionesInicialSfqPortalDocente::abortSiNoEsInicial();

        $curso = (int) request()->query('curso', 0);
        if ($curso > 0) {
            if ($this->modoPortalDocente) {
                CalificacionesInicialSfqPortalDocente::abortSiProfesorSinCurso($curso);
            }
            $this->cursoId = $curso;
        }
    }

    public function updatedCursoId(mixed $value): void
    {
        $id = ((int) $value) > 0 ? (int) $value : null;
        if ($id !== null && $this->modoPortalDocente) {
            CalificacionesInicialSfqPortalDocente::abortSiProfesorSinCurso($id);
        }
        $this->cursoId = $id;
    }

    /**
     * @return Collection<int, array{
     *     matricula: \App\Models\Matricula,
     *     ic: array<string, bool>,
     *     observaciones: bool
     * }>
     */
    public function filasGrilla(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        $materia = CalificacionesInicialSfqDatos::materiaPrincipalCurso((int) $this->cursoId);
        if ($materia === null) {
            return collect();
        }

        $matriculas = CalificacionesInicialSfqDatos::matriculasRegularesCurso((int) $this->cursoId);

        return $matriculas->map(function ($mat) use ($materia) {
            $fila = CalificacionesInicialSfqDatos::filaCalificaciones((int) $mat->id, (int) $materia->ord);

            $ic = [];
            foreach (CalificacionesInicialSfqCatalogo::CAMPOS_IC as $campo) {
                $valor = $fila !== null ? (string) ($fila->{$campo} ?? '') : '';
                $ic[$campo] = CalificacionesInicialSfqDatos::icTieneDatos($valor);
            }

            return [
                'matricula' => $mat,
                'ic' => $ic,
                'observaciones' => CalificacionesInicialSfqDatos::observacionesTienenDatos($fila),
            ];
        });
    }

    public function cursos(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->when($this->modoPortalDocente, fn ($q) => $q->whereIn('Id', CalificacionesInicialSfqPortalDocente::idsCursosAsignados()))
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    public function render()
    {
        return view('livewire.calificaciones-inicial.sfq.carga-index', [
            'cursos' => $this->cursos(),
            'filas' => $this->filasGrilla(),
            'etiquetasColumna' => CalificacionesInicialSfqCatalogo::ETIQUETAS_COLUMNA,
            'columnasGrilla' => CalificacionesInicialSfqCatalogo::COLUMNAS_GRILLA_CARGA,
            'anchoColumnaIcono' => CalificacionesInicialSfqCatalogo::anchoColumnaIconoCss(),
        ])->layout(CalificacionesInicialSfqPortalDocente::layout(), [
            'pageTitle' => 'Carga de calificaciones (Inicial SFQ)',
        ]);
    }
}
