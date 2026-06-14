<?php

namespace App\Support\Cooperadora;

use App\Models\CoopConfig;
use App\Models\Terlec;
use Illuminate\Support\Facades\Schema;

final class CooperadoraConfig
{
    public static function registro(): CoopConfig
    {
        if (! Schema::hasTable('coop_config')) {
            abort(503, 'Módulo Cooperadora no instalado.');
        }

        $row = CoopConfig::query()->first();

        if ($row === null) {
            $row = CoopConfig::query()->create([
                'nombre_institucion' => 'Cooperadora',
                'direccion' => '',
                'localidad' => '',
                'telefono' => '',
                'cuit' => '',
                'repace' => '',
                'descuento_hermano_pct' => 0,
                'recibo_proximo_num' => 1,
                'orden_pago_proximo_num' => 1,
            ]);
        }

        return $row;
    }

    public static function anioVigente(): int
    {
        $idTerlec = (int) schoolCtx()->idTerlec;
        $ano = Terlec::query()->whereKey($idTerlec)->value('ano');

        return (int) ($ano ?: now()->year);
    }

    public static function descuentoHermanosPct(): float
    {
        return (float) self::registro()->descuento_hermano_pct;
    }

    /**
     * @return array{nombre: string, direccion: string, localidad: string, telefono: string, cuit: string, repace: string, logo_file: ?string}
     */
    public static function datosPdfHeader(): array
    {
        $cfg = self::registro();
        $logoEscuela = schoolPdfHeaderData()['logo_file'] ?? null;

        return [
            'nombre' => trim((string) $cfg->nombre_institucion),
            'direccion' => trim((string) $cfg->direccion),
            'localidad' => trim((string) $cfg->localidad),
            'telefono' => trim((string) $cfg->telefono),
            'cuit' => trim((string) ($cfg->cuit ?? '')),
            'repace' => trim((string) ($cfg->repace ?? '')),
            'logo_file' => is_string($logoEscuela) && $logoEscuela !== '' ? $logoEscuela : null,
        ];
    }
}
