<?php

namespace App\Support\Cooperadora;

use App\Models\CoopEgreso;
use App\Models\CoopIngreso;

final class AnularMovimientoCooperadora
{
    public static function ingreso(int $idIngreso): bool
    {
        $ingreso = CoopIngreso::query()->find($idIngreso);

        if ($ingreso === null || $ingreso->anulado) {
            return false;
        }

        $ingreso->update(['anulado' => true]);

        return true;
    }

    public static function egreso(int $idEgreso): bool
    {
        $egreso = CoopEgreso::query()->find($idEgreso);

        if ($egreso === null || $egreso->anulado) {
            return false;
        }

        $egreso->update(['anulado' => true]);

        return true;
    }
}
