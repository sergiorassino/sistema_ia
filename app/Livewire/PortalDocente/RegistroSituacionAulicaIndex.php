<?php

namespace App\Livewire\PortalDocente;

use App\Support\PortalDocente\CuadernoSeguimientoAulicoDocente;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Alumnos del curso/materia — acceso al detalle de situación áulica por alumno.
 */
class RegistroSituacionAulicaIndex extends Component
{
    public int $cursoId;

    public int $materiaId;

    public string $materiaNombre = '';

    public string $cursoLabel = '';

    public function mount(int $curso, int $materia): void
    {
        CuadernoSeguimientoAulicoDocente::abortSiNoHabilitadoEnTenant();
        CuadernoSeguimientoAulicoDocente::abortSiNoEsSecundario();
        CuadernoSeguimientoAulicoDocente::abortSiProfesorSinMateria($materia, $curso);

        $this->cursoId = $curso;
        $this->materiaId = $materia;

        $ctx = schoolCtx();
        $fila = DB::table('materias as m')
            ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('m.id', $materia)
            ->where('m.idCursos', $curso)
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->first(['m.materia', 'c.cursec']);

        abort_if($fila === null, 404);

        $this->materiaNombre = trim((string) ($fila->materia ?? ''));
        $sec = trim((string) ($fila->cursec ?? ''));
        $this->cursoLabel = $sec !== '' ? $sec : ('Curso '.$curso);
    }

    public function render()
    {
        $alumnos = CuadernoSeguimientoAulicoDocente::alumnosDelCurso($this->cursoId);

        return view('livewire.portal-docente.registro-situacion-aulica-index', [
            'alumnos' => $alumnos,
        ])->layout('layouts.docente', ['pageTitle' => 'Registro de Situación Áulica']);
    }
}
