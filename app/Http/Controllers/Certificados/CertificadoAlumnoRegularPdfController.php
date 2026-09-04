<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Support\Certificados\CertificadoAlumnoRegular;
use App\Support\Certificados\CertificadoAlumnoRegularDatos;
use App\Support\Certificados\CertificadoAlumnoRegularEscolarTcpdf;
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

        $tipo = (string) $request->input('tipo', CertificadoAlumnoRegular::TIPO_LABORAL);
        if (! CertificadoAlumnoRegular::esTipoValido($tipo)) {
            abort(422, 'Tipo de certificado no válido.');
        }

        $validator = Validator::make(
            $request->all(),
            array_merge(
                [
                    'idLegajos' => ['required', 'integer', 'min:1'],
                    'tipo' => ['required', 'string', 'in:'.implode(',', CertificadoAlumnoRegular::tiposValidos())],
                ],
                CertificadoAlumnoRegular::reglasFormulario($tipo),
            ),
            CertificadoAlumnoRegular::mensajesValidacion(),
        );

        if ($validator->fails()) {
            abort(422, 'Datos del certificado incompletos o inválidos.');
        }

        $validated = $validator->validated();
        $idLegajos = (int) $validated['idLegajos'];
        unset($validated['idLegajos'], $validated['tipo']);

        if ($idNivel < 1 || $idTerlec < 1 || $idLegajos < 1) {
            abort(404);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $key = 'cert-alu-reg-pdf:'.(auth()->id() ?? $request->ip()).':'.$tipo.':'.$idLegajos;
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $form = CertificadoAlumnoRegular::completarParaGuardar($validated, $tipo);

        $datos = CertificadoAlumnoRegularDatos::paraLegajo($idLegajos, $idNivel, $idTerlec, $form);
        if ($datos === null) {
            abort(404, 'Alumno no matriculado en el ciclo lectivo activo.');
        }

        $pdf = $tipo === CertificadoAlumnoRegular::TIPO_ESCOLAR
            ? CertificadoAlumnoRegularEscolarTcpdf::generar($datos)
            : CertificadoAlumnoRegularTcpdf::generar($datos);

        $leg = $datos['legajo'];
        if ($tipo === CertificadoAlumnoRegular::TIPO_ESCOLAR) {
            $slug = Str::slug(
                'constancia-alumno-regular-escolar-'.($leg['apellido'] ?? '').'-'.($leg['nombre'] ?? ''),
                '_',
            );
            if ($slug === '') {
                $slug = 'constancia_alumno_regular_escolar';
            }
        } else {
            $slug = Str::slug(
                'certificado-alumno-regular-'.($leg['apellido'] ?? '').'-'.($leg['nombre'] ?? '').'-'.$idLegajos,
                '_',
            );
            if ($slug === '') {
                $slug = 'certificado_alumno_regular_'.$idLegajos;
            }
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
