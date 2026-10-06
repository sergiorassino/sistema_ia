<?php

namespace App\Livewire\Parametrizacion;

use App\Models\Profesor;
use App\Models\SituacionAulicaDestinatario;
use App\Support\PermisosConfiguracion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Destinatarios adicionales del aviso de situación áulica (además del preceptor del curso).
 */
class SituacionAulicaDestinatariosIndex extends Component
{
    public string $idProfesor = '';

    public function mount(): void
    {
        abort_unless(
            tienePermiso(PermisosConfiguracion::SANCION_TIPOS_CONFIG),
            403,
            'Sin permiso para administrar destinatarios de situación áulica.'
        );
        abort_unless(tenantPortalDocenteCuadernoSeguimientoAulico(), 404);
    }

    public function agregar(): void
    {
        if (! $this->tablaDisponible()) {
            $this->addError('idProfesor', 'Falta la tabla de destinatarios. Aplique el SQL del módulo antes de cargar personas.');

            return;
        }

        $key = 'situacion-aulica-dest:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->addError('idProfesor', 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate([
            'idProfesor' => ['required', 'integer', 'min:1'],
        ], [
            'idProfesor.required' => 'Elija un profesor.',
            'idProfesor.integer' => 'Elija un profesor.',
            'idProfesor.min' => 'Elija un profesor.',
        ]);

        $idNivel = (int) schoolCtx()->idNivel;
        $id = (int) $this->idProfesor;

        $profesor = Profesor::query()->delNivel($idNivel)->where('id', $id)->first();
        if ($profesor === null) {
            $this->addError('idProfesor', 'Elija un profesor de este nivel.');

            return;
        }

        $yaEsta = SituacionAulicaDestinatario::query()
            ->where('idNivel', $idNivel)
            ->where('idProfesor', $id)
            ->exists();
        if ($yaEsta) {
            $this->addError('idProfesor', 'Esa persona ya está en la lista.');

            return;
        }

        try {
            SituacionAulicaDestinatario::query()->create([
                'idNivel' => $idNivel,
                'idProfesor' => $id,
            ]);
        } catch (QueryException $e) {
            report($e);
            $this->addError('idProfesor', 'No se pudo agregar el destinatario.');

            return;
        }

        $this->idProfesor = '';
        $this->resetValidation();
        $this->dispatch('se-swal-exito', mensaje: 'Destinatario agregado.');
    }

    public function quitar(int $id): void
    {
        if (! $this->tablaDisponible()) {
            $this->dispatch('se-swal-error', mensaje: 'Falta la tabla de destinatarios.');

            return;
        }

        $key = 'situacion-aulica-dest:del:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $idNivel = (int) schoolCtx()->idNivel;
        $fila = SituacionAulicaDestinatario::query()
            ->where('idNivel', $idNivel)
            ->where('id', $id)
            ->first();

        if ($fila === null) {
            $this->dispatch('se-swal-error', mensaje: 'El destinatario no pertenece a este nivel.');

            return;
        }

        $fila->delete();
        $this->dispatch('se-swal-exito', mensaje: 'Destinatario quitado.');
    }

    private function tablaDisponible(): bool
    {
        return Schema::hasTable(SituacionAulicaDestinatario::TABLA);
    }

    public function render()
    {
        $idNivel = (int) schoolCtx()->idNivel;
        $tablaOk = $this->tablaDisponible();
        $destinatarios = collect();
        $profesores = collect();

        if ($tablaOk) {
            $destinatarios = SituacionAulicaDestinatario::query()
                ->with('profesor')
                ->where('idNivel', $idNivel)
                ->get()
                ->sortBy(fn (SituacionAulicaDestinatario $fila) => mb_strtolower(
                    ($fila->profesor?->apellido ?? '').' '.($fila->profesor?->nombre ?? '')
                ))
                ->values();

            $yaElegidos = $destinatarios->pluck('idProfesor')->map(fn ($id) => (int) $id)->all();
            $profesores = Profesor::query()
                ->delNivel($idNivel)
                ->when($yaElegidos !== [], fn ($q) => $q->whereNotIn('id', $yaElegidos))
                ->orderBy('apellido')
                ->orderBy('nombre')
                ->get(['id', 'apellido', 'nombre']);
        }

        return view('livewire.parametrizacion.situacion-aulica-destinatarios-index', [
            'tablaOk' => $tablaOk,
            'destinatarios' => $destinatarios,
            'profesores' => $profesores,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Destinatarios de situación áulica']);
    }
}
