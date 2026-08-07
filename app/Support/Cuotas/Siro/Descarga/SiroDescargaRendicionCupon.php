<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;

/**
 * Resuelve cupón / cuota generada a partir de una línea de rendición.
 */
final class SiroDescargaRendicionCupon
{
    /**
     * @param  array<string, mixed>  $linea
     * @return array{
     *     cupon: ?CuponAPagar,
     *     cuotaGenerada: ?CuotaGenerada,
     *     advertencias: list<string>,
     *     modalidadIdentificacion: string,
     *     matchTipo: string,
     *     detalleMatch: string
     * }
     */
    public static function resolver(array $linea, int $idTerlec): array
    {
        $advertencias = [];
        $resolucion = SiroDescargaRendicionIdFactura::resolucionDesdeLinea($linea, $idTerlec);
        $modalidadIdentificacion = $resolucion['modalidadEtiqueta'];
        $candidatos = $resolucion['idFactura'] !== null ? [$resolucion['idFactura']] : [];
        $importeArchivo = isset($linea['importePagadoCentavos'])
            ? SiroDescargaRendicionLinea::importeDesdeCentavos((int) $linea['importePagadoCentavos'])
            : null;

        foreach ($candidatos as $idFactura) {
            $resultado = self::resolverPorIdFactura($idFactura, $idTerlec, $importeArchivo);
            if ($resultado['cupon'] !== null) {
                $respuesta = self::respuestaDesdeResultado($resultado, $advertencias);
                $respuesta['modalidadIdentificacion'] = $modalidadIdentificacion;

                return $respuesta;
            }
        }

        if ($candidatos !== []) {
            $principal = $candidatos[0];
            $via = $modalidadIdentificacion !== '' ? ' ('.$modalidadIdentificacion.')' : '';

            return [
                'cupon' => null,
                'cuotaGenerada' => null,
                'advertencias' => [
                    'No se encontró cupón en cupones_a_pagar con id_factura '.$principal.$via.'.',
                ],
                'modalidadIdentificacion' => $modalidadIdentificacion,
                'matchTipo' => '',
                'detalleMatch' => '',
            ];
        }

        $familia = SiroDescargaRendicionIdFactura::familiaDesdeLinea($linea);
        if (SiroDescargaRendicionBarcodeFamilia::esCupón448($familia)) {
            return [
                'cupon' => null,
                'cuotaGenerada' => null,
                'advertencias' => [
                    'No se pudo armar id_factura desde el pago 0448 (revisar ID cliente extendido o barcode anterior).',
                ],
                'modalidadIdentificacion' => $modalidadIdentificacion,
                'matchTipo' => '',
                'detalleMatch' => '',
            ];
        }

        return [
            'cupon' => null,
            'cuotaGenerada' => null,
            'advertencias' => [
                'No se pudo determinar el cupón del pago (revisar idComprobante 0449 o barcode 0448).',
            ],
            'modalidadIdentificacion' => $modalidadIdentificacion,
            'matchTipo' => '',
            'detalleMatch' => '',
        ];
    }

    /**
     * @param  array{
     *     cupon: ?CuponAPagar,
     *     cuotaGenerada: ?CuotaGenerada,
     *     mensaje: string,
     *     matchTipo: string,
     *     advertenciasExtra: list<string>,
     *     detalleMatch: string
     * }  $resultado
     * @param  list<string>  $advertencias
     * @return array{
     *     cupon: ?CuponAPagar,
     *     cuotaGenerada: ?CuotaGenerada,
     *     advertencias: list<string>,
     *     modalidadIdentificacion: string,
     *     matchTipo: string,
     *     detalleMatch: string
     * }
     */
    private static function respuestaDesdeResultado(array $resultado, array $advertencias): array
    {
        if ($resultado['cupon'] === null) {
            $advertencias[] = $resultado['mensaje'];
        } elseif ($resultado['cuotaGenerada'] === null) {
            $advertencias[] = 'El cupón encontrado no pertenece al ciclo lectivo activo.';
        }

        foreach ($resultado['advertenciasExtra'] as $extra) {
            if ($extra !== '' && ! in_array($extra, $advertencias, true)) {
                $advertencias[] = $extra;
            }
        }

        return [
            'cupon' => $resultado['cupon'],
            'cuotaGenerada' => $resultado['cuotaGenerada'],
            'advertencias' => $advertencias,
            'modalidadIdentificacion' => '',
            'matchTipo' => $resultado['matchTipo'],
            'detalleMatch' => $resultado['detalleMatch'],
        ];
    }

    /**
     * @return array{
     *     cupon: ?CuponAPagar,
     *     cuotaGenerada: ?CuotaGenerada,
     *     mensaje: string,
     *     matchTipo: string,
     *     advertenciasExtra: list<string>,
     *     detalleMatch: string
     * }
     */
    private static function resolverPorIdFactura(string $idFactura, int $idTerlec, ?float $importeArchivo): array
    {
        $cupon = CuponAPagar::query()->where('id_factura', $idFactura)->first();
        if ($cupon !== null) {
            return [
                'cupon' => $cupon,
                'cuotaGenerada' => self::cuotaGeneradaDelCupon($cupon, $idTerlec),
                'mensaje' => '',
                'matchTipo' => 'exacto',
                'advertenciasExtra' => [],
                'detalleMatch' => '',
            ];
        }

        $provisorio = SiroDescargaRendicionMatchUploadCercano::buscar($idFactura, $importeArchivo);
        if ($provisorio['cupon'] !== null) {
            return [
                'cupon' => $provisorio['cupon'],
                'cuotaGenerada' => self::cuotaGeneradaDelCupon($provisorio['cupon'], $idTerlec),
                'mensaje' => '',
                'matchTipo' => 'upload_cercano',
                'advertenciasExtra' => $provisorio['advertencias'],
                'detalleMatch' => $provisorio['detalle'],
            ];
        }

        return [
            'cupon' => null,
            'cuotaGenerada' => null,
            'mensaje' => 'No se encontró cupón en cupones_a_pagar con id_factura '.$idFactura.'.',
            'matchTipo' => '',
            'advertenciasExtra' => [],
            'detalleMatch' => '',
        ];
    }

    private static function cuotaGeneradaDelCupon(CuponAPagar $cupon, int $idTerlec): ?CuotaGenerada
    {
        return CuotaGenerada::query()
            ->where('id', (int) $cupon->id_cuotas_generadas)
            ->where('idTerlec', $idTerlec)
            ->first();
    }
}
