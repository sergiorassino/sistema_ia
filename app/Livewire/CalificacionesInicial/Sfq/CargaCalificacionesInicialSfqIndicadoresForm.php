<?php

namespace App\Livewire\CalificacionesInicial\Sfq;

use App\Livewire\Concerns\AvisoCargaNotasOffEnto;
use App\Support\CalificacionesInicial\CalificacionesInicialModulos;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqCatalogo;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqDatos;
use App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * SFQ — carga de indicadores (ic01–ic06) por alumno, etapa y área.
 */
class CargaCalificacionesInicialSfqIndicadoresForm extends Component
{
    use AvisoCargaNotasOffEnto;

    public int $idMatricula;

    public int $cursoId;

    public string $campoIc = '';

    public string $alumnoLinea = '';

    public string $cursoLabel = '';

    public int|string $sala = '';

    public int $etapa = 1;

    public string $area = '';

    /** @var list<array{id: int, idEdani: int, edani: string, indicador: string, nota: string}> */
    public array $filas = [];

    public bool $modoPortalDocente = false;

    public function mount(int $matricula, string $campo): void
    {
        CalificacionesInicialModulos::abortSiImplementacionInactiva(
            CalificacionesInicialModulos::CARGA_NOTAS,
            CalificacionesInicialSfqCatalogo::IMPLEMENTACION,
        );

        CalificacionesInicialSfqDatos::abortSiEsquemaIncompleto();

        abort_unless(CalificacionesInicialSfqCatalogo::esCampoIc($campo), 404);

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

        $mat = CalificacionesInicialSfqDatos::matriculaEnContexto($matricula);
        abort_if($mat === null, 404);

        if ($this->modoPortalDocente) {
            CalificacionesInicialSfqPortalDocente::abortSiProfesorSinMatricula($matricula);
        }

        $this->idMatricula = (int) $mat->id;
        $this->cursoId = (int) $mat->idCursos;
        $this->campoIc = $campo;

        $data = CalificacionesInicialSfqDatos::cargarFormularioIndicadores($mat, $campo);
        $this->alumnoLinea = $data['alumnoLinea'];
        $this->cursoLabel = $data['cursoLabel'];
        $this->sala = $data['sala'];
        $this->etapa = (int) $data['etapa'];
        $this->area = (string) $data['area'];
        $this->filas = $data['filas'];
        $this->inicializarAvisoCargaNotasOff($this->modoPortalDocente);
    }

    #[Renderless]
    public function guardar(): void
    {
        if ($this->cargaNotasOffBloqueaEdicion()) {
            return;
        }

        PortalDocenteContext::abortSiStaffSinPermisoIa(\App\Support\PermisosIaCatalog::CALIF_CARGA);

        $key = 'calificacionesInicialSfq:indicadores:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $mat = CalificacionesInicialSfqDatos::matriculaEnContexto($this->idMatricula);
        if ($mat === null) {
            abort(404);
        }

        if ($this->modoPortalDocente) {
            CalificacionesInicialSfqPortalDocente::abortSiProfesorSinMatricula($this->idMatricula);
        }

        CalificacionesInicialSfqDatos::guardarIndicadores($mat, $this->campoIc, $this->filas);
    }

    public function render()
    {
        return view('livewire.calificaciones-inicial.sfq.indicadores-form', array_merge([
            'etiquetaColumna' => CalificacionesInicialSfqCatalogo::ETIQUETAS_COLUMNA[$this->campoIc] ?? $this->campoIc,
            'etiquetaEtapa' => CalificacionesInicialSfqCatalogo::etiquetaEtapa($this->etapa),
            'opcionesNota' => CalificacionesInicialSfqCatalogo::OPCIONES_NOTA,
        ], $this->datosVistaAvisoCargaNotasOff($this->modoPortalDocente)))->layout(CalificacionesInicialSfqPortalDocente::layout(), [
            'pageTitle' => 'Carga de indicadores (Inicial SFQ)',
        ]);
    }
}
