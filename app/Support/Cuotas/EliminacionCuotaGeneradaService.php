<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use Illuminate\Support\Facades\DB;

/**
 * Eliminación de un registro en cuotasgeneradas (solo sin pagos y adeudado por completo).
 */
final class EliminacionCuotaGeneradaService
{
    public static function puedeEliminar(CuotaGenerada $registro): bool
    {
        return self::motivoRechazo($registro) === null;
    }

    public static function motivoRechazo(?CuotaGenerada $registro): ?string
    {
        if ($registro === null) {
            return 'Cuota no encontrada.';
        }

        if (CuotaPago::query()->where('idCuotasGeneradas', (int) $registro->id)->exists()) {
            return 'No se puede eliminar: la cuota tiene pagos registrados.';
        }

        $pagado = round((float) ($registro->pagado ?? 0), 2);
        if ($pagado > 0) {
            return 'No se puede eliminar: la cuota tiene importe pagado.';
        }

        $faltapa = round((float) ($registro->faltapa ?? 0), 2);
        if ($faltapa <= 0) {
            return 'No se puede eliminar: la cuota no tiene saldo adeudado.';
        }

        $deudaCompleta = CuotasFormato::calcularFaltapa(
            (float) ($registro->importe ?? 0),
            0.0,
            (float) ($registro->bonificacion ?? 0),
            (float) ($registro->interes ?? 0),
        );

        if (abs($faltapa - $deudaCompleta) > 0.009) {
            return 'No se puede eliminar: la cuota no está adeudada por completo.';
        }

        return null;
    }

    public static function eliminar(int $idCuotaGenerada, int $idLegajo): bool
    {
        $registro = GestionAranceles::cuotaParaGestion($idCuotaGenerada, $idLegajo);
        if ($registro === null || self::motivoRechazo($registro) !== null) {
            return false;
        }

        return DB::transaction(function () use ($registro): bool {
            $locked = CuotaGenerada::query()->whereKey($registro->id)->lockForUpdate()->first();
            if ($locked === null) {
                return false;
            }

            if (CuotaPago::query()->where('idCuotasGeneradas', (int) $locked->id)->exists()) {
                return false;
            }

            if (self::motivoRechazo($locked) !== null) {
                return false;
            }

            return (bool) $locked->delete();
        });
    }
}
