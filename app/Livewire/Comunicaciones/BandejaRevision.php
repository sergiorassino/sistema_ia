<?php

namespace App\Livewire\Comunicaciones;

use App\Comunicaciones\ComunicacionesGestionSession;
use App\Comunicaciones\ComunicacionesRepository;
use App\Support\ComunicacionesRutasGestion;
use Livewire\Component;

class BandejaRevision extends Component
{
    public string $filtro = 'todos'; // todos|no_leidos
    public string $direccion = 'todos'; // todos|recibidos|enviados
    public string $periodo = 'actual'; // actual|historico

    /** null = todos los comunicados institucionales del nivel/ciclo */
    public ?int $idProfesorObjetivo = null;

    public ?int $idLegajoObjetivo = null;

    public ?string $profesorObjetivoLabel = null;

    public string $profesorSearch = '';
    public array $profesorResults = [];

    public function mount(): void
    {
        abort_unless(tienePermiso(3) && tienePermiso(8), 403, 'Sin permiso para revisar comunicaciones.');
    }

    public function limpiarFiltroProfesor(): void
    {
        $this->idProfesorObjetivo      = null;
        $this->idLegajoObjetivo        = null;
        $this->profesorObjetivoLabel   = null;
        $this->profesorSearch          = '';
        $this->profesorResults         = [];
    }

    public function hydrate(): void
    {
        if (! in_array($this->filtro, ['todos', 'no_leidos'], true)) {
            $this->filtro = 'todos';
        }
    }

    public function updatedProfesorSearch(): void
    {
        $ctx = schoolCtx();
        $this->profesorResults = ComunicacionesRepository::buscarUsuariosRevision(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $this->profesorSearch,
            15
        );
    }

    public function selectProfesor(int $id, string $label): void
    {
        $this->selectUsuario('profesor', $id, $label);
    }

    public function selectUsuario(string $tipo, int $id, string $label): void
    {
        $this->idProfesorObjetivo = null;
        $this->idLegajoObjetivo = null;

        if ($tipo === 'estudiante') {
            $this->idLegajoObjetivo = (int) $id;
        } else {
            $this->idProfesorObjetivo = (int) $id;
        }

        $this->profesorObjetivoLabel = trim($label);
        $this->profesorSearch = '';
        $this->profesorResults = [];
    }

    public function abrirHilo(int $idHilo): void
    {
        abort_unless(ComunicacionesGestionSession::puedeVerHilo($idHilo), 404);

        ComunicacionesGestionSession::abrir($idHilo);
        $this->redirectRoute(ComunicacionesRutasGestion::nombreRuta('hilo'), navigate: true);
    }

    public function render()
    {
        $ctx      = schoolCtx();
        $idNivel  = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        $dir = in_array($this->direccion, ['todos', 'recibidos', 'enviados'], true) ? $this->direccion : 'todos';

        $hilos = ComunicacionesRepository::bandejaRevisionControl(
            $idNivel,
            $idTerlec,
            $this->filtro,
            $dir === 'todos' ? 'todos' : $dir,
            $this->periodo !== 'historico',
            $this->idProfesorObjetivo,
            $this->idLegajoObjetivo
        );

        return view('comunicaciones::livewire.comunicaciones.bandeja-revision', [
            'hilos' => $hilos,
        ])->layout(ComunicacionesRutasGestion::layout(), ['pageTitle' => 'Comunicaciones · Revisión']);
    }
}
