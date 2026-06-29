<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Support\Certificados\CertificadoAsistenciaProfesor;
use App\Support\Certificados\CertificadoAsistenciaProfesorDatos;
use App\Support\Certificados\CertificadoAsistenciaProfesorTcpdf;
use App\Support\Pdf\PdfPostEntrega;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class CertificadoAsistenciaProfesorPdfController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(tienePermiso(20), 403, 'Sin permiso para certificados de asistencia del profesor.');

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;

        $validator = Validator::make(
            $request->all(),
            array_merge(
                ['idProfesores' => ['required', 'integer', 'min:1']],
                CertificadoAsistenciaProfesor::reglasFormulario(),
            ),
            CertificadoAsistenciaProfesor::mensajesValidacion(),
        );

        if ($validator->fails()) {
            abort(422, 'Datos del certificado incompletos o inválidos.');
        }

        $validated = $validator->validated();
        $idProfesores = (int) $validated['idProfesores'];
        unset($validated['idProfesores']);

        if ($idProfesores < 1) {
            abort(404);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $key = 'cert-asist-prof-pdf:'.(auth()->id() ?? $request->ip()).':'.$idProfesores;
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        /** @var array{fecha: string, texto: string, parapre: string} $form */
        $form = $validated;
        $form['texto'] = trim((string) $form['texto']);
        $form['parapre'] = trim((string) $form['parapre']);

        $datos = CertificadoAsistenciaProfesorDatos::paraProfesor($idProfesores, $idNivel, $form);
        if ($datos === null) {
            abort(404, 'Profesor no encontrado o sin rol asignado.');
        }

        $pdf = CertificadoAsistenciaProfesorTcpdf::generar($datos);

        $nombreArchivo = CertificadoAsistenciaProfesor::nombreArchivoPdf($datos['profesor']);

        $this->limpiarBuffersSalida();

        $binario = $pdf->Output($nombreArchivo, 'S');

        return PdfPostEntrega::respuesta($binario, $nombreArchivo, $request);
    }

    private function limpiarBuffersSalida(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}
