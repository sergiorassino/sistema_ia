<?php

namespace App\Livewire\PortalDocente;

use App\Models\Matricula;
use App\Models\Sancion;
use App\Support\PortalDocente\CuadernoSeguimientoAulicoDocente;
use App\Support\PortalDocente\NotificarPreceptorSituacionAulica;
use App\Support\PortalDocente\SituacionAulicaTipo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Situación áulica de un alumno: listado (solo del docente activo, año actual) y alta sin edición.
 */
class SituacionAulicaAlumnoShow extends Component
{
    public int $cursoId;

    public int $materiaId;

    public int $matriculaId;

    public string $fecha = '';

    public string $motivo = '';

    public bool $mostrarFormNuevo = false;

    public function mount(int $curso, int $materia): void
    {
        CuadernoSeguimientoAulicoDocente::abortSiNoHabilitadoEnTenant();
        CuadernoSeguimientoAulicoDocente::abortSiNoEsSecundario();
        CuadernoSeguimientoAulicoDocente::abortSiProfesorSinMateria($materia, $curso);

        $matricula = \App\Support\Navegacion\ContextoEstudianteSesion::matricula(
            \App\Support\Navegacion\ContextoEstudianteSesion::PORTAL_DOCENTE_CUADERNO,
        );
        abort_if($matricula === null, 404);

        $this->cursoId = $curso;
        $this->materiaId = $materia;
        $this->matriculaId = $matricula;

        $m = $this->matriculaDelContexto();
        abort_if((int) $m->idCursos !== $curso, 404);

        try {
            SituacionAulicaTipo::idTipo();
        } catch (\RuntimeException $e) {
            abort(503, $e->getMessage());
        }

        $this->fecha = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'fecha.required' => 'Indique la fecha.',
            'fecha.date' => 'Fecha inválida.',
            'motivo.required' => 'Indique el motivo.',
        ];
    }

    private function matriculaDelContexto(): Matricula
    {
        /** @var Matricula $m */
        $m = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->where('idCursos', $this->cursoId)
            ->whereIn('idCondiciones', CuadernoSeguimientoAulicoDocente::idsCondicionesRegulares())
            ->findOrFail($this->matriculaId);

        return $m;
    }

    /** @return Collection<int, Sancion> */
    private function sancionesDelDocente(): Collection
    {
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        $idTipo = SituacionAulicaTipo::idTipo();

        return Sancion::query()
            ->with(['tipo'])
            ->where('idMatricula', $this->matriculaId)
            ->where('idProfesores', $idProfesor)
            ->where('idTipoSancion', $idTipo)
            ->whereHas('matricula', function ($q) {
                $q->where('idNivel', schoolCtx()->idNivel)
                    ->where('idTerlec', schoolCtx()->idTerlec);
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    public function abrirFormNuevo(): void
    {
        $this->mostrarFormNuevo = true;
        $this->resetValidation();
        $this->fecha = now()->format('Y-m-d');
        $this->motivo = '';
    }

    public function cerrarFormNuevo(): void
    {
        $this->mostrarFormNuevo = false;
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $key = 'portal-situacion-aulica:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('fecha', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $m = $this->matriculaDelContexto();
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);

        $sancion = Sancion::create([
            'idMatricula' => (int) $m->id,
            'idTipoSancion' => SituacionAulicaTipo::idTipo(),
            'idProfesores' => $idProfesor,
            'fecha' => $this->fecha ?: null,
            'cantidad' => null,
            'motivo' => trim($this->motivo),
            'solipor' => null,
        ]);

        try {
            $avisoPreceptor = NotificarPreceptorSituacionAulica::despachar($sancion, $m);
        } catch (\Throwable $e) {
            report($e);
            $avisoPreceptor = false;
        }

        $this->mostrarFormNuevo = false;
        $this->reset(['motivo']);
        $this->fecha = now()->format('Y-m-d');

        if ($avisoPreceptor) {
            session()->flash('success', 'Registro guardado. Se notificó al preceptor del curso.');
        } else {
            session()->flash('success', 'Registro guardado. No se pudo notificar al preceptor (sin asignación o aviso no disponible).');
        }
    }

    public function render()
    {
        $matricula = $this->matriculaDelContexto();
        $sanciones = $this->sancionesDelDocente();

        return view('livewire.portal-docente.situacion-aulica-alumno-show', [
            'matricula' => $matricula,
            'sanciones' => $sanciones,
            'tipoLabel' => SituacionAulicaTipo::label(),
        ])->layout('layouts.docente', ['pageTitle' => 'Situación áulica']);
    }
}
