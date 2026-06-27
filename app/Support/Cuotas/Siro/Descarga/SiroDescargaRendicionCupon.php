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
     *     modalidadIdentificacion: string
     * }
     */
    public static function resolver(array $linea, int $idTerlec): array
    {
        $advertencias = [];
        $resolucion = SiroDescargaRendicionIdFactura::resolucionDesdeLinea($linea, $idTerlec);
        $modalidadIdentificacion = $resolucion['modalidadEtiqueta'];
        $candidatos = $resolucion['idFactura'] !== null ? [$resolucion['idFactura']] : [];

        foreach ($candidatos as $idFactura) {
            $resultado = self::resolverPorIdFactura($idFactura, $idTerlec);
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
            ];
        }

        return [
            'cupon' => null,
            'cuotaGenerada' => null,
            'advertencias' => [
                'No se pudo determinar el cupón del pago (revisar idComprobante 0449 o barcode 0448).',
            ],
            'modalidadIdentificacion' => $modalidadIdentificacion,
        ];
    }

    /**
     * @param  array{cupon: ?CuponAPagar, cuotaGenerada: ?CuotaGenerada, mensaje: string}  $resultado
     * @param  list<string>  $advertencias
     * @return array{cupon: ?CuponAPagar, cuotaGenerada: ?CuotaGenerada, advertencias: list<string>, modalidadIdentificacion: string}
     */
    private static function respuestaDesdeResultado(array $resultado, array $advertencias): array
    {
        if ($resultado['cupon'] === null) {
            $advertencias[] = $resultado['mensaje'];
        } elseif ($resultado['cuotaGenerada'] === null) {
            $advertencias[] = 'El cupón encontrado no pertenece al ciclo lectivo activo.';
        }

        return [
            'cupon' => $resultado['cupon'],
            'cuotaGenerada' => $resultado['cuotaGenerada'],
            'advertencias' => $advertencias,
            'modalidadIdentificacion' => '',
        ];
    }

    /**
     * @return array{cupon: ?CuponAPagar, cuotaGenerada: ?CuotaGenerada, mensaje: string}
     */
    private static function resolverPorIdFactura(string $idFactura, int $idTerlec): array
    {
        $cupon = CuponAPagar::query()->where('id_factura', $idFactura)->first();
        if ($cupon === null) {
            return [
                'cupon' => null,
                'cuotaGenerada' => null,
                'mensaje' => 'No se encontró cupón en cupones_a_pagar con id_factura '.$idFactura.'.',
            ];
        }

        return [
            'cupon' => $cupon,
            'cuotaGenerada' => self::cuotaGeneradaDelCupon($cupon, $idTerlec),
            'mensaje' => '',
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
