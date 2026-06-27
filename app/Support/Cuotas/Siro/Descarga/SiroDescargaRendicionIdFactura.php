<?php

namespace App\Support\Cuotas\Siro\Descarga;

/**
 * Arma el id_factura buscado en cupones_a_pagar para una línea de rendición.
 *
 * Discriminación según SIRO Integrado v5.2: prefijo 0448 (cupón) vs 0449/0444/0447 (electrónico).
 */
final class SiroDescargaRendicionIdFactura
{
    /**
     * @param  array<string, mixed>  $linea
     * @return list<string>
     */
    public static function candidatosDesdeLinea(array $linea, int $idTerlec = 0): array
    {
        $codigoBarras = (string) ($linea['codigoBarras'] ?? '');
        $familia = SiroDescargaRendicionBarcodeFamilia::desdeCodigoBarras($codigoBarras);

        if (SiroDescargaRendicionBarcodeFamilia::esCupón448($familia)) {
            $resolucion = SiroDescargaRendicionResolucion448::resolver($linea, $idTerlec);

            return $resolucion['idFactura'] !== null ? [$resolucion['idFactura']] : [];
        }

        if (SiroDescargaRendicionBarcodeFamilia::esElectronico($familia)) {
            return self::candidatosDesdeElectronico($linea, $codigoBarras);
        }

        return self::candidatosDesdeElectronico($linea, $codigoBarras);
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    public static function principalDesdeLinea(array $linea, int $idTerlec = 0): ?string
    {
        $candidatos = self::candidatosDesdeLinea($linea, $idTerlec);

        return $candidatos[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array{
     *     idFactura: ?string,
     *     modalidad: ?string,
     *     modalidadEtiqueta: string
     * }
     */
    public static function resolucionDesdeLinea(array $linea, int $idTerlec = 0): array
    {
        $familia = SiroDescargaRendicionBarcodeFamilia::desdeCodigoBarras(
            (string) ($linea['codigoBarras'] ?? ''),
        );

        if (SiroDescargaRendicionBarcodeFamilia::esCupón448($familia)) {
            return SiroDescargaRendicionResolucion448::resolver($linea, $idTerlec);
        }

        $idFactura = self::candidatosDesdeElectronico($linea, (string) ($linea['codigoBarras'] ?? ''))[0] ?? null;
        $etiqueta = match ($familia) {
            SiroDescargaRendicionBarcodeFamilia::ELECTRONICO_449,
            SiroDescargaRendicionBarcodeFamilia::ELECTRONICO_444,
            SiroDescargaRendicionBarcodeFamilia::ELECTRONICO_447 => 'Electrónico '.$familia.' (idComprobante)',
            default => $idFactura !== null ? 'Id. comprobante' : '',
        };

        return [
            'idFactura' => $idFactura,
            'modalidad' => $idFactura !== null ? 'electronico' : null,
            'modalidadEtiqueta' => $etiqueta,
        ];
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    public static function familiaDesdeLinea(array $linea): string
    {
        return SiroDescargaRendicionBarcodeFamilia::desdeCodigoBarras(
            (string) ($linea['codigoBarras'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return list<string>
     */
    private static function candidatosDesdeElectronico(array $linea, string $codigoBarras): array
    {
        $idComprobante = (string) ($linea['idComprobante'] ?? '');
        $idUsuario = (string) ($linea['idUsuario'] ?? '');

        $idFactura = SiroDescargaRendicionBarcodeElectronico::idFacturaDesdeLinea(
            $idComprobante,
            $codigoBarras,
            $idUsuario,
        );

        return $idFactura !== null ? [$idFactura] : [];
    }
}
