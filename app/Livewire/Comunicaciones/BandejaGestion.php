<?php

namespace App\Livewire\Comunicaciones;

use App\Comunicaciones\ComunicacionesGestionSession;
use App\Comunicaciones\ComunicacionesRepository;
use App\Support\ComunicacionesRutasGestion;
use Livewire\Component;

class BandejaGestion extends Component
{
    public string $filtro = 'todos'; // todos|no_leidos
    public string $periodo = 'actual'; // actual|historico

    public function mount(): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoBandejaGestion(), 403, 'Sin permiso para ver comunicaciones.');

        $filtroQuery = request()->query('filtro', '');
        if (in_array($filtroQuery, ['no_leidos'], true)) {
            $this->filtro = $filtroQuery;
        }
    }

    public function hydrate(): void
    {
        if (! in_array($this->filtro, ['todos', 'no_leidos'], true)) {
            $this->filtro = 'todos';
        }
    }

    public function updatedFiltro(): void
    {
        // Reactivo: Livewire re-renderiza automáticamente
    }

    public function abrirHilo(int $idHilo): void
    {
        abort_unless(ComunicacionesGestionSession::puedeVerHilo($idHilo), 404);

        ComunicacionesGestionSession::abrir($idHilo);
        $this->redirectRoute(ComunicacionesRutasGestion::nombreRuta('hilo'), navigate: true);
    }

    public function render()
    {
        $ctx       = schoolCtx();
        $idProf    = (int) $ctx->idProfesor;
        $idNivel   = (int) $ctx->idNivel;
        $idTerlec  = (int) $ctx->idTerlec;

        $hilos = ComunicacionesRepository::bandejaProfesor(
            $idProf,
            $idNivel,
            $idTerlec,
            $this->filtro,
            'todos',
            $this->periodo !== 'historico'
        );

        return view('comunicaciones::livewire.comunicaciones.bandeja-gestion', [
            'hilos' => $hilos,
        ])->layout(ComunicacionesRutasGestion::layout(), ['pageTitle' => 'Comunicaciones']);
    }
}
