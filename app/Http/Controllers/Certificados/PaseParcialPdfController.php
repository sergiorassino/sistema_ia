<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Support\Certificados\PaseParcial;
use App\Support\Certificados\PaseParcialDatos;
use App\Support\Certificados\PaseParcialTcpdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaseParcialPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(tienePermiso(21), 403, 'Sin permiso para pase parcial.');

        $ctx = schoolCtx();
        $idTerlec = (int) $ctx->idTerlec;

        $validator = Validator::make(
            $request->all(),
            array_merge(
                ['idLegajos' => ['required', 'integer', 'min:1']],
                PaseParcial::reglasFormulario(),
            ),
            PaseParcial::mensajesValidacion(),
        );

        if ($validator->fails()) {
            abort(422, 'Datos de la solicitud incompletos o inválidos.');
        }

        $validated = $validator->validated();
        $idLegajos = (int) $validated['idLegajos'];
        unset($validated['idLegajos']);

        if ($idLegajos < 1 || $idTerlec < 1) {
            abort(404);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $key = 'pase-parcial-pdf:'.(auth()->id() ?? $request->ip()).':'.$idLegajos;
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        /** @var array{fecha: string, destino: string} $form */
        $form = $validated;
        $form['destino'] = trim((string) $form['destino']);

        $datos = PaseParcialDatos::paraLegajo($idLegajos, $idTerlec, $form);
        if ($datos === null) {
            abort(404, 'Alumno sin matrícula de nivel medio en el ciclo lectivo activo.');
        }

        $pdf = PaseParcialTcpdf::generar($datos);

        $leg = $datos['legajo'];
        $slug = Str::slug(
            'pase-parcial-'.($leg['apellido'] ?? '').'-'.($leg['nombre'] ?? '').'-'.$idLegajos,
            '_',
        );
        if ($slug === '') {
            $slug = 'pase_parcial_'.$idLegajos;
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
