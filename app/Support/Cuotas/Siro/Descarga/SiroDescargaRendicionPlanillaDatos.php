<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\RendicionRoela;
use App\Support\Cuotas\CuotasFormato;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;

/**
 * Arma los datos del PDF de planilla de descarga SIRO.
 */
final class SiroDescargaRendicionPlanillaDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function build(int $nroPlanilla): ?array
    {
        if ($nroPlanilla < 1) {
            return null;
        }

        $consulta = new SiroDescargaRendicionConsulta;
        $planilla = $consulta->planillaPorNro($nroPlanilla);
        if ($planilla === null) {
            return null;
        }

        $canal = collect(SiroDescargaRendicionCanal::opcionesPlanilla())
            ->firstWhere('id', (int) ($planilla->canalPago ?? 0));

        $rendiciones = $consulta->rendicionesDePlanilla($nroPlanilla);
        $filas = [];
        $totalImporte = 0.0;
        $totalInteres = 0.0;
        $totalBonificacion = 0.0;
        $totalPagado = 0.0;

        foreach ($rendiciones as $i => $r) {
            $importe = round((float) ($r->importe ?? 0), 2);
            $interes = round((float) ($r->interes ?? 0), 2);
            $bonificacion = round((float) ($r->bonificacion ?? 0), 2);
            $pagado = round((float) ($r->pagado ?? 0), 2);

            $totalImporte += $importe;
            $totalInteres += $interes;
            $totalBonificacion += $bonificacion;
            $totalPagado += $pagado;

            $filas[] = self::filaDesdeRendicion($r, $i + 1, $importe, $interes, $bonificacion, $pagado);
        }

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'nroPlanilla' => (int) $planilla->nroPlanilla,
            'nroPlanillaEtiqueta' => number_format((int) $planilla->nroPlanilla, 0, ',', '.'),
            'fechaCarga' => $planilla->fecha?->format('d/m/Y') ?? '—',
            'canalPago' => (string) ($canal['label'] ?? ($planilla->canalPago ?? '—')),
            'nombreArchivo' => trim((string) ($planilla->nombreArchivo ?? '')),
            'impactada' => (int) ($planilla->impactado ?? 0) === 1,
            'cantidad' => count($filas),
            'filas' => $filas,
            'totales' => [
                'importe' => CuotasFormato::formatearImporte($totalImporte),
                'interes' => CuotasFormato::formatearImporte($totalInteres),
                'bonificacion' => CuotasFormato::formatearImporte($totalBonificacion),
                'pagado' => CuotasFormato::formatearImporte($totalPagado),
            ],
        ];
    }

    /**
     * @return array{
     *     item: string,
     *     fechaPago: string,
     *     canal: string,
     *     estudiante: string,
     *     curso: string,
     *     cuota: string,
     *     venc1: string,
     *     beca: string,
     *     importe: string,
     *     interes: string,
     *     bonificacion: string,
     *     pagado: string,
     *     impactado: string
     * }
     */
    private static function filaDesdeRendicion(
        RendicionRoela $r,
        int $item,
        float $importe,
        float $interes,
        float $bonificacion,
        float $pagado,
    ): array {
        $legajo = $r->legajo;
        $nombreEst = $legajo
            ? mb_strtoupper(trim((string) $legajo->apellido.' '.(string) $legajo->nombre))
            : '—';

        $cursoEtiqueta = $r->curso
            ? GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($r->curso)
            : '—';

        $becaPct = $r->beca?->porcentaje;
        $beca = $becaPct !== null
            ? rtrim(rtrim(number_format((float) $becaPct, 2, ',', '.'), '0'), ',')
            : '';

        return [
            'item' => (string) $item,
            'fechaPago' => $r->fechaPago?->format('d/m/Y') ?? '—',
            'canal' => trim((string) ($r->tipoPago?->abrev ?? '')),
            'estudiante' => $nombreEst !== '' ? $nombreEst : '—',
            'curso' => $cursoEtiqueta,
            'cuota' => trim((string) ($r->cuota?->nombre ?? '')) ?: '—',
            'venc1' => $r->fechVenc1?->format('d/m/Y') ?? '—',
            'beca' => $beca,
            'importe' => CuotasFormato::formatearImporte($importe),
            'interes' => CuotasFormato::formatearImporte($interes),
            'bonificacion' => CuotasFormato::formatearImporte($bonificacion),
            'pagado' => CuotasFormato::formatearImporte($pagado),
            'impactado' => (int) ($r->impactado ?? 0) === 1 ? 'Sí' : 'No',
        ];
    }
}
