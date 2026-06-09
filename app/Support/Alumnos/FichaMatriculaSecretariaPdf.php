<?php

namespace App\Support\Alumnos;

use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Genera el PDF en lote de ficha de matrícula (secretaría) según la variante del tenant.
 */
final class FichaMatriculaSecretariaPdf
{
    /**
     * @param  list<int>  $matriculaIds
     */
    public static function respuestaHttp(array $matriculaIds): Response
    {
        abort_unless(tenantSecretariaFichaMatriculaHabilitada(), 404);

        $ids = FichaMatriculaSecretariaLoteParams::resolverIdsMatriculas($matriculaIds);
        if ($ids === []) {
            abort(404);
        }

        $implementacion = tenantSecretariaFichaMatriculaImplementacion();
        $hojas = [];
        $header = schoolPdfHeaderData();

        foreach ($ids as $idMatricula) {
            $datos = match ($implementacion) {
                'sanfranciscoasis' => FichaMatriculaDatos::paraMatricula($idMatricula),
                'montecristo' => FichaMatriculaMontecristoDatos::paraMatricula($idMatricula),
                default => null,
            };

            if ($datos !== null) {
                $hojas[] = $datos;
            }
        }

        if ($hojas === []) {
            abort(404);
        }

        $cantidad = count($hojas);

        if ($cantidad === 1) {
            $apellido = trim((string) ($hojas[0]['apellido'] ?? ''));
            $nombre = trim((string) ($hojas[0]['nombre'] ?? ''));
            $slug = Str::slug('ficha-matricula-'.$apellido.'-'.$nombre, '_');
        } else {
            $slug = Str::slug('fichas-matricula-'.$cantidad.'-alumnos', '_');
        }

        if ($slug === '') {
            $slug = $implementacion === 'montecristo'
                ? 'ficha_solicitud_matricula'
                : 'ficha_matricula';
        }

        return match ($implementacion) {
            'sanfranciscoasis' => FichaMatriculaConAceptacionTcpdf::respuestaHttp(
                FichaMatriculaConAceptacionTcpdf::generarLote($hojas, $header),
                $slug.'.pdf',
            ),
            'montecristo' => FichaMatriculaSolicitudMontecristoTcpdf::respuestaHttp(
                FichaMatriculaSolicitudMontecristoTcpdf::generarLote($hojas),
                $slug.'.pdf',
            ),
            default => abort(404),
        };
    }
}
