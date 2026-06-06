<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Support\Certificados\CertificadoEstudiosTramite;
use App\Support\Certificados\CertificadoEstudiosTramiteDatos;
use App\Support\Certificados\CertificadoEstudiosTramiteTcpdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CertificadoEstudiosTramitePdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(tienePermiso(18), 403, 'Sin permiso para constancias de certificado en trámite.');

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        $validator = Validator::make(
            $request->all(),
            array_merge(
                ['idLegajos' => ['required', 'integer', 'min:1']],
                CertificadoEstudiosTramite::reglasFormulario(),
            ),
            CertificadoEstudiosTramite::mensajesValidacion(),
        );

        if ($validator->fails()) {
            abort(422, 'Datos de la constancia incompletos o inválidos.');
        }

        $validated = $validator->validated();
        $idLegajos = (int) $validated['idLegajos'];
        unset($validated['idLegajos']);

        if ($idNivel < 1 || $idTerlec < 1 || $idLegajos < 1) {
            abort(404);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $key = 'cert-estu-tram-pdf:'.(auth()->id() ?? $request->ip()).':'.$idLegajos;
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        /** @var array{mateAdeud: string, idiomaCursado: string, preAnte: string, fechaEmision: string} $form */
        $form = $validated;
        $form['mateAdeud'] = trim((string) ($form['mateAdeud'] ?? ''));
        $form['idiomaCursado'] = trim((string) $form['idiomaCursado']);
        $form['preAnte'] = trim((string) $form['preAnte']);

        $datos = CertificadoEstudiosTramiteDatos::paraLegajo($idLegajos, $idNivel, $idTerlec, $form);
        if ($datos === null) {
            abort(404, 'Alumno no matriculado en el ciclo lectivo activo.');
        }

        $pdf = CertificadoEstudiosTramiteTcpdf::generar($datos);

        $leg = $datos['legajo'];
        $slug = Str::slug(
            'constancia-certificado-tramite-'.($leg['apellido'] ?? '').'-'.($leg['nombre'] ?? '').'-'.$idLegajos,
            '_',
        );
        if ($slug === '') {
            $slug = 'constancia_certificado_tramite_'.$idLegajos;
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
