<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuponAPagar;
use App\Support\Cuotas\Siro\SiroIdFactura;
use Illuminate\Support\Collection;

/**
 * TEMPORAL — puesta en marcha SIRO.
 *
 * Si no hay coincidencia exacta de id_factura en cupones_a_pagar, busca la misma
 * cadena salvo el nro. de upload (posiciones 16–17) y elige el upload más cercano
 * al del archivo de rendición.
 *
 * Cuando la puesta en marcha termine y los contadores de upload estén alineados,
 * poner {@see self::HABILITADO} en false (o eliminar esta clase y el aviso del form).
 */
final class SiroDescargaRendicionMatchUploadCercano
{
    /**
     * Interruptor de la excepción provisorio. Desactivar al cerrar la puesta en marcha.
     */
    public const HABILITADO = true;

    private const TOLERANCIA_IMPORTE = 0.02;

    public static function mensajeAvisoFormulario(): string
    {
        return 'Puesta en marcha (provisorio): si no hay coincidencia exacta de id_factura, '
            .'se busca la misma cadena salvo el nro. de upload y se elige el upload más cercano '
            .'al del archivo SIRO. Quitar esta excepción cuando los contadores de upload estén alineados.';
    }

    /**
     * @return array{
     *     cupon: ?CuponAPagar,
     *     advertencias: list<string>,
     *     detalle: string
     * }
     */
    public static function buscar(string $idFacturaBuscado, ?float $importeArchivo): array
    {
        if (! self::HABILITADO) {
            return [
                'cupon' => null,
                'advertencias' => [],
                'detalle' => '',
            ];
        }

        $partes = SiroIdFactura::partesCadena($idFacturaBuscado);
        if ($partes === null) {
            return [
                'cupon' => null,
                'advertencias' => [],
                'detalle' => '',
            ];
        }

        $candidatos = self::candidatosMismaCadenaSalvoUpload(
            $partes['prefijoSinUpload'],
            $partes['sufijoCuota'],
        );

        if ($candidatos->isEmpty()) {
            return [
                'cupon' => null,
                'advertencias' => [],
                'detalle' => '',
            ];
        }

        $elegido = self::elegirMasCercano(
            $candidatos,
            $partes['ultUpload'],
            $importeArchivo,
        );

        if ($elegido === null) {
            return [
                'cupon' => null,
                'advertencias' => [],
                'detalle' => '',
            ];
        }

        /** @var CuponAPagar $cupon */
        $cupon = $elegido['cupon'];
        $uploadCupon = $elegido['ultUpload'];
        $idEncontrado = (string) $cupon->id_factura;
        $importesOk = $importeArchivo !== null
            && self::importeCoincideConCupon($importeArchivo, $cupon);
        $importeCupon = self::importeReferenciaCupon($cupon, $importeArchivo);

        $avisoEleccion = 'Match provisorio (puesta en marcha): se eligió cupones_a_pagar.id_factura '
            .$idEncontrado.' (upload '.$uploadCupon.', buscado '.$partes['ultUpload']
            .') en lugar de '.$idFacturaBuscado.'.';

        $advertencias = [$avisoEleccion];

        if ($importeArchivo === null) {
            $advertencias[] = 'No se pudo verificar el importe del archivo de descarga contra el cupón.';
        } elseif (! $importesOk) {
            $advertencias[] = 'Importes NO coinciden: archivo $'
                .number_format($importeArchivo, 2, ',', '.')
                .' / cupón $'
                .number_format($importeCupon, 2, ',', '.')
                .' (revisar cupones_a_pagar.id_factura '.$idEncontrado
                .'; vencimientos importe1venc/2venc/3venc o saldo_pagar).';
        }

        $detalle = 'Provisorio upload cercano → '.$idEncontrado
            .' · '.($importesOk ? 'importes OK' : 'importes distintos');

        return [
            'cupon' => $cupon,
            'advertencias' => $advertencias,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  Collection<int, CuponAPagar>  $candidatos
     * @return array{cupon: CuponAPagar, ultUpload: int}|null
     */
    public static function elegirMasCercano(Collection $candidatos, int $uploadBuscado, ?float $importeArchivo): ?array
    {
        if ($candidatos->isEmpty()) {
            return null;
        }

        $filas = [];
        foreach ($candidatos as $cupon) {
            $partes = SiroIdFactura::partesCadena((string) $cupon->id_factura);
            if ($partes === null) {
                continue;
            }

            $ultUpload = $partes['ultUpload'];
            if ($ultUpload <= 0 && (int) ($cupon->ult_upload ?? 0) > 0) {
                $ultUpload = (int) $cupon->ult_upload;
            }

            $filas[] = [
                'cupon' => $cupon,
                'ultUpload' => $ultUpload,
                'distancia' => abs($ultUpload - $uploadBuscado),
                'importeOk' => $importeArchivo !== null
                    && self::importeCoincideConCupon($importeArchivo, $cupon),
            ];
        }

        if ($filas === []) {
            return null;
        }

        usort($filas, static function (array $a, array $b): int {
            if ($a['distancia'] !== $b['distancia']) {
                return $a['distancia'] <=> $b['distancia'];
            }
            if ($a['importeOk'] !== $b['importeOk']) {
                return $a['importeOk'] ? -1 : 1;
            }
            if ($a['ultUpload'] !== $b['ultUpload']) {
                return $b['ultUpload'] <=> $a['ultUpload'];
            }

            return ((int) $b['cupon']->id) <=> ((int) $a['cupon']->id);
        });

        return [
            'cupon' => $filas[0]['cupon'],
            'ultUpload' => $filas[0]['ultUpload'],
        ];
    }

    public static function importeCoincideConCupon(float $importeArchivo, CuponAPagar $cupon): bool
    {
        $importeArchivo = round($importeArchivo, 2);
        foreach (self::importesCupon($cupon) as $importe) {
            if ($importe > 0 && abs($importe - $importeArchivo) <= self::TOLERANCIA_IMPORTE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Importe de referencia para el aviso.
     * Si hay importe de archivo, prioriza el vencimiento/saldo que coincida
     * (p. ej. importe1venc con bonificación). Si no, prioriza vencimientos sobre saldo_pagar
     * (saldo suele ser capital sin bonificar).
     */
    public static function importeReferenciaCupon(CuponAPagar $cupon, ?float $importeArchivo = null): float
    {
        if ($importeArchivo !== null) {
            $importeArchivo = round($importeArchivo, 2);
            foreach (self::importesCupon($cupon) as $importe) {
                if ($importe > 0 && abs($importe - $importeArchivo) <= self::TOLERANCIA_IMPORTE) {
                    return $importe;
                }
            }
        }

        foreach (self::importesCupon($cupon) as $importe) {
            if ($importe > 0) {
                return $importe;
            }
        }

        return 0.0;
    }

    /**
     * Importes válidos del cupón para cruzar con el archivo SIRO.
     * Primero vencimientos (con bonificación si aplica), luego saldo_pagar (capital / faltapa).
     *
     * @return list<float>
     */
    private static function importesCupon(CuponAPagar $cupon): array
    {
        return [
            round((float) ($cupon->importe1venc ?? 0), 2),
            round((float) ($cupon->importe2venc ?? 0), 2),
            round((float) ($cupon->importe3venc ?? 0), 2),
            round((float) ($cupon->saldo_pagar ?? 0), 2),
        ];
    }

    /**
     * @return Collection<int, CuponAPagar>
     */
    private static function candidatosMismaCadenaSalvoUpload(string $prefijoSinUpload, string $sufijoCuota): Collection
    {
        return CuponAPagar::query()
            ->whereRaw('LEFT(id_factura, 15) = ?', [$prefijoSinUpload])
            ->whereRaw('SUBSTRING(id_factura, 18, 3) = ?', [$sufijoCuota])
            ->whereRaw('CHAR_LENGTH(id_factura) >= 20')
            ->get();
    }
}
