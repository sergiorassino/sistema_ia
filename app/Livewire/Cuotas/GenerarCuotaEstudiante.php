<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\GeneracionCuotaEstudianteService;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Selección de plantilla del año y generación en cuotasgeneradas.
 */
class GenerarCuotaEstudiante extends Component
{
    public int $idLegajo;

    public int $idCuota = 0;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $idLegajo = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::CUOTAS_GESTION);
        abort_if($idLegajo === null || GestionAranceles::legajoParaGestion($idLegajo) === null, 404);

        $this->idLegajo = $idLegajo;
    }

    public function generar(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $rateKey = 'cuotas:generar:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 15)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $this->validate([
            'idCuota' => ['required', 'integer', 'min:1'],
        ], [
            'idCuota.required' => 'Seleccione la cuota que desea generar.',
            'idCuota.min' => 'Seleccione la cuota que desea generar.',
        ]);

        $resultado = GeneracionCuotaEstudianteService::generar($this->idLegajo, $this->idCuota);

        if (! $resultado->exito) {
            $this->dispatch('se-swal-error', mensaje: $resultado->mensaje);

            return;
        }

        session()->flash('success', $resultado->mensaje);
        $this->redirectRoute('cuotas.estudiante', navigate: true);
    }

    public function render()
    {
        $esRegular = GeneracionCuotaEstudianteService::esEstudianteRegular($this->idLegajo);
        $plantillas = $esRegular
            ? GeneracionCuotaEstudianteService::plantillasDelCiclo($this->idLegajo)
            : collect();

        return view('livewire.cuotas.generar-cuota', [
            'encabezado' => GestionAranceles::encabezadoEstudiante($this->idLegajo),
            'esRegular' => $esRegular,
            'plantillas' => $plantillas,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Generar cuota']);
    }
}
