<?php

namespace App\Livewire\CalificacionesSecundario;

use App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos;
use App\Support\CalificacionesSecundario\RecalculoPromedioAnualSecundario;
use App\Support\NivelSistema;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Recálculo masivo de promedio anual (`calif`) para secundario estándar (Eval/JIS).
 *
 * Misma fórmula que la carga de calificaciones; alcance: ciclo y nivel de `schoolCtx()`.
 */
class RecalculoPromediosSecundario extends Component
{
    /**
     * @var array{
     *     procesados: int,
     *     actualizados: int,
     *     sin_cambio: int,
     *     omitidos_coloquio: int,
     *     errores: int,
     *     nivel: string,
     *     ano_lectivo: int|string
     * }|null
     */
    public ?array $informe = null;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_RECALCULO_PROMEDIOS), 403, 'Sin permiso para recalcular promedios.');
        abort_unless(NivelSistema::esSecundario((int) (schoolCtx()->idNivel ?? 0)), 403, 'Este módulo requiere contexto de Secundario.');
        abort_unless(CalificacionesSecundarioModulos::cargaEsEstandar(), 404);
    }

    public function cerrarInforme(): void
    {
        $this->informe = null;
    }

    public function ejecutar(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_RECALCULO_PROMEDIOS), 403);
        abort_unless(NivelSistema::esSecundario((int) (schoolCtx()->idNivel ?? 0)), 403);
        abort_unless(CalificacionesSecundarioModulos::cargaEsEstandar(), 404);

        $this->informe = null;

        $key = 'calificacionesSecundario:recalculoPromedios:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('recalculo', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 120);

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $ctx = schoolCtx();
        $res = RecalculoPromedioAnualSecundario::ejecutar(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );

        if (($res['mensaje_error'] ?? null) !== null && (int) $res['procesados'] === 0) {
            $this->addError('recalculo', (string) $res['mensaje_error']);
            $this->dispatch('se-swal-error', mensaje: (string) $res['mensaje_error']);

            return;
        }

        $this->informe = [
            'procesados' => (int) $res['procesados'],
            'actualizados' => (int) $res['actualizados'],
            'sin_cambio' => (int) $res['sin_cambio'],
            'omitidos_coloquio' => (int) $res['omitidos_coloquio'],
            'errores' => (int) $res['errores'],
            'nivel' => $ctx->nivelNombre(),
            'ano_lectivo' => $ctx->terlecAno() ?? '—',
        ];

        if (($res['mensaje_error'] ?? null) !== null || (int) $res['errores'] > 0) {
            $extra = (string) ($res['mensaje_error'] ?? 'Algunas filas no se pudieron actualizar.');
            $this->addError('recalculo', $extra);
            $this->dispatch('se-swal-error', mensaje: $extra);

            return;
        }

        $this->dispatch(
            'se-swal-exito',
            mensaje: 'Se actualizaron '.$this->informe['actualizados'].' promedio(s) final(es).',
        );
    }

    public function render()
    {
        $ctx = schoolCtx();
        $filas = RecalculoPromedioAnualSecundario::contarFilas(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );

        return view('livewire.calificaciones-secundario.recalculo-promedios-secundario', [
            'cantidadFilas' => $filas,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Recalcular promedios (secundario)']);
    }
}
