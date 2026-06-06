<?php

namespace App\Support\Alumnos;

/**
 * Código de barras legacy (empresa 0448) — equivalente a digitoVerificador1_448 / digitoVerificador2_448.
 */
final class ComprobantePagoCodigoBarras
{
    /** Pesos por posición (0-based): 1, luego 3-5-7-9 en ciclo. */
    private const PESOS_448 = [1, 3, 5, 7, 9];

    public static function armar(array $partes): string
    {
        $barraDv1 = $partes['empresaServicio']
            .$partes['identUsuario']
            .$partes['fecha1erVenc']
            .$partes['importe1erVenc']
            .$partes['dias2doVenc']
            .$partes['importe2doVenc']
            .$partes['numeroCuenta'];

        $dv1 = self::digitoVerificador1($barraDv1);
        $barraDv2 = $barraDv1.$dv1;
        $dv2 = self::digitoVerificador2($barraDv2);

        return $barraDv2.$dv2;
    }

    /**
     * Primer dígito verificador (57 posiciones, 0-56) — digitoVerificador1_448.
     */
    public static function digitoVerificador1(string $barra): string
    {
        $c = self::sumaPonderada448($barra, 57);

        $d = $c / 2;
        $f = $d / 10;
        $g = ($f - (int) $f) * 10;

        return substr((string) $g, 0, 1);
    }

    /**
     * Segundo dígito verificador (58 posiciones, 0-57) — digitoVerificador2_448.
     */
    public static function digitoVerificador2(string $barra): string
    {
        $c = self::sumaPonderada448($barra, 58);

        $d = $c / 2;
        $e = (int) $d;
        $f = $e / 10;
        $g = ($f - (int) $f) * 10;

        return substr((string) $g, 0, 1);
    }

    private static function sumaPonderada448(string $barra, int $maxPosiciones): int
    {
        $c = 0;
        $len = min(strlen($barra), $maxPosiciones);

        for ($i = 0; $i < $len; $i++) {
            $digito = (int) $barra[$i];
            $peso = self::PESOS_448[$i === 0 ? 0 : 1 + (($i - 1) % 4)];
            $c += $digito * $peso;
        }

        return $c;
    }

    public static function importeCodigo(float $importe): string
    {
        return str_pad(number_format($importe, 2, '', ''), 10, '0', STR_PAD_LEFT);
    }

    public static function fechaCodigo(?\Carbon\CarbonInterface $fecha): string
    {
        if ($fecha === null) {
            return '000000';
        }

        return $fecha->format('ymd');
    }
}
