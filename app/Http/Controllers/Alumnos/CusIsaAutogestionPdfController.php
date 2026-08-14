<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Certificados\CertificadoUnicoSaludTcpdf;
use App\Support\Certificados\CusIsaVozImagenDatos;
use App\Support\Certificados\InformeSaludAnualTcpdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * C.U.S. e I.S.A. en PDF para el alumno/familia en sesión (ciclo de autogestión).
 */
class CusIsaAutogestionPdfController extends Controller
{
    public function cus(Request $request)
    {
        abort_unless(tenantAutogestionCusHabilitada(), 404);

        return $this->pdf($request, CusIsaVozImagenDatos::TIPO_CUS);
    }

    public function isa(Request $request)
    {
        abort_unless(tenantAutogestionIsaHabilitada(), 404);

        return $this->pdf($request, CusIsaVozImagenDatos::TIPO_ISA);
    }

    private function pdf(Request $request, string $tipo)
    {
        $key = 'alumnos-'.$tipo.'-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $alumno = CusIsaVozImagenDatos::alumnoParaAutogestion();
        if ($alumno === null) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => 'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.',
            ], 422);
        }

        $slugBase = match ($tipo) {
            CusIsaVozImagenDatos::TIPO_CUS => 'certificado-unico-salud',
            default => 'informe-salud-anual',
        };

        $slug = Str::slug(
            $slugBase.'-'.trim((string) ($alumno['apellido'] ?? '').'-'.(string) ($alumno['nombre'] ?? '')),
            '_',
        );
        if ($slug === '') {
            $slug = $slugBase;
        }

        $header = studentPdfHeaderData();
        $insti = trim((string) ($header['insti'] ?? ''));

        return match ($tipo) {
            CusIsaVozImagenDatos::TIPO_CUS => CertificadoUnicoSaludTcpdf::respuestaHttp(
                CertificadoUnicoSaludTcpdf::generarLote([$alumno], $header),
                $slug.'.pdf',
            ),
            default => InformeSaludAnualTcpdf::respuestaHttp(
                InformeSaludAnualTcpdf::generarLote([$alumno], $insti),
                $slug.'.pdf',
            ),
        };
    }
}
