<?php

namespace App\Support\Cooperadora;

use App\Models\CoopConfig;
use Illuminate\Support\Facades\DB;

final class NumeroDocumentoCooperadora
{
    public static function reservarRecibo(): int
    {
        return DB::transaction(function () {
            $cfg = CooperadoraConfig::registro();
            $num = (int) $cfg->recibo_proximo_num;
            CoopConfig::query()->whereKey($cfg->id)->update([
                'recibo_proximo_num' => $num + 1,
            ]);

            return $num;
        });
    }

    public static function reservarOrdenPago(): int
    {
        return DB::transaction(function () {
            $cfg = CooperadoraConfig::registro();
            $num = (int) $cfg->orden_pago_proximo_num;
            CoopConfig::query()->whereKey($cfg->id)->update([
                'orden_pago_proximo_num' => $num + 1,
            ]);

            return $num;
        });
    }

    public static function formatearRecibo(int $numero): string
    {
        return number_format($numero, 0, ',', '.');
    }

    public static function formatearOrden(int $numero): string
    {
        return number_format($numero, 0, ',', '.');
    }
}
