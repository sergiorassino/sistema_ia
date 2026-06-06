<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Support\Certificados\CertificadoAlumnoRegular;
use App\Support\Certificados\CertificadoAlumnoRegularDatos;
use App\Support\Certificados\CertificadoAlumnoRegularTcpdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CertificadoAlumnoRegularPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(tienePermiso(17), 403, 'Sin permiso para certificados de alumno regular.');

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        $validator = Validator::make(
            $request->all(),
            array_merge(
                ['idLegajos' => ['required', 'integer', 'min:1']],
                CertificadoAlumnoRegular::reglasFormulario(),
            ),
            CertificadoAlumnoRegular::mensajesValidacion(),
        );

        if ($validator->fails()) {
            abort(422, 'Datos del certificado incompletos o inválidos.');
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

        $key = 'cert-alu-reg-pdf:'.(auth()->id() ?? $request->ip()).':'.$idLegajos;
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        /** @var array{iniFin: int, fechIniFin: string, prePor: string, prePorDni: string, preAnte: string, fechaEmision: string} $form */
        $form = $validated;
        $form['iniFin'] = (int) $form['iniFin'];
        $form['prePor'] = trim((string) $form['prePor']);
        $form['prePorDni'] = trim((string) $form['prePorDni']);
        $form['preAnte'] = trim((string) $form['preAnte']);

        $datos = CertificadoAlumnoRegularDatos::paraLegajo($idLegajos, $idNivel, $idTerlec, $form);
        if ($datos === null) {
            abort(404, 'Alumno no matriculado en el ciclo lectivo activo.');
        }

        $pdf = CertificadoAlumnoRegularTcpdf::generar($datos);

        $leg = $datos['legajo'];
        $slug = Str::slug(
            'certificado-alumno-regular-'.($leg['apellido'] ?? '').'-'.($leg['nombre'] ?? '').'-'.$idLegajos,
            '_',
        );
        if ($slug === '') {
            $slug = 'certificado_alumno_regular_'.$idLegajos;
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
