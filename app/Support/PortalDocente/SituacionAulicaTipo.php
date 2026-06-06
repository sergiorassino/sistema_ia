<?php

namespace App\Support\PortalDocente;

use App\Models\SancionTipo;
use Illuminate\Support\Facades\Cache;

/**
 * Tipo único de sanción para el cuaderno de seguimiento áulico (tabla `sanciontipo`).
 */
final class SituacionAulicaTipo
{
    public const LABEL = 'Registro de Situación Áulica';

    public static function label(): string
    {
        return self::LABEL;
    }

    /**
     * @throws \RuntimeException si no existe el tipo en `sanciontipo`
     */
    public static function idTipo(): int
    {
        $id = Cache::remember('situacion_aulica:id_tipo_sancion', 300, function () {
            $row = SancionTipo::query()
                ->whereRaw('LOWER(TRIM(tipo)) = ?', [mb_strtolower(trim(self::LABEL))])
                ->first(['id']);

            return $row !== null ? (int) $row->id : 0;
        });

        if ($id < 1) {
            throw new \RuntimeException(
                'Falta el tipo «'.self::LABEL.'» en la tabla sanciontipo. Solicite a secretaría que lo cargue.'
            );
        }

        return $id;
    }
}
