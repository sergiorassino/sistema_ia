<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Support\Certificados\ConstanciaDocumentos;
use App\Support\Certificados\ConstanciaDocumentosDatos;
use App\Support\Certificados\ConstanciaDocumentosTcpdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ConstanciaDocumentosPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(tienePermiso(19), 403, 'Sin permiso para constancias de documentos.');

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;

        $validator = Validator::make(
            $request->all(),
            array_merge(
                ['idLegajos' => ['required', 'integer', 'min:1']],
                ConstanciaDocumentos::reglasFormulario(),
            ),
            ConstanciaDocumentos::mensajesValidacion(),
        );

        if ($validator->fails()) {
            abort(422, 'Datos de la constancia incompletos o inválidos.');
        }

        $validated = $validator->validated();
        $idLegajos = (int) $validated['idLegajos'];
        unset($validated['idLegajos']);

        if ($idNivel < 1 || $idLegajos < 1) {
            abort(404);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $key = 'const-docu-pdf:'.(auth()->id() ?? $request->ip()).':'.$idLegajos;
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        /** @var array{certifde: string, otorpor: string, fechotor: string, parnacop: string, parapre: string, fechemis: string} $form */
        $form = $validated;
        $form['certifde'] = trim((string) $form['certifde']);
        $form['otorpor'] = trim((string) $form['otorpor']);
        $form['parnacop'] = trim((string) $form['parnacop']);
        $form['parapre'] = trim((string) $form['parapre']);

        $datos = ConstanciaDocumentosDatos::paraLegajo($idLegajos, $idNivel, $form);
        if ($datos === null) {
            abort(404, 'Alumno sin matrícula histórica en el nivel activo.');
        }

        $pdf = ConstanciaDocumentosTcpdf::generar($datos);

        $leg = $datos['legajo'];
        $slug = Str::slug(
            'constancia-documentos-'.($leg['apellido'] ?? '').'-'.($leg['nombre'] ?? '').'-'.$idLegajos,
            '_',
        );
        if ($slug === '') {
            $slug = 'constancia_documentos_'.$idLegajos;
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
