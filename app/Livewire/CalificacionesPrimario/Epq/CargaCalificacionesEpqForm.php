<?php

namespace App\Livewire\CalificacionesPrimario\Epq;

use App\Livewire\Concerns\AvisoCargaNotasOffEnto;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioDatos;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqCatalogo;
use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqDatos;
use App\Support\PortalDocente\CalificacionesPrimarioPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * EPQ — grilla materias × trimestres (ic01–ic07), guardado al salir de cada celda.
 */
class CargaCalificacionesEpqForm extends Component
{
    use AvisoCargaNotasOffEnto;

    public int $idMatricula;

    public int $cursoId;

    public string $alumnoLinea = '';

    public string $cursoLabel = '';

    /** @var list<array{id: int, ord: int, materia: string}> */
    public array $materiasLista = [];

    /** @var array<int, array<string, mixed>> */
    public array $notas = [];

    public bool $modoPortalDocente = false;

    public function mount(int $matricula): void
    {
        CalificacionesPrimarioModulos::abortSiImplementacionInactiva(
            CalificacionesPrimarioModulos::CARGA_ESTUDIANTE,
            CalificacionesEpqCatalogo::IMPLEMENTACION,
        );

        $this->modoPortalDocente = CalificacionesPrimarioPortalDocente::esPortalDocente();

        if (! $this->modoPortalDocente) {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                \App\Support\PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para cargar calificaciones.',
            );
        }

        CalificacionesPrimarioPortalDocente::abortSiNoEsPrimario();

        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($matricula);
        abort_if($mat === null, 404);

        if ($this->modoPortalDocente) {
            CalificacionesPrimarioPortalDocente::abortSiProfesorSinCurso((int) $mat->idCursos);
        }

        $this->idMatricula = (int) $mat->id;
        $this->cursoId = (int) $mat->idCursos;
        $this->cargarDesdeBd($mat);
        $this->inicializarAvisoCargaNotasOff($this->modoPortalDocente);
    }

    protected function cargarDesdeBd(\App\Models\Matricula $mat): void
    {
        $data = CalificacionesEpqDatos::cargarCalificaciones($mat);
        $this->materiasLista = $data['materias']
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'ord' => (int) $m->ord,
                'materia' => (string) $m->materia,
            ])
            ->values()
            ->all();
        $this->notas = $data['notas'];
        $this->alumnoLinea = $data['alumnoLinea'];
        $this->cursoLabel = $data['cursoLabel'];
    }

    #[Renderless]
    public function saveCell(int $idMaterias, string $campo, mixed $value): void
    {
        if ($this->cargaNotasOffBloqueaEdicion()) {
            return;
        }

        PortalDocenteContext::abortSiStaffSinPermisoIa(\App\Support\PermisosIaCatalog::CALIF_CARGA);

        $key = 'calificacionesEpq:carga:cell:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $campo = trim($campo);
        if (! in_array($campo, CalificacionesEpqCatalogo::CAMPOS_NOTA, true)) {
            abort(400);
        }

        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($this->idMatricula);
        if ($mat === null) {
            abort(404);
        }

        $value = is_string($value) ? trim($value) : (string) ($value ?? '');

        Validator::make(
            ['value' => $value],
            ['value' => ['nullable', 'string', 'max:15']],
        )->validate();

        CalificacionesEpqDatos::guardarNota($mat, $idMaterias, $campo, $value);

        if (isset($this->notas[$idMaterias])) {
            $this->notas[$idMaterias][$campo] = $value;
        }
    }

    public function render()
    {
        return view('livewire.calificaciones-primario.epq.carga-form', $this->datosVistaAvisoCargaNotasOff($this->modoPortalDocente))
            ->layout(CalificacionesPrimarioPortalDocente::layout(), ['pageTitle' => 'Carga de calificaciones (EPQ)']);
    }
}
