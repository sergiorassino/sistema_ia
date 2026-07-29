<?php

namespace App\Livewire\Arca;

use App\Models\Ento;
use App\Models\Nivel;
use App\Support\Arca\ObsFacturaHtmlSanitizer;
use App\Support\Database\PersistenciaColumnas;
use App\Support\NivelSistema;
use App\Support\PermisosArca;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use RuntimeException;

/**
 * Edición de `ento.obsFactura` por nivel pedagógico (Inicial, Primario, Secundario).
 */
class EditarObservacionFacturaForm extends Component
{
    public string $obsInicial = '';

    public string $obsPrimario = '';

    public string $obsSecundario = '';

    public function mount(): void
    {
        abort_unless(PermisosArca::puedeEditarObservacionFactura(), 404);

        if (! Schema::hasColumn('ento', 'obsFactura')) {
            return;
        }

        $porNivel = Ento::query()
            ->whereIn('idNivel', self::idsNivelesEditables())
            ->get(['idNivel', 'obsFactura'])
            ->keyBy(fn (Ento $e) => (int) $e->idNivel);

        $this->obsInicial = (string) ($porNivel->get(NivelSistema::INICIAL)?->obsFactura ?? '');
        $this->obsPrimario = (string) ($porNivel->get(NivelSistema::PRIMARIO)?->obsFactura ?? '');
        $this->obsSecundario = (string) ($porNivel->get(NivelSistema::SECUNDARIO)?->obsFactura ?? '');
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

        $this->obsInicial = $this->normalizarHtml($this->obsInicial);
        $this->obsPrimario = $this->normalizarHtml($this->obsPrimario);
        $this->obsSecundario = $this->normalizarHtml($this->obsSecundario);

        $this->validate([
            'obsInicial' => ['nullable', 'string', 'max:65000'],
            'obsPrimario' => ['nullable', 'string', 'max:65000'],
            'obsSecundario' => ['nullable', 'string', 'max:65000'],
        ]);

        /** @var array<int, array{payload: array<string, mixed>, columnas_con_valor_sin_columna: list<string>}> $preparados */
        $preparados = [];
        foreach ($this->valoresPorNivel() as $idNivel => $html) {
            $preparado = PersistenciaColumnas::prepararPayload(
                'ento',
                ['idNivel' => $idNivel, 'obsFactura' => $html],
                ['idNivel'],
            );
            if ($preparado['columnas_con_valor_sin_columna'] !== []) {
                $mensaje = PersistenciaColumnas::mensajeColumnasInexistentes(
                    'ento',
                    $preparado['columnas_con_valor_sin_columna'],
                );
                $this->addError($this->campoParaNivel($idNivel), $mensaje);
                $this->dispatch('se-swal-error', mensaje: $mensaje);

                return;
            }
            $preparados[$idNivel] = $preparado;
        }

        try {
            DB::transaction(function () use ($preparados): void {
                foreach ($preparados as $idNivel => $preparado) {
                    Ento::query()->updateOrCreate(
                        ['idNivel' => $idNivel],
                        $preparado['payload'],
                    );

                    $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
                        'ento',
                        ['idNivel' => $idNivel],
                        $preparado['payload'],
                    );
                    if ($noPersistidas !== []) {
                        throw new RuntimeException(
                            PersistenciaColumnas::mensajeColumnasNoPersistidas('ento', $noPersistidas)
                        );
                    }
                }
            });
        } catch (QueryException $e) {
            Log::warning('arca-obs-factura: error al guardar en ento', [
                'message' => $e->getMessage(),
            ]);
            $mensaje = PersistenciaColumnas::mensajeDesdeQueryException($e)
                ?? 'No se pudo guardar en la base de datos. Intente nuevamente.';
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        } catch (RuntimeException $e) {
            $this->dispatch('se-swal-error', mensaje: $e->getMessage());

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: 'Observaciones de factura guardadas para los tres niveles.');
    }

    /**
     * @return list<array{id: int, etiqueta: string, wireModel: string, value: string, errorKey: string}>
     */
    public function bloquesNivel(): array
    {
        $nombres = Nivel::query()
            ->whereIn('id', self::idsNivelesEditables())
            ->get(['id', 'nivel'])
            ->keyBy('id');

        return [
            [
                'id' => NivelSistema::INICIAL,
                'etiqueta' => trim((string) ($nombres->get(NivelSistema::INICIAL)?->nivel ?? 'Inicial')),
                'wireModel' => 'obsInicial',
                'value' => $this->obsInicial,
                'errorKey' => 'obsInicial',
            ],
            [
                'id' => NivelSistema::PRIMARIO,
                'etiqueta' => trim((string) ($nombres->get(NivelSistema::PRIMARIO)?->nivel ?? 'Primario')),
                'wireModel' => 'obsPrimario',
                'value' => $this->obsPrimario,
                'errorKey' => 'obsPrimario',
            ],
            [
                'id' => NivelSistema::SECUNDARIO,
                'etiqueta' => trim((string) ($nombres->get(NivelSistema::SECUNDARIO)?->nivel ?? 'Secundario')),
                'wireModel' => 'obsSecundario',
                'value' => $this->obsSecundario,
                'errorKey' => 'obsSecundario',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.arca.editar-observacion-factura-form', [
            'bloques' => $this->bloquesNivel(),
            'columnaDisponible' => Schema::hasColumn('ento', 'obsFactura'),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Editar Observación Factura']);
    }

    /** @return list<int> */
    private static function idsNivelesEditables(): array
    {
        return [
            NivelSistema::INICIAL,
            NivelSistema::PRIMARIO,
            NivelSistema::SECUNDARIO,
        ];
    }

    /** @return array<int, string> */
    private function valoresPorNivel(): array
    {
        return [
            NivelSistema::INICIAL => $this->obsInicial,
            NivelSistema::PRIMARIO => $this->obsPrimario,
            NivelSistema::SECUNDARIO => $this->obsSecundario,
        ];
    }

    private function normalizarHtml(string $html): string
    {
        $limpio = ObsFacturaHtmlSanitizer::limpiar($html);

        return ObsFacturaHtmlSanitizer::estaVacio($limpio) ? '' : $limpio;
    }

    private function campoParaNivel(int $idNivel): string
    {
        return match ($idNivel) {
            NivelSistema::INICIAL => 'obsInicial',
            NivelSistema::PRIMARIO => 'obsPrimario',
            default => 'obsSecundario',
        };
    }
}
