<?php

namespace App\Livewire\Programas;

use App\Support\PlanificacionesProgramas\PlanificacionesProgramasConsulta;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Descarga pública de programas de examen (sin login).
 *
 * Lee programas aprobados desde la tabla `materias` (pp_prog / pp_aprobProg).
 * El año ofrecido es el ciclo lectivo activo del sistema (`ento.idTerlecVerNotas`).
 */
#[Layout('layouts.programas-examen')]
class ProgramasExamenPublico extends Component
{
    public ?int $anio = null;

    public function mount(): void
    {
        abort_unless(tenantProgramasExamenHabilitado(), 404);
    }

    /**
     * Años lectivos del sistema, del más reciente al más antiguo.
     *
     * @return list<int>
     */
    public function aniosDisponibles(): array
    {
        return PlanificacionesProgramasConsulta::aniosLectivosSistema();
    }

    public function elegirAnio(int $anio): void
    {
        if (in_array($anio, $this->aniosDisponibles(), true)) {
            $this->anio = $anio;
        }
    }

    public function volver(): void
    {
        $this->anio = null;
    }

    /**
     * Programas aprobados del año elegido.
     *
     * @return Collection<int, object>
     */
    public function programas(): Collection
    {
        if ($this->anio === null || ! in_array($this->anio, $this->aniosDisponibles(), true)) {
            return collect();
        }

        if (PlanificacionesProgramasConsulta::columnasFaltantes() !== []) {
            return collect();
        }

        return PlanificacionesProgramasConsulta::filasPublicasPorAnio($this->anio)
            ->map(function (object $fila) {
                $cursec = PlanificacionesProgramasConsulta::etiquetaCurso($fila);
                $partes = preg_split('/\s+/', trim($cursec), 2);
                $fila->curso = $partes[0] ?? $cursec;
                $fila->seccion = $partes[1] ?? '';
                $fila->nombreMateria = (string) $fila->materia;

                return $fila;
            });
    }

    public function render()
    {
        return view('livewire.programas.programas-examen-publico', [
            'anios' => $this->aniosDisponibles(),
            'programas' => $this->programas(),
        ]);
    }
}
