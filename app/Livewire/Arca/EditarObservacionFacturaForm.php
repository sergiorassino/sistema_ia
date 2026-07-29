<?php

namespace App\Livewire\Arca;

use App\Models\Ento;
use App\Support\Arca\ObsFacturaHtmlSanitizer;
use App\Support\Database\PersistenciaColumnas;
use App\Support\PermisosArca;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Edición de `ento.obsFactura` — texto HTML del impreso de factura AFIP.
 */
class EditarObservacionFacturaForm extends Component
{
    public string $obsFactura = '';

    public function mount(): void
    {
        abort_unless(PermisosArca::puedeEditarObservacionFactura(), 404);

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        abort_if($idNivel < 1, 403, 'Seleccione un nivel en el contexto activo.');

        /** @var Ento $ento */
        $ento = Ento::query()->firstOrNew(['idNivel' => $idNivel]);

        $this->obsFactura = Schema::hasColumn('ento', 'obsFactura')
            ? (string) ($ento->obsFactura ?? '')
            : '';
    }

    public function guardar(): void
    {
        abort_unless(PermisosArca::puedeEditarObservacionFactura(), 403);

        $key = 'arca:obs-factura:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel < 1) {
            $this->dispatch('se-swal-error', mensaje: 'Seleccione un nivel en el contexto activo.');

            return;
        }

        $this->obsFactura = ObsFacturaHtmlSanitizer::limpiar($this->obsFactura);
        if (ObsFacturaHtmlSanitizer::estaVacio($this->obsFactura)) {
            $this->obsFactura = '';
        }

        $this->validate([
            'obsFactura' => ['nullable', 'string', 'max:65000'],
        ]);

        $payload = [
            'idNivel' => $idNivel,
            'obsFactura' => $this->obsFactura,
        ];

        $preparado = PersistenciaColumnas::prepararPayload('ento', $payload, ['idNivel']);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $mensaje = PersistenciaColumnas::mensajeColumnasInexistentes(
                'ento',
                $preparado['columnas_con_valor_sin_columna'],
            );
            $this->addError('obsFactura', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        }

        try {
            Ento::query()->updateOrCreate(
                ['idNivel' => $idNivel],
                $preparado['payload'],
            );
        } catch (QueryException $e) {
            Log::warning('arca-obs-factura: error al guardar en ento', [
                'idNivel' => $idNivel,
                'message' => $e->getMessage(),
            ]);
            $mensaje = PersistenciaColumnas::mensajeDesdeQueryException($e)
                ?? 'No se pudo guardar en la base de datos. Intente nuevamente.';
            $this->addError('obsFactura', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        }

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'ento',
            ['idNivel' => $idNivel],
            $preparado['payload'],
        );
        if ($noPersistidas !== []) {
            $mensaje = PersistenciaColumnas::mensajeColumnasNoPersistidas('ento', $noPersistidas);
            $this->addError('obsFactura', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: 'Observación de factura guardada.');
    }

    public function render()
    {
        return view('livewire.arca.editar-observacion-factura-form')
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Editar Observación Factura']);
    }
}
