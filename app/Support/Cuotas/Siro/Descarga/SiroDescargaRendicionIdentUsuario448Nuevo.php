<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Support\Cuotas\Siro\SiroIdFactura;

/**
 * Identificador 0448 — modalidad nueva (cupones emitidos desde la convención vigente).
 *
 * 15 dígitos: idCuotas(4) + idLegajos(6) + ultUpload(2) + idCuotas(3).
 */
final class SiroDescargaRendicionIdentUsuario448Nuevo
{
    public const LARGO = 15;

    public static function armar(int $idCuotas, int $idLegajos, int $ultUpload): string
    {
        $ultUpload = max(1, min(99, $ultUpload));
        $sufijo3 = substr(str_pad((string) $idCuotas, 3, '0', STR_PAD_LEFT), -3);

        return str_pad((string) $idCuotas, 4, '0', STR_PAD_LEFT)
            .str_pad((string) $idLegajos, 6, '0', STR_PAD_LEFT)
            .str_pad((string) $ultUpload, 2, '0', STR_PAD_LEFT)
            .$sufijo3;
    }

    /**
     * @return array{
     *     identUsuario: string,
     *     idCuotas: int,
     *     idLegajos: int,
     *     ultUpload: int
     * }|null
     */
    public static function parse(string $identUsuario): ?array
    {
        $digits = preg_replace('/\D+/', '', $identUsuario) ?? '';
        if ($digits === '') {
            return null;
        }

        $identUsuario = str_pad(substr($digits, 0, self::LARGO), self::LARGO, '0', STR_PAD_LEFT);

        $idCuotas = (int) substr($identUsuario, 0, 4);
        $idLegajos = (int) substr($identUsuario, 4, 6);
        $ultUpload = (int) substr($identUsuario, 10, 2);
        $sufijo3 = substr($identUsuario, 12, 3);

        if ($idCuotas <= 0 || $idLegajos <= 0 || $ultUpload <= 0) {
            return null;
        }

        $sufijoEsperado = substr(str_pad((string) $idCuotas, 3, '0', STR_PAD_LEFT), -3);
        if ($sufijo3 !== $sufijoEsperado) {
            return null;
        }

        return [
            'identUsuario' => $identUsuario,
            'idCuotas' => $idCuotas,
            'idLegajos' => $idLegajos,
            'ultUpload' => $ultUpload,
        ];
    }

    /**
     * @param  array{idCuotas: int, idLegajos: int, ultUpload: int}  $parsed
     */
    public static function idFacturaDesdeParse(array $parsed): string
    {
        return SiroIdFactura::generar($parsed['idLegajos'], $parsed['idCuotas'], $parsed['ultUpload']);
    }
}
