<?php

namespace App\Http\Controllers\Examenes;

use App\Http\Controllers\Controller;
use App\Support\Examenes\TercerMateriaGestor;
use App\Support\Examenes\TercerMateriaGrillaTcpdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class TercerMateriaPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');
        abort_unless(tenantBoletinMuestraTercerMateria(), 404);

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $key = 'tercer-materia-grilla-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            abort(404);
        }

        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;
        $filas = TercerMateriaGestor::filas($idNivel, $idTerlec);

        if ($filas === []) {
            abort(404);
        }

        $header = schoolPdfHeaderData();
        $insti = trim((string) ($header['insti'] ?? ''));
        if ($insti === '') {
            $insti = 'Institución educativa';
        }

        $pdf = TercerMateriaGrillaTcpdf::generar(
            [
                'instiNombre' => mb_strtoupper($insti, 'UTF-8'),
                'nivelNombre' => (string) ($ctx->nivelNombre() ?? ''),
                'cicloAno' => (string) ($ctx->terlecAno() ?? ''),
                'logo_abs' => $header['logo_file'] ?? null,
                'fechaImpresion' => now()->format('d/m/Y H:i'),
            ],
            $filas,
        );

        $slug = Str::slug('tercer-materia-'.($ctx->terlecAno() ?? ''), '_');
        if ($slug === '') {
            $slug = 'tercer_materia';
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
