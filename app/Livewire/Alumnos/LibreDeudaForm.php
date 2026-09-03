<?php

namespace App\Livewire\Alumnos;

use App\Models\Legajo;
use App\Support\Alumnos\LibreDeudaDatos;
use App\Support\Aulica\AulicaDeudaConsulta;
use App\Support\Aulica\AulicaDeudaResultado;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Formulario de Libre Deuda: consulta Áulica, muestra el detalle en un modal y recién entonces ofrece el PDF.
 */
class LibreDeudaForm extends Component
{
    public bool $modalAbierto = false;

    /** @var array<string, mixed> */
    public array $detalle = [];

    public function mount(): void
    {
        abort_unless(tenantAutogestionLibreDeudaHabilitada(), 404);
    }

    public function consultarYMostrar(): void
    {
        $key = 'alumnos-libre-deuda-consulta:'.(auth('alumno')->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiadas consultas. Intente nuevamente en un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            $this->detalle = AulicaDeudaResultado::error('Sesión de estudiante inválida.')->paraModal();
            $this->modalAbierto = true;

            return;
        }

        $legajo = Legajo::query()->where('id', (int) $ctx->idLegajo)->first();
        if ($legajo === null) {
            $this->detalle = AulicaDeudaResultado::error('No se encontró el legajo.')->paraModal();
            $this->modalAbierto = true;

            return;
        }

        $origen = AulicaDeudaConsulta::origenResponsableDesdeLegajo($legajo);
        $deuda = (new AulicaDeudaConsulta)->paraLegajo($legajo);
        $this->detalle = $deuda->paraModal(is_array($origen) ? $origen['etiqueta'] : '');
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
    }

    public function render()
    {
        $datos = LibreDeudaDatos::paraAutogestion();

        return view('livewire.alumnos.libre-deuda-form', [
            'datos' => $datos,
            'urlPdf' => se_route_url('alumnos.libre-deuda.pdf'),
            'urlAranceles' => trim((string) config('tenant.autogestion.aranceles_aulica_url', '')),
        ])->layout('layouts.alumno', ['pageTitle' => 'Libre Deuda']);
    }
}
