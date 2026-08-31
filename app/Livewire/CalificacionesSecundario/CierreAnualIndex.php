<?php

namespace App\Livewire\CalificacionesSecundario;

use App\Support\CalificacionesSecundario\CierreAnualJournal;
use App\Support\CalificacionesSecundario\CierreAnualSecundario;
use App\Support\Database\PersistenciaColumnas;
use App\Support\PermisosIaCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use RuntimeException;

/**
 * Cierre anual (secundario): listado de estudiantes y cierre masivo al matriz (Dic / Feb).
 */
class CierreAnualIndex extends Component
{
    public string $buscar = '';

    public bool $confirmarDic = false;

    public bool $confirmarFeb = false;

    /**
     * @var array{
     *     operacion: string,
     *     titulo: string,
     *     procesados: int,
     *     actualizados: int,
     *     aprobados: int,
     *     previas: int,
     *     omitidos: int,
     *     nivel: string,
     *     ano_lectivo: int|string,
     *     lote_id: int
     * }|null
     */
    public ?array $informeCierre = null;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL), 403, 'Sin permiso para cierre anual.');
        $ctx = schoolCtx();
        if (! str_contains(mb_strtolower($ctx->nivelNombre()), 'secundari')) {
            abort(403, 'Este módulo requiere contexto de Secundario.');
        }
    }

    public function cerrarInformeCierre(): void
    {
        $this->informeCierre = null;
    }

    public function irALotes(): mixed
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL), 403);
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL_LOTES), 403, 'Sin permiso para lotes de cierre.');
        CierreAnualJournal::guardarSesionLoteId(0);

        return $this->redirect(route('calificacionesSecundario.cierreAnual.lotes'), navigate: true);
    }

    public function verFilasLote(): mixed
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL), 403);
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL_LOTES), 403, 'Sin permiso para lotes de cierre.');
        $id = (int) ($this->informeCierre['lote_id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        CierreAnualJournal::guardarSesionLoteId($id);

        return $this->redirect(route('calificacionesSecundario.cierreAnual.lotes'), navigate: true);
    }

    public function solicitarCierreDic(): void
    {
        $this->confirmarDic = true;
        $this->confirmarFeb = false;
    }

    public function cancelarCierreDic(): void
    {
        $this->confirmarDic = false;
    }

    public function solicitarCierreFeb(): void
    {
        $this->confirmarFeb = true;
        $this->confirmarDic = false;
    }

    public function cancelarCierreFeb(): void
    {
        $this->confirmarFeb = false;
    }

    public function ejecutarCierreDic(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL), 403);
        $this->confirmarDic = false;
        $this->informeCierre = null;

        $key = 'calificacionesSecundario:cierreAnual:dic:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('cierre', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 120);

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        try {
            $ctx = schoolCtx();
            $res = CierreAnualSecundario::pasarAprobadasMatrizDic(
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
            );
        } catch (QueryException $e) {
            $msg = PersistenciaColumnas::mensajeDesdeQueryException($e)
                ?? 'No se pudo completar el cierre de diciembre.';
            $this->addError('cierre', $msg);
            $this->dispatch('se-swal-error', mensaje: $msg);

            return;
        } catch (RuntimeException $e) {
            $this->addError('cierre', $e->getMessage());
            $this->dispatch('se-swal-error', mensaje: $e->getMessage());

            return;
        }

        $this->informeCierre = CierreAnualSecundario::armarInformeCierre('dic', $res, schoolCtx());
        $this->dispatch(
            'se-swal-exito',
            mensaje: 'Cierre de diciembre registrado. '.$this->informeCierre['actualizados'].' calificación(es) actualizada(s).',
        );
    }

    public function ejecutarCierreFeb(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL), 403);
        $this->confirmarFeb = false;
        $this->informeCierre = null;

        $key = 'calificacionesSecundario:cierreAnual:feb:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('cierre', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 120);

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        try {
            $ctx = schoolCtx();
            $res = CierreAnualSecundario::pasarAprobadasMatrizYPreviasFeb(
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
            );
        } catch (QueryException $e) {
            $msg = PersistenciaColumnas::mensajeDesdeQueryException($e)
                ?? 'No se pudo completar el cierre de febrero.';
            $this->addError('cierre', $msg);
            $this->dispatch('se-swal-error', mensaje: $msg);

            return;
        } catch (RuntimeException $e) {
            $this->addError('cierre', $e->getMessage());
            $this->dispatch('se-swal-error', mensaje: $e->getMessage());

            return;
        }

        $this->informeCierre = CierreAnualSecundario::armarInformeCierre('feb', $res, schoolCtx());
        $this->dispatch(
            'se-swal-exito',
            mensaje: 'Cierre de febrero registrado. '.$this->informeCierre['actualizados'].' calificación(es) actualizada(s).',
        );
    }

    public function render()
    {
        $ctx = schoolCtx();
        $alumnos = CierreAnualSecundario::matriculasDelAnio(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $this->buscar,
        );

        return view('livewire.calificaciones-secundario.cierre-anual-index', [
            'alumnos' => $alumnos,
            'journalListo' => CierreAnualJournal::tablasListas(),
            'cantidadLotes' => CierreAnualJournal::contarLotes(
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
            ),
            'puedeVerLotes' => tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL_LOTES),
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Cierre anual (secundario)']);
    }
}
