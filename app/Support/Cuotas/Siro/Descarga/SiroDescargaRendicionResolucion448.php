<?php

namespace App\Support\Cuotas\Siro\Descarga;

/**
 * Resolución de id_factura para pagos 0448 (cupón impreso).
 *
 * 1. Formato nuevo: ID cliente extendido (cola Integrado extendida / UNIFICADO).
 * 2. Formato anterior: barcode 0448 (concepto + legajo + idCuotas + relleno).
 */
final class SiroDescargaRendicionResolucion448
{
    public const MODALIDAD_NUEVA = 'nueva';

    public const MODALIDAD_LEGACY = 'legacy';

    public const ETIQUETA_NUEVA = 'ID cliente extendido (formato nuevo)';

    public const ETIQUETA_LEGACY = 'Barcode 0448 (formato anterior)';

    /**
     * @param  array<string, mixed>  $linea
     * @return array{
     *     idFactura: ?string,
     *     modalidad: ?string,
     *     modalidadEtiqueta: string
     * }
     */
    public static function resolver(array $linea, int $idTerlec = 0): array
    {
        $identCola = SiroDescargaRendicionIdClienteExtendido::desdeColaLinea(
            (string) ($linea['cadenaPago'] ?? ''),
        );
        if ($identCola !== '') {
            $nuevo = SiroDescargaRendicionIdentUsuario448Nuevo::parse($identCola);
            if ($nuevo !== null) {
                return [
                    'idFactura' => SiroDescargaRendicionIdentUsuario448Nuevo::idFacturaDesdeParse($nuevo),
                    'modalidad' => self::MODALIDAD_NUEVA,
                    'modalidadEtiqueta' => self::ETIQUETA_NUEVA,
                ];
            }
        }

        return self::resolverLegacy($linea, $idTerlec);
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array{
     *     idFactura: ?string,
     *     modalidad: ?string,
     *     modalidadEtiqueta: string
     * }
     */
    private static function resolverLegacy(array $linea, int $idTerlec): array
    {
        $codigoBarras = (string) ($linea['codigoBarras'] ?? '');
        if (! SiroDescargaRendicionBarcodeComprobante448::esBarcodeComprobante448($codigoBarras)) {
            return self::vacio();
        }

        $parsed = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($codigoBarras);
        if ($parsed === null) {
            return self::vacio();
        }

        $ultUpload = SiroDescargaRendicionBarcode448UltUpload::desdeLineaLegacy($linea, $idTerlec, $parsed);
        if ($ultUpload === null) {
            return self::vacio();
        }

        $idFactura = SiroDescargaRendicionBarcodeComprobante448::idFacturaDesdeParseLegacy($parsed, $ultUpload);

        return [
            'idFactura' => $idFactura,
            'modalidad' => self::MODALIDAD_LEGACY,
            'modalidadEtiqueta' => self::ETIQUETA_LEGACY,
        ];
    }

    /**
     * @return array{idFactura: null, modalidad: null, modalidadEtiqueta: string}
     */
    private static function vacio(): array
    {
        return [
            'idFactura' => null,
            'modalidad' => null,
            'modalidadEtiqueta' => '',
        ];
    }
}
