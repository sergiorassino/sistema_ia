<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\MateriasAdeudadasAlumnosListado;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Livewire\Attributes\On;
use Livewire\Component;

class MateriasAdeudadasGestionIndex extends Component
{
    use RequiresPermisoExamenes;

    public string $buscar = '';

    /** Incrementa al confirmar el panel hijo para refrescar el listado de alumnos. */
    public int $prepTick = 0;

    public function render()
    {
        $ctx = schoolCtx();
        $esSecundario = MateriasAdeudadasAlumnosListado::esNivelSecundario($ctx);
        $preparacionLista = $ctx->isValid()
            && MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(MateriasAdeudadasPreparacion::MODULO_GESTION);

        $alumnos = [];
        if ($esSecundario && $preparacionLista) {
            $alumnos = MateriasAdeudadasAlumnosListado::alumnos(
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
                $this->buscar !== '' ? $this->buscar : null,
            );
        }

        return view('livewire.examenes.materias-adeudadas-gestion', [
            'alumnos' => $alumnos,
            'totalAlumnos' => count($alumnos),
            'esSecundario' => $esSecundario,
            'preparacionLista' => $preparacionLista,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de materias adeudadas']);
    }

    #[On('materias-adeudadas-preparacion-confirmada')]
    public function onPreparacionConfirmada(string $modulo): void
    {
        if ($modulo === MateriasAdeudadasPreparacion::MODULO_GESTION) {
            $this->prepTick++;
        }
    }
}
