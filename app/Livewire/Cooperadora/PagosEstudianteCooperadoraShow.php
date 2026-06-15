<?php

namespace App\Livewire\Cooperadora;

use App\Models\Matricula;
use App\Support\Cooperadora\BusquedaEstudianteCooperadora;
use App\Support\Cooperadora\PagosEstudianteCooperadoraConsulta;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class PagosEstudianteCooperadoraShow extends Component
{
    public int $idLegajo;

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);

        $idLegajo = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::COOPERADORA_PAGOS_ESTUDIANTE);
        abort_if(
            $idLegajo === null
            || PagosEstudianteCooperadoraConsulta::encabezadoEstudiante($idLegajo) === null,
            404,
        );

        $this->idLegajo = $idLegajo;
    }

    public function alternarHermanoCooperadora(): void
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);

        $key = 'coop:hermano-matricula:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $matricula = BusquedaEstudianteCooperadora::matriculaActiva($this->idLegajo);
        if ($matricula === null) {
            $this->dispatch('se-swal-error', mensaje: 'No hay matrícula activa en el ciclo lectivo actual.');

            return;
        }

        $marcar = ! ((int) ($matricula->coop_es_hermano ?? 0) === 1);

        Matricula::query()
            ->whereKey($matricula->id)
            ->where('idLegajos', $this->idLegajo)
            ->update(['coop_es_hermano' => $marcar ? 1 : 0]);

        $mensaje = $marcar
            ? 'Estudiante marcado como hermano para descuento de cooperadora en este ciclo lectivo.'
            : 'Se quitó la marca de hermano para cooperadora en este ciclo lectivo.';

        $this->dispatch('se-swal-exito', mensaje: $mensaje);
    }

    public function render()
    {
        $filas = PagosEstudianteCooperadoraConsulta::filas($this->idLegajo);

        return view('livewire.cooperadora.pagos-estudiante-show', [
            'encabezado' => PagosEstudianteCooperadoraConsulta::encabezadoEstudiante($this->idLegajo),
            'filas' => $filas,
            'totales' => PagosEstudianteCooperadoraConsulta::totalesDesdeFilas($filas),
        ])->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Pagos del estudiante']);
    }
}
