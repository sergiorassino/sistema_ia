<?php

namespace App\Support\Cuotas;

use App\Models\CuotaPago;
use Illuminate\Support\Collection;

/**
 * Datos para el PDF «Resumen de pagos» del estudiante (todos los ciclos lectivos).
 */
final class ResumenPagosEstudianteDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function paraLegajo(int $idLegajo): ?array
    {
        if (GestionAranceles::legajoParaGestion($idLegajo) === null) {
            return null;
        }

        $encabezado = GestionAranceles::encabezadoEstudiante($idLegajo);
        if ($encabezado === null) {
            return null;
        }

        $filas = self::filasPagos($idLegajo);
        $totales = self::calcularTotales($filas);

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'fechaImpresion' => now()->format('d/m/Y H:i'),
            'apellidoNombre' => mb_strtoupper(trim(($encabezado['apellido'] ?? '').' '.($encabezado['nombre'] ?? ''))),
            'dni' => (string) ($encabezado['dni'] ?? ''),
            'curso' => (string) ($encabezado['curso'] ?? ''),
            'terlecAno' => (string) ($encabezado['terlecAno'] ?? schoolCtx()->terlecAno()),
            'filas' => $filas,
            'totales' => $totales,
        ];
    }

    /**
     * @return list<array{
     *     ano: string,
     *     cuota: string,
     *     fechaHora: string,
     *     medioPago: string,
     *     importe: string,
     *     bonificacion: string,
     *     interes: string,
     *     abonado: string
     * }>
     */
    private static function filasPagos(int $idLegajo): array
    {
        return self::consultaPagos($idLegajo)
            ->map(function (CuotaPago $pago): array {
                $importe = round((float) ($pago->importe ?? 0), 2);
                $bonificacion = round((float) ($pago->bonificacion ?? 0), 2);
                $interes = round((float) ($pago->interes ?? 0), 2);
                $abonado = round(max(0, $importe + $interes - $bonificacion), 2);

                $medioPago = trim((string) ($pago->tipoPago?->abrev ?? ''));
                if ($medioPago === '') {
                    $medioPago = trim((string) ($pago->tipoPago?->tipoPago ?? ''));
                }

                return [
                    'ano' => (string) ($pago->cuotaGenerada?->terlec?->ano ?? ''),
                    'cuota' => mb_strtoupper(trim((string) ($pago->cuotaGenerada?->cuota?->nombre ?? ''))),
                    'fechaHora' => CuotasFormato::formatearFechaHora($pago->fechhora),
                    'medioPago' => mb_strtoupper($medioPago),
                    'importe' => CuotasFormato::formatearImporte($importe),
                    'bonificacion' => CuotasFormato::formatearImporte($bonificacion),
                    'interes' => CuotasFormato::formatearImporte($interes),
                    'abonado' => CuotasFormato::formatearImporte($abonado),
                    '_importe' => $importe,
                    '_bonificacion' => $bonificacion,
                    '_interes' => $interes,
                    '_abonado' => $abonado,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CuotaPago>
     */
    private static function consultaPagos(int $idLegajo): Collection
    {
        return CuotaPago::query()
            ->with([
                'tipoPago:id,abrev,tipoPago',
                'cuotaGenerada.terlec:id,ano',
                'cuotaGenerada.cuota:id,nombre',
            ])
            ->whereHas('cuotaGenerada', fn ($q) => $q->where('idLegajos', $idLegajo))
            ->orderByDesc('fechhora')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{importe: string, bonificacion: string, interes: string, abonado: string}
     */
    private static function calcularTotales(array $filas): array
    {
        $importe = 0.0;
        $bonificacion = 0.0;
        $interes = 0.0;
        $abonado = 0.0;

        foreach ($filas as $fila) {
            $importe += (float) ($fila['_importe'] ?? 0);
            $bonificacion += (float) ($fila['_bonificacion'] ?? 0);
            $interes += (float) ($fila['_interes'] ?? 0);
            $abonado += (float) ($fila['_abonado'] ?? 0);
        }

        return [
            'importe' => CuotasFormato::formatearImporte($importe),
            'bonificacion' => CuotasFormato::formatearImporte($bonificacion),
            'interes' => CuotasFormato::formatearImporte($interes),
            'abonado' => CuotasFormato::formatearImporte($abonado),
        ];
    }
}
