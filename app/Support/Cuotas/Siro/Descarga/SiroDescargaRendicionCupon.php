<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use App\Support\Cuotas\Siro\SiroIdFactura;

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
     *     advertencias: list<string>
     * }
     */
    public static function resolver(array $linea, int $idTerlec): array
    {
        $advertencias = [];
        $idComprobante = preg_replace('/\D+/', '', (string) ($linea['idComprobante'] ?? '')) ?? '';
        $cupon = null;
        $cuotaGenerada = null;

        if (strlen($idComprobante) >= 20 && ! self::esIdComprobanteVacio($idComprobante)) {
            $factura = str_pad(substr($idComprobante, 0, 20), 20, '0', STR_PAD_LEFT);
            $cupon = CuponAPagar::query()->where('id_factura', $factura)->first();
            if ($cupon !== null) {
                $cuotaGenerada = self::cuotaGeneradaDelCupon($cupon, $idTerlec);
            } else {
                $dec = SiroIdFactura::decodificar($factura);
                if ($dec !== null) {
                    $cuotaGenerada = self::cuotaPorLegajoCuota($dec['idLegajos'], $dec['idCuotas'], $idTerlec);
                    $cupon = self::cuponPorCuotaYUpload($cuotaGenerada, $dec['ultUpload']);
                }
            }
        }

        if ($cupon === null && $cuotaGenerada === null) {
            $cpe = self::extraerCpe((string) ($linea['codigoBarras'] ?? ''));
            if ($cpe !== null) {
                $cupon = CuponAPagar::query()->where('cpe', $cpe)->orderByDesc('id')->first();
                if ($cupon !== null) {
                    $cuotaGenerada = self::cuotaGeneradaDelCupon($cupon, $idTerlec);
                }
            }
        }

        if ($cuotaGenerada === null) {
            [$idLegajos, $idCuotas] = self::extraerLegajoCuotaDesdeLinea($linea);
            if ($idLegajos > 0 && $idCuotas > 0) {
                $cuotaGenerada = self::cuotaPorLegajoCuota($idLegajos, $idCuotas, $idTerlec);
                $cupon = self::cuponMasReciente($cuotaGenerada);
            }
        }

        if ($cuotaGenerada === null) {
            $advertencias[] = 'No se encontró la cuota del alumno (revisar cupón en cupones_a_pagar).';
        } elseif ($cupon === null) {
            $advertencias[] = 'Sin cupón en cupones_a_pagar; se usó la cuota generada vigente.';
        }

        return [
            'cupon' => $cupon,
            'cuotaGenerada' => $cuotaGenerada,
            'advertencias' => $advertencias,
        ];
    }

    private static function esIdComprobanteVacio(string $digits): bool
    {
        return preg_match('/^0+$/', $digits) === 1;
    }

    private static function cuotaGeneradaDelCupon(CuponAPagar $cupon, int $idTerlec): ?CuotaGenerada
    {
        return CuotaGenerada::query()
            ->where('id', (int) $cupon->id_cuotas_generadas)
            ->where('idTerlec', $idTerlec)
            ->first();
    }

    private static function cuotaPorLegajoCuota(int $idLegajos, int $idCuotas, int $idTerlec): ?CuotaGenerada
    {
        return CuotaGenerada::query()
            ->where('idLegajos', $idLegajos)
            ->where('idCuotas', $idCuotas)
            ->where('idTerlec', $idTerlec)
            ->orderByDesc('id')
            ->first();
    }

    private static function cuponPorCuotaYUpload(?CuotaGenerada $cuotaGenerada, int $ultUpload): ?CuponAPagar
    {
        if ($cuotaGenerada === null) {
            return null;
        }

        return CuponAPagar::query()
            ->where('id_cuotas_generadas', (int) $cuotaGenerada->id)
            ->where('ult_upload', $ultUpload)
            ->orderByDesc('id')
            ->first()
            ?? self::cuponMasReciente($cuotaGenerada);
    }

    private static function cuponMasReciente(?CuotaGenerada $cuotaGenerada): ?CuponAPagar
    {
        if ($cuotaGenerada === null) {
            return null;
        }

        return CuponAPagar::query()
            ->where('id_cuotas_generadas', (int) $cuotaGenerada->id)
            ->orderByDesc('ult_upload')
            ->orderByDesc('id')
            ->first();
    }

    private static function extraerCpe(string $codigoBarras): ?string
    {
        $digits = preg_replace('/\D+/', '', $codigoBarras) ?? '';
        if (strlen($digits) >= 19) {
            return substr($digits, 0, 19);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array{0: int, 1: int}
     */
    private static function extraerLegajoCuotaDesdeLinea(array $linea): array
    {
        $barcode = (string) ($linea['codigoBarras'] ?? '');
        $idUsuario = (string) ($linea['idUsuario'] ?? '');
        $idCuotas = (int) ltrim($idUsuario, '0');
        $idLegajos = 0;

        if ($idCuotas > 0) {
            $sufijo = str_pad((string) $idCuotas, 3, '0', STR_PAD_LEFT);
            if (preg_match('/(\d{1,7})'.preg_quote($sufijo, '/').'/', $barcode, $m) === 1) {
                $idLegajos = (int) $m[1];
            }
        }

        if ($idLegajos <= 0 && preg_match('/(\d{1,7})(\d{3})/', $barcode, $m) === 1) {
            $idLegajos = (int) $m[1];
            $idCuotas = (int) $m[2];
        }

        return [$idLegajos, $idCuotas];
    }
}
