<?php

namespace App\Livewire\Programas;

use App\Support\DocPp\DocPpConsulta;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Descarga pública de programas de examen (sin login).
 *
 * Lee programas aprobados desde la tabla `doc_pp` (tipo prog, aprobado = 1).
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
     * @return list<int>
     */
    public function aniosDisponibles(): array
    {
        return DocPpConsulta::aniosLectivosSistema();
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
     * @return Collection<int, object>
     */
    public function programas(): Collection
    {
        if ($this->anio === null || ! in_array($this->anio, $this->aniosDisponibles(), true)) {
            return collect();
        }

        return DocPpConsulta::programasPublicosPorAnio($this->anio);
    }

    public function render()
    {
        return view('livewire.programas.programas-examen-publico', [
            'anios' => $this->aniosDisponibles(),
            'programas' => $this->programas(),
        ]);
    }
}
