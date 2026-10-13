<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use App\Models\Legajo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Saldo familiar con 2.º vencimiento ya cumplido.
 *
 * Misma base que la subida a SIRO («tiene deuda familiar»): suma de
 * {@see CuotaGenerada::$faltapa} con `venc2` anterior a hoy y sin aviso de pago.
 * La familia sin asignar (`legajos.idFamilias` 0 o 1) no se mezcla con otros
 * alumnos: en ese caso solo cuenta la deuda del propio estudiante.
 */
final class DeudaFamiliarVencida
{
    public const FAMILIA_SIN_ASIGNAR = 1;

    public static function tiene(int $idLegajo): bool
    {
        return self::saldo($idLegajo) > 0;
    }

    public static function saldo(int $idLegajo): float
    {
        if ($idLegajo < 1) {
            return 0.0;
        }

        $idFamilia = (int) Legajo::query()->whereKey($idLegajo)->value('idFamilias');
        $hoy = Carbon::today()->format('Y-m-d');

        $query = CuotaGenerada::query()
            ->whereDate('venc2', '<', $hoy)
            ->where(function (Builder $q): void {
                $q->where('avisoPago', '<>', 1)
                    ->orWhereNull('avisoPago');
            });

        if ($idFamilia > self::FAMILIA_SIN_ASIGNAR) {
            $query->whereIn('idLegajos', function ($sub) use ($idFamilia): void {
                $sub->select('id')->from('legajos')->where('idFamilias', $idFamilia);
            });
        } else {
            $query->where('idLegajos', $idLegajo);
        }

        return round((float) $query->sum('faltapa'), 2);
    }
}
