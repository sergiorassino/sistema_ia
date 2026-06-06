<?php

namespace App\Http\Controllers\Docentes;

use App\Http\Controllers\Controller;
use App\Support\InasistenciasDocentes;
use App\Support\InasistenciasDocentes\RankingMateriasCursos;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RankingInasistenciasMateriasCursosCsvController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless(tienePermiso(InasistenciasDocentes::PERMISO_ORDEN), 403);
        abort_unless(RankingMateriasCursos::tieneDetalle(), 404);

        $anio = (int) $request->query('anio', InasistenciasDocentes::anoLectivo());
        $periodo = (int) $request->query('periodo', 0);
        $sort = (string) $request->query('sort', 'total');
        $dir = (string) $request->query('dir', 'DESC');

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        $idTerlec = (int) (schoolCtx()->idTerlec ?? 0);
        $datos = RankingMateriasCursos::datos($anio, $periodo, $sort, $dir, $idNivel, $idTerlec);

        $sufijo = $periodo > 0 ? '_bim'.$periodo : '_anual';
        $nombre = 'ranking_inasistencias_'.$anio.$sufijo.'.csv';

        return response()->streamDownload(function () use ($datos) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Curso', 'Materia', 'Total inasistencias'], ';');
            foreach ($datos['filas'] as $r) {
                fputcsv($out, [
                    $r['curso'],
                    $r['materia'],
                    number_format($r['total'], 1, ',', ''),
                ], ';');
            }
            fclose($out);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
