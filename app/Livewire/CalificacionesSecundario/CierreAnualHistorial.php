<?php

namespace App\Livewire\CalificacionesSecundario;

use App\Support\CalificacionesSecundario\CierreAnualSecundario;
use App\Support\Examenes\MateriasAdeudadasCargaManual;
use Livewire\Component;

/**
 * Historial de calificaciones del alumno (consulta por legajo).
 */
class CierreAnualHistorial extends Component
{
    public int $idLegajos = 0;

    /** @var array<string, string> */
    public array $alumno = [];

    public function mount(): void
    {
        $idLegajos = \App\Support\Navegacion\ContextoEstudianteSesion::legajo(
            \App\Support\Navegacion\ContextoEstudianteSesion::CIERRE_ANUAL_SECUNDARIO,
        );
        abort_if($idLegajos === null, 404);

        abort_unless(tienePermiso(15), 403, 'Sin permiso para cierre anual.');

        $ctx = schoolCtx();
        if (! str_contains(mb_strtolower($ctx->nivelNombre()), 'secundari')) {
            abort(403, 'Este módulo requiere contexto de Secundario.');
        }

        $alumno = MateriasAdeudadasCargaManual::alumnoEnGestion(
            $idLegajos,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );

        if ($alumno === null) {
            $lista = CierreAnualSecundario::matriculasDelAnio((int) $ctx->idNivel, (int) $ctx->idTerlec);
            $encontrado = collect($lista)->firstWhere('idLegajos', $idLegajos);
            if ($encontrado === null) {
                abort(404, 'Alumno no encontrado en la matrícula del ciclo lectivo actual.');
            }
            $alumno = [
                'idLegajos' => $encontrado['idLegajos'],
                'idMatricula' => $encontrado['idMatricula'],
                'apellido' => $encontrado['apellido'],
                'nombre' => $encontrado['nombre'],
                'dni' => $encontrado['dni'],
                'curso' => $encontrado['curso'],
            ];
        }

        $this->idLegajos = $idLegajos;
        $this->alumno = $alumno;
    }

    public function render()
    {
        $ctx = schoolCtx();
        $filas = CierreAnualSecundario::historialAlumno(
            $this->idLegajos,
            (int) $ctx->idNivel,
            (string) ($this->alumno['apellido'] ?? ''),
            (string) ($this->alumno['nombre'] ?? ''),
        );

        return view('livewire.calificaciones-secundario.cierre-anual-historial', [
            'filas' => $filas,
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Historial · Cierre anual']);
    }
}
