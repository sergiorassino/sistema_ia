<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\Certificados\CertificadoUnicoSaludTcpdf;
use App\Support\Certificados\CusIsaVozImagenDatos;
use App\Support\Certificados\InformeSaludAnualTcpdf;
use App\Support\Certificados\UsoImagenVozTcpdf;
use App\Support\PermisosIaCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF en lote: C.U.S., I.S.A. y autorización de uso de imagen y voz.
 */
class CusIsaVozImagenPdfController extends Controller
{
    public function __invoke(Request $request, string $tipo)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CERT_CUS_ISA_VOZ_IMAGEN), 403);

        $tipoValido = CusIsaVozImagenDatos::tipoValido($tipo);
        if ($tipoValido === null) {
            abort(404);
        }

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $uid = (string) (auth()->id() ?? '');
        $key = 'cert-cus-isa-voz-pdf:'.$tipoValido.':'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'curso' => ['required', 'integer', 'min:1'],
            'matriculas' => ['required', 'array', 'min:1', 'max:'.BoletinSecundarioLoteParams::MAX_MATRICULAS],
            'matriculas.*' => ['integer', 'min:1'],
        ]);

        $cursoId = (int) $validated['curso'];
        $ids = BoletinSecundarioLoteParams::resolverIdsMatriculasDesdeLista(
            array_map('intval', $validated['matriculas']),
            $cursoId,
        );

        if ($ids === []) {
            abort(404);
        }

        $alumnos = CusIsaVozImagenDatos::alumnosParaPdf($ids, $cursoId);
        if ($alumnos === []) {
            abort(404);
        }

        $cantidad = count($alumnos);
        $slugBase = match ($tipoValido) {
            CusIsaVozImagenDatos::TIPO_CUS => 'certificado-unico-salud',
            CusIsaVozImagenDatos::TIPO_ISA => 'informe-salud-anual',
            CusIsaVozImagenDatos::TIPO_VOZ_IMAGEN => 'uso-imagen-voz',
            default => 'certificados-salud',
        };

        $slug = Str::slug($slugBase.'-'.$cantidad.'-alumnos', '_');
        if ($slug === '') {
            $slug = $slugBase;
        }

        $ctx = CusIsaVozImagenDatos::contextoInstitucional();

        $pdf = match ($tipoValido) {
            CusIsaVozImagenDatos::TIPO_CUS => CertificadoUnicoSaludTcpdf::generarLote($alumnos),
            CusIsaVozImagenDatos::TIPO_ISA => InformeSaludAnualTcpdf::generarLote($alumnos, (string) $ctx['insti']),
            CusIsaVozImagenDatos::TIPO_VOZ_IMAGEN => UsoImagenVozTcpdf::generarLote($alumnos),
        };

        return match ($tipoValido) {
            CusIsaVozImagenDatos::TIPO_CUS => CertificadoUnicoSaludTcpdf::respuestaHttp($pdf, $slug.'.pdf'),
            CusIsaVozImagenDatos::TIPO_ISA => InformeSaludAnualTcpdf::respuestaHttp($pdf, $slug.'.pdf'),
            CusIsaVozImagenDatos::TIPO_VOZ_IMAGEN => UsoImagenVozTcpdf::respuestaHttp($pdf, $slug.'.pdf'),
        };
    }
}
