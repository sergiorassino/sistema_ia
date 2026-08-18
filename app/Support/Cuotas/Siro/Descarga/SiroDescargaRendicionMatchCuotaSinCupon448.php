<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use App\Support\Cuotas\Siro\SiroIdFactura;

/**
 * TEMPORAL — puesta en marcha SIRO (provisorio 2).
 *
 * Cupones 0448 impresos desde autogestión (Rapipago / Pago Fácil) que no están
 * en cupones_a_pagar, o cuyo importe no coincide con los vencimientos del cupón
 * encontrado: se identifica la {@see CuotaGenerada} por legajo+cuota del barcode
 * y se descarga el importe del archivo de rendición, desglosando interés o
 * bonificación contra el saldo de la cuota.
 *
 * Si el cupón de autogestión ya está en cupones_a_pagar y el importe del archivo
 * coincide con importe1/2/3venc, se cobra con ese snapshot (no hace falta este atajo).
 *
 * Cuando todos los cupones emitidos estén en cupones_a_pagar, poner
 * {@see self::HABILITADO} en false (o eliminar esta clase y el aviso del form).
 */
final class SiroDescargaRendicionMatchCuotaSinCupon448
{
    /**
     * Interruptor de la excepción provisorio 2. Desactivar al cerrar la puesta en marcha.
     */
    public const HABILITADO = true;

    public const MATCH_TIPO = 'sin_cupon_448';

    public static function mensajeAvisoFormulario(): string
    {
        return 'Puesta en marcha (provisorio 2): cupones 448 de Rapipago/Pago Fácil cuyo '
            .'id_factura no está en cupones_a_pagar, o cuyo importe no coincide con los '
            .'vencimientos del cupón encontrado, se descargan con el importe del archivo '
            .'de rendición y se desglosan interés/bonificación contra el saldo de la cuota. '
            .'Si el cupón de autogestión ya está en la tabla y el importe cierra, se cobra '
            .'con ese registro. Quitar esta excepción cuando todos los cupones emitidos '
            .'estén en cupones_a_pagar.';
    }

    public static function esMatchTipo(string $matchTipo): bool
    {
        return $matchTipo === self::MATCH_TIPO;
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    public static function aplicaALinea(array $linea): bool
    {
        if (! self::HABILITADO) {
            return false;
        }

        $familia = SiroDescargaRendicionIdFactura::familiaDesdeLinea($linea);

        return SiroDescargaRendicionBarcodeFamilia::esCupón448($familia);
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array{
     *     cuotaGenerada: ?CuotaGenerada,
     *     advertencias: list<string>,
     *     detalle: string
     * }
     */
    public static function buscar(array $linea, int $idTerlec): array
    {
        $vacio = [
            'cuotaGenerada' => null,
            'advertencias' => [],
            'detalle' => '',
        ];

        if (! self::aplicaALinea($linea) || $idTerlec <= 0) {
            return $vacio;
        }

        $ids = self::idsDesdeLinea($linea);
        if ($ids === null) {
            return $vacio;
        }

        $cuota = CuotaGenerada::query()
            ->where('idLegajos', $ids['idLegajos'])
            ->where('idCuotas', $ids['idCuotas'])
            ->where('idTerlec', $idTerlec)
            ->first();

        if ($cuota === null) {
            return $vacio;
        }

        $pagado = isset($linea['importePagadoCentavos'])
            ? SiroDescargaRendicionLinea::importeDesdeCentavos((int) $linea['importePagadoCentavos'])
            : 0.0;

        $aviso = 'Match provisorio 448 (puesta en marcha): no hay cupón en cupones_a_pagar; '
            .'se descarga el importe del archivo SIRO ($'.number_format($pagado, 2, ',', '.')
            .') sobre cuotasgeneradas.id '.(int) $cuota->id
            .' (legajo '.$ids['idLegajos'].', cuota '.$ids['idCuotas']
            .') y se desglosan interés/bonificación.';

        return [
            'cuotaGenerada' => $cuota,
            'advertencias' => [$aviso],
            'detalle' => 'Provisorio 448 sin cupón → cuota '.(int) $cuota->id.' · importe archivo',
        ];
    }

    /**
     * Capital para desglosar el importe cobrado por SIRO (saldo del cupón o de la cuota).
     */
    public static function capitalParaDesglose(?CuponAPagar $cupon, CuotaGenerada $cuotaGenerada): float
    {
        if ($cupon !== null) {
            $saldo = round((float) ($cupon->saldo_pagar ?? 0), 2);
            if ($saldo > 0) {
                return $saldo;
            }
        }

        $faltapa = round((float) ($cuotaGenerada->faltapa ?? 0), 2);
        if ($faltapa > 0) {
            return $faltapa;
        }

        return round((float) ($cuotaGenerada->importe ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array{idLegajos: int, idCuotas: int}|null
     */
    public static function idsDesdeLinea(array $linea): ?array
    {
        $cola = SiroDescargaRendicionIdClienteExtendido::desdeColaLinea(
            (string) ($linea['cadenaPago'] ?? ''),
        );
        $nuevo = $cola !== '' ? SiroDescargaRendicionIdentUsuario448Nuevo::parse($cola) : null;
        if ($nuevo !== null) {
            return [
                'idLegajos' => $nuevo['idLegajos'],
                'idCuotas' => $nuevo['idCuotas'],
            ];
        }

        $legacy = SiroDescargaRendicionBarcodeComprobante448::parseLegacy(
            (string) ($linea['codigoBarras'] ?? ''),
        );
        if ($legacy !== null) {
            return [
                'idLegajos' => $legacy['idLegajos'],
                'idCuotas' => $legacy['idCuotas'],
            ];
        }

        $idFactura = (string) ($linea['idComprobante'] ?? '');
        $dec = SiroIdFactura::decodificar($idFactura);
        if ($dec !== null) {
            return [
                'idLegajos' => $dec['idLegajos'],
                'idCuotas' => $dec['idCuotas'],
            ];
        }

        return null;
    }
}
