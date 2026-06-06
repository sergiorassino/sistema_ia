<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Support\Certificados\SolicitudDePase;
use App\Support\Certificados\SolicitudDePaseDatos;
use App\Support\Certificados\SolicitudDePaseTcpdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SolicitudDePasePdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(tienePermiso(22), 403, 'Sin permiso para solicitud de pase.');

        $validator = Validator::make(
            $request->all(),
            array_merge(
                ['idLegajos' => ['required', 'integer', 'min:1']],
                SolicitudDePase::reglasFormulario(),
            ),
            SolicitudDePase::mensajesValidacion(),
        );

        if ($validator->fails()) {
            abort(422, 'Datos de la solicitud incompletos o inválidos.');
        }

        $validated = $validator->validated();
        $idLegajos = (int) $validated['idLegajos'];
        unset($validated['idLegajos']);

        if ($idLegajos < 1) {
            abort(404);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $key = 'solicitud-de-pase-pdf:'.(auth()->id() ?? $request->ip()).':'.$idLegajos;
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        /** @var array{
         *     fechaEmision: string,
         *     cursosCompletos: string,
         *     mateAdeud: string,
         *     cursar: string,
         *     preAnte: string
         * } $form */
        $form = $validated;
        $form['cursosCompletos'] = trim((string) ($form['cursosCompletos'] ?? ''));
        $form['mateAdeud'] = trim((string) ($form['mateAdeud'] ?? ''));
        $form['cursar'] = trim((string) ($form['cursar'] ?? ''));
        $form['preAnte'] = trim((string) $form['preAnte']);

        $datos = SolicitudDePaseDatos::paraLegajo($idLegajos, $form);
        if ($datos === null) {
            abort(404, 'Alumno no pertenece al legajo de nivel medio.');
        }

        $pdf = SolicitudDePaseTcpdf::generar($datos);

        $leg = $datos['legajo'];
        $slug = Str::slug(
            'solicitud-de-pase-'.($leg['apellido'] ?? '').'-'.($leg['nombre'] ?? '').'-'.$idLegajos,
            '_',
        );
        if ($slug === '') {
            $slug = 'solicitud_de_pase_'.$idLegajos;
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
