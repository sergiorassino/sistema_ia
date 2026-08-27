<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Support\Certificados\CertificadoFinalizacionNivel;
use App\Support\Certificados\CertificadoFinalizacionNivelDatos;
use App\Support\Certificados\CertificadoJardinTcpdf;
use App\Support\Certificados\CertificadoSextoGradoTcpdf;
use App\Support\PermisosIaCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF en lote: certificado de jardín o de sexto grado.
 */
class CertificadoFinalizacionNivelPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CERT_JARDIN_SEXTO_GRADO), 403);

        $tipo = CertificadoFinalizacionNivel::tipoDesdeRuta($request->route()?->getName());
        if ($tipo === null) {
            abort(404);
        }

        CertificadoFinalizacionNivel::abortSiNivelIncorrecto($tipo);

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $uid = (string) (auth()->id() ?? '');
        $key = 'cert-finalizacion-pdf:'.$tipo.':'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = $request->validate(
            array_merge(
                [
                    'curso' => ['required', 'integer', 'min:1'],
                    'matriculas' => ['required', 'array', 'min:1', 'max:'.CertificadoFinalizacionNivel::MAX_MATRICULAS],
                    'matriculas.*' => ['integer', 'min:1'],
                ],
                CertificadoFinalizacionNivel::reglasFormulario(),
            ),
            CertificadoFinalizacionNivel::mensajesValidacion(),
        );

        $cursoId = (int) $validated['curso'];
        $ids = CertificadoFinalizacionNivel::resolverIdsMatriculas(
            $tipo,
            $cursoId,
            array_map('intval', $validated['matriculas']),
        );

        if ($ids === []) {
            abort(404);
        }

        $form = [
            'serie' => trim((string) ($validated['serie'] ?? '')),
            'mesApro' => trim((string) $validated['mesApro']),
            'anoApro' => trim((string) $validated['anoApro']),
            'diaEmision' => trim((string) $validated['diaEmision']),
            'mesEmision' => trim((string) $validated['mesEmision']),
            'anoEmision' => trim((string) $validated['anoEmision']),
            'ppi' => trim((string) ($validated['ppi'] ?? '')),
        ];

        $hojas = CertificadoFinalizacionNivelDatos::alumnosParaPdf($tipo, $cursoId, $ids, $form);
        if ($hojas === []) {
            abort(404);
        }

        $slugBase = $tipo === CertificadoFinalizacionNivel::TIPO_JARDIN
            ? 'certificado-jardin'
            : 'certificado-sexto-grado';
        $slug = Str::slug($slugBase.'-'.count($hojas).'-alumnos', '_');
        if ($slug === '') {
            $slug = $slugBase;
        }

        if ($tipo === CertificadoFinalizacionNivel::TIPO_JARDIN) {
            $pdf = CertificadoJardinTcpdf::generarLote($hojas);

            return CertificadoJardinTcpdf::respuestaHttp($pdf, $slug.'.pdf');
        }

        $pdf = CertificadoSextoGradoTcpdf::generarLote($hojas);

        return CertificadoSextoGradoTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
