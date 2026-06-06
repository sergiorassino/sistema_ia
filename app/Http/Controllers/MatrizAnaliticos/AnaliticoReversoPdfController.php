<?php

namespace App\Http\Controllers\MatrizAnaliticos;

use App\Http\Controllers\Controller;
use App\Support\MatrizAnaliticos\AnaliticoReversoDatos;
use App\Support\MatrizAnaliticos\AnaliticoReversoTcpdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AnaliticoReversoPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(tienePermiso(16), 403, 'Sin permiso para Libro Matriz / Analítico.');

        $validated = $request->validate([
            'idLegajos' => ['required', 'integer', 'min:1'],
        ]);
        $idLegajos = (int) $validated['idLegajos'];

        $ctx = schoolCtx();
        if (! str_contains(mb_strtolower($ctx->nivelNombre()), 'secundari')) {
            abort(403, 'Este módulo requiere contexto de Secundario.');
        }

        $idNivel = (int) $ctx->idNivel;
        if ($idNivel < 1 || $idLegajos < 1) {
            abort(404);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $key = 'analitico-reverso-pdf:'.(auth()->id() ?? $request->ip()).':'.$idLegajos;
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = AnaliticoReversoDatos::paraLegajo($idLegajos, $idNivel);
        if ($datos === null) {
            abort(404);
        }

        $pdf = AnaliticoReversoTcpdf::generar($datos);

        $leg = $datos['legajo'];
        $slug = Str::slug(
            'analitico-reverso-'.($leg['apellido'] ?? '').'-'.($leg['nombre'] ?? '').'-'.$idLegajos,
            '_',
        );
        if ($slug === '') {
            $slug = 'analitico_reverso_'.$idLegajos;
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
