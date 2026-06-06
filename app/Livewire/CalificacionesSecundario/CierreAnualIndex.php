<?php

namespace App\Livewire\CalificacionesSecundario;

use App\Support\CalificacionesSecundario\CierreAnualSecundario;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

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
     *     ano_lectivo: int|string
     * }|null
     */
    public ?array $informeCierre = null;

    public function mount(): void
    {
        abort_unless(tienePermiso(15), 403, 'Sin permiso para cierre anual.');
        $ctx = schoolCtx();
        if (! str_contains(mb_strtolower($ctx->nivelNombre()), 'secundari')) {
            abort(403, 'Este módulo requiere contexto de Secundario.');
        }
    }

    public function cerrarInformeCierre(): void
    {
        $this->informeCierre = null;
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
        abort_unless(tienePermiso(15), 403);
        $this->confirmarDic = false;

        $key = 'calificacionesSecundario:cierreAnual:dic:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('cierre', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 120);

        $ctx = schoolCtx();
        $res = CierreAnualSecundario::pasarAprobadasMatrizDic(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );

        $this->informeCierre = CierreAnualSecundario::armarInformeCierre('dic', $res, $ctx);
    }

    public function ejecutarCierreFeb(): void
    {
        abort_unless(tienePermiso(15), 403);
        $this->confirmarFeb = false;

        $key = 'calificacionesSecundario:cierreAnual:feb:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('cierre', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 120);

        $ctx = schoolCtx();
        $res = CierreAnualSecundario::pasarAprobadasMatrizYPreviasFeb(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );

        $this->informeCierre = CierreAnualSecundario::armarInformeCierre('feb', $res, $ctx);
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
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Cierre anual (secundario)']);
    }
}
