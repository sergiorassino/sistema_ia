<?php

namespace App\Support\Cuotas\Siro;

use App\Support\Alumnos\ArancelesEscolares;
use Carbon\CarbonInterface;

/**
 * Columnas de vista previa del archivo SIRO en la grilla de subida.
 */
final class SiroSubidaGrillaColumnas
{
    private const VACIO = '—';

    /**
     * @param  ?array<string, mixed>  $detalle  Salida de {@see CuponAPagarSnapshot::armar} / armarParaCuponesVencidosSiro
     * @return array{
     *     siroVenc1: string,
     *     siroImporte1: string,
     *     siroVenc2: string,
     *     siroImporte2: string,
     *     siroVenc3: string,
     *     siroImporte3: string
     * }
     */
    public static function desdeDetalle(?array $detalle): array
    {
        if ($detalle === null) {
            return self::vacias();
        }

        /** @var CarbonInterface $venc1 */
        $venc1 = $detalle['venc1'];
        $venc2 = $detalle['venc2'] ?? null;
        $venc3 = $detalle['venc3'] ?? null;

        $fecha1 = SiroSubidaBaseDeudaArchivo::fechaSiro($venc1 instanceof CarbonInterface ? $venc1 : null);
        $fecha2 = SiroSubidaBaseDeudaArchivo::fechaSiro($venc2 instanceof CarbonInterface ? $venc2 : null);
        $fecha3 = SiroSubidaBaseDeudaArchivo::fechaSiro($venc3 instanceof CarbonInterface ? $venc3 : null);

        $importe1 = (float) ($detalle['importe1'] ?? 0);
        $importe2 = $fecha2 === str_repeat('0', 8) ? 0.0 : (float) ($detalle['importe2'] ?? 0);
        $importe3 = $fecha3 === str_repeat('0', 8) ? 0.0 : (float) ($detalle['importe3'] ?? 0);

        return [
            'siroVenc1' => self::formatearFechaSiro($fecha1),
            'siroImporte1' => self::formatearImporte($importe1),
            'siroVenc2' => self::formatearFechaSiro($fecha2),
            'siroImporte2' => $fecha2 === str_repeat('0', 8) ? self::VACIO : self::formatearImporte($importe2),
            'siroVenc3' => self::formatearFechaSiro($fecha3),
            'siroImporte3' => $fecha3 === str_repeat('0', 8) ? self::VACIO : self::formatearImporte($importe3),
        ];
    }

    /**
     * @return array{
     *     siroVenc1: string,
     *     siroImporte1: string,
     *     siroVenc2: string,
     *     siroImporte2: string,
     *     siroVenc3: string,
     *     siroImporte3: string
     * }
     */
    private static function vacias(): array
    {
        return [
            'siroVenc1' => self::VACIO,
            'siroImporte1' => self::VACIO,
            'siroVenc2' => self::VACIO,
            'siroImporte2' => self::VACIO,
            'siroVenc3' => self::VACIO,
            'siroImporte3' => self::VACIO,
        ];
    }

    private static function formatearFechaSiro(string $fechaSiro): string
    {
        if ($fechaSiro === str_repeat('0', 8) || strlen($fechaSiro) !== 8 || ! ctype_digit($fechaSiro)) {
            return self::VACIO;
        }

        return substr($fechaSiro, 6, 2).'/'.substr($fechaSiro, 4, 2).'/'.substr($fechaSiro, 0, 4);
    }

    private static function formatearImporte(float $importe): string
    {
        return ArancelesEscolares::formatearImporte($importe);
    }
}
