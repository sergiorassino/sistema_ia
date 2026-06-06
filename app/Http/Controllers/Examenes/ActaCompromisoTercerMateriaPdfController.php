<?php

namespace App\Http\Controllers\Examenes;

use App\Http\Controllers\Controller;
use App\Support\Examenes\ActaCompromisoTercerMateriaTcpdf;
use App\Support\Examenes\TercerMateriaGestor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ActaCompromisoTercerMateriaPdfController extends Controller
{
    public function __invoke(Request $request, int $idCalificacion): Response
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');
        abort_unless(tenantBoletinMuestraTercerMateria(), 404);

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $key = 'acta-compromiso-tm-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            abort(404);
        }

        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        $datosActa = TercerMateriaGestor::datosActaCompromiso($idCalificacion, $idNivel, $idTerlec);
        if ($datosActa === null) {
            abort(404);
        }

        $fechaYmd = trim((string) $request->query('fecha', ''));
        if ($fechaYmd !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaYmd)) {
            abort(404);
        }

        $payload = ActaCompromisoTercerMateriaTcpdf::datosDesdeContexto(
            $datosActa,
            $fechaYmd !== '' ? $fechaYmd : null,
        );

        $pdf = ActaCompromisoTercerMateriaTcpdf::generar($payload);

        $slug = Str::slug('acta-compromiso-'.($datosActa['apenom'] ?? 'tercer-materia'), '_');
        if ($slug === '') {
            $slug = 'acta_compromiso_tercer_materia';
        }

        $this->limpiarBuffersSalida();
        $binario = $pdf->Output($slug.'.pdf', 'S');

        return response($binario, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$slug.'.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function limpiarBuffersSalida(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}
