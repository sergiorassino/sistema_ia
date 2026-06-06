<?php



namespace App\Livewire\Examenes;



use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\MateriasAdeudadasAlumnosListado;

use App\Support\Examenes\MateriasAdeudadasCargaManual;

use App\Support\Examenes\MateriasAdeudadasPreparacion;

use Illuminate\Support\Facades\RateLimiter;

use Livewire\Component;



class MateriasAdeudadasCargaManualIndex extends Component

{
    use RequiresPermisoExamenes;

    public int $idLegajos;



    public bool $mostrarAgregar = false;



    public function mount(): void
    {
        $idLegajos = \App\Support\Navegacion\ContextoEstudianteSesion::legajo(
            \App\Support\Navegacion\ContextoEstudianteSesion::EXAMENES_MATERIAS_ADEUDADAS,
        );
        abort_if($idLegajos === null, 404);

        $ctx = schoolCtx();
        if (! $ctx->isValid() || ! MateriasAdeudadasAlumnosListado::esNivelSecundario($ctx)) {
            abort(403, 'Este módulo requiere contexto de Secundario.');
        }

        if (! MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(MateriasAdeudadasPreparacion::MODULO_GESTION)) {
            $this->redirectRoute('examenes.materias-adeudadas.gestion.entrar');

            return;
        }



        $alumno = MateriasAdeudadasCargaManual::alumnoEnGestion(
            $idLegajos,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );

        if ($alumno === null) {
            abort(404, 'Alumno no encontrado en la matrícula activa del ciclo lectivo actual.');
        }

        $this->idLegajos = $idLegajos;
    }



    public function abrirAgregar(): void

    {

        $this->mostrarAgregar = true;

    }



    public function cerrarAgregar(): void

    {

        $this->mostrarAgregar = false;

    }



    public function registrarAdeudada(int $idCalificacion): void

    {

        $this->ejecutarCambioAdeudo($idCalificacion, true);

    }



    public function quitarAdeudada(int $idCalificacion): void

    {

        $this->ejecutarCambioAdeudo($idCalificacion, false);

    }



    private function ejecutarCambioAdeudo(int $idCalificacion, bool $registrar): void

    {

        $key = 'examenes:ma-carga-manual:'.(auth()->id() ?? 'guest');

        if (RateLimiter::tooManyAttempts($key, 30)) {

            $this->addError('carga', 'Demasiados intentos. Espere un minuto e intente de nuevo.');



            return;

        }

        RateLimiter::hit($key, 60);



        $ctx = schoolCtx();

        $idNivel = (int) $ctx->idNivel;



        $resultado = $registrar

            ? MateriasAdeudadasCargaManual::registrarAdeudada($idCalificacion, $this->idLegajos, $idNivel)

            : MateriasAdeudadasCargaManual::quitarAdeudada($idCalificacion, $this->idLegajos, $idNivel);



        match ($resultado) {

            'ok' => session()->flash(

                'success',

                $registrar

                    ? 'Materia registrada como adeudada.'

                    : 'Se quitó el adeudo de la materia.',

            ),

            'ya_adeudada' => session()->flash('info', 'La materia ya estaba registrada como adeudada.'),

            'no_adeudada' => session()->flash('info', 'La materia no estaba marcada como adeudada.'),

            default => $this->addError('carga', 'No se encontró la calificación o no pertenece a este alumno.'),

        };

    }



    public function render()

    {

        $ctx = schoolCtx();

        $idNivel = (int) $ctx->idNivel;



        $alumno = MateriasAdeudadasCargaManual::alumnoEnGestion(

            $this->idLegajos,

            $idNivel,

            (int) $ctx->idTerlec,

        ) ?? [];



        $adeudadas = MateriasAdeudadasCargaManual::materiasAdeudadas($this->idLegajos, $idNivel);

        $todas = MateriasAdeudadasCargaManual::materiasDelAlumno($this->idLegajos, $idNivel);



        return view('livewire.examenes.materias-adeudadas-carga-manual', [

            'alumno' => $alumno,

            'adeudadas' => $adeudadas,

            'todas' => $todas,

            'totalAdeudadas' => count($adeudadas),

            'totalMaterias' => count($todas),

        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Carga manual — materias adeudadas']);

    }

}

