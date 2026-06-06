<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;

/**
 * Cancelación masiva de reservas (cuotasgeneradas tipo reserva sin pagos).
 */
final class CancelarTodasReservasService
{
    /**
     * @return array{conImporte: int, enCero: int, total: int}
     */
    public static function resumen(): array
    {
        $query = self::queryObjetivo();
        $conImporte = (clone $query)->where('importe', '>', 0)->count();
        $enCero = (clone $query)->where('importe', '=', 0)->count();

        return [
            'conImporte' => $conImporte,
            'enCero' => $enCero,
            'total' => $conImporte + $enCero,
        ];
    }

    /**
     * @return array{
     *     ano: int,
     *     canceladasConImporte: int,
     *     yaEnCero: int,
     *     filasActualizadas: int,
     *     totalSinPago: int
     * }
     */
    public static function cancelar(): array
    {
        $ano = (int) schoolCtx()->terlecAno();
        $resumenAntes = self::resumen();
        $filasActualizadas = self::queryObjetivo()->update([
            'importe' => 0,
            'faltapa' => 0,
        ]);

        return [
            'ano' => $ano,
            'canceladasConImporte' => $resumenAntes['conImporte'],
            'yaEnCero' => $resumenAntes['enCero'],
            'filasActualizadas' => $filasActualizadas,
            'totalSinPago' => $resumenAntes['total'],
        ];
    }

    /**
     * @param  array{
     *     ano: int,
     *     canceladasConImporte: int,
     *     yaEnCero: int,
     *     filasActualizadas: int,
     *     totalSinPago: int
     * }  $resultado
     */
    public static function tituloInforme(array $resultado): string
    {
        if ($resultado['filasActualizadas'] === 0) {
            return 'Sin cambios';
        }

        return $resultado['canceladasConImporte'] > 0
            ? 'Cancelación completada'
            : 'Actualización completada';
    }

    /**
     * @param  array{
     *     ano: int,
     *     canceladasConImporte: int,
     *     yaEnCero: int,
     *     filasActualizadas: int,
     *     totalSinPago: int
     * }  $resultado
     */
    public static function mensajeInforme(array $resultado): string
    {
        $ano = $resultado['ano'];

        if ($resultado['filasActualizadas'] === 0) {
            return "Ciclo lectivo {$ano}.\n\n"
                .'No se encontraron reservas sin pago para cancelar.';
        }

        $canceladas = number_format($resultado['canceladasConImporte'], 0, ',', '.');
        $enCero = number_format($resultado['yaEnCero'], 0, ',', '.');
        $actualizadas = number_format($resultado['filasActualizadas'], 0, ',', '.');

        $lineas = [
            "Ciclo lectivo {$ano}.",
            '',
            "Reservas canceladas (tenían importe): {$canceladas}.",
        ];

        if ($resultado['yaEnCero'] > 0) {
            $lineas[] = "Reservas que ya estaban en cero: {$enCero} (se actualizó faltapa a 0).";
        }

        $lineas[] = "Total de registros actualizados: {$actualizadas}.";
        $lineas[] = '';
        $lineas[] = 'Se aplicó importe = 0 y faltapa = 0 en todas las reservas sin pago del ciclo activo.';
        $lineas[] = 'Las reservas con pagos registrados no se modificaron.';

        return implode("\n", $lineas);
    }

    private static function queryObjetivo()
    {
        return CuotaGenerada::query()
            ->where('idTerlec', CuotasPlantillaCatalog::idTerlecActivo())
            ->where('idCuotastipo', GeneracionCuotaEstudianteService::TIPO_RESERVA)
            ->where('pagado', 0);
    }
}
