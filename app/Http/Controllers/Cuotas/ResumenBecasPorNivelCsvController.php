<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\ResumenBecasPorNivelConsulta;
use App\Support\PermisosCuotas;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumenBecasPorNivelCsvController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        abort_unless(PermisosCuotas::puedeResumenBecasPorNivel(), 403);

        $ano = (int) schoolCtx()->terlecAno();
        $resumen = ResumenBecasPorNivelConsulta::resumen();
        $niveles = $resumen['niveles'];
        $nombre = 'resumen_becas_por_nivel_'.$ano.'.csv';

        return response()->streamDownload(function () use ($resumen, $niveles) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF");

            $encabezado = ['Nombre Beca'];
            foreach ($niveles as $nivel) {
                $encabezado[] = $nivel['nombre'];
            }
            $encabezado[] = 'TOTAL';
            fputcsv($out, $encabezado, ';');

            foreach ($resumen['filas'] as $fila) {
                $row = [$fila['nombreBeca']];
                foreach ($niveles as $nivel) {
                    $row[] = (string) ($fila['porNivel'][$nivel['id']] ?? 0);
                }
                $row[] = (string) $fila['total'];
                fputcsv($out, $row, ';');
            }

            $totales = ['Total Acumulado - Suma'];
            foreach ($niveles as $nivel) {
                $totales[] = (string) ($resumen['totalesNivel'][$nivel['id']] ?? 0);
            }
            $totales[] = (string) $resumen['totalGeneral'];
            fputcsv($out, $totales, ';');

            fclose($out);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
