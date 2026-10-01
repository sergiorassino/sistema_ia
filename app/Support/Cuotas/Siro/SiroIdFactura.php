<?php

namespace App\Support\Cuotas\Siro;

/**
 * Identificador de comprobante SIRO (20 dígitos) — modelo 26.
 *
 * {@code legajo(8) + idCuotas(7) + ultUpload(2) + idCuotas(3)}
 */
final class SiroIdFactura
{
    public static function generar(int $idLegajos, int $idCuotas, int $ultUpload): string
    {
        $sufijoCuota = str_pad(substr((string) $idCuotas, -3), 3, '0', STR_PAD_LEFT);
        $diferenciador = str_pad((string) $ultUpload, 2, '0', STR_PAD_LEFT).$sufijoCuota;

        return str_pad((string) $idLegajos, 8, '0', STR_PAD_LEFT)
            .str_pad((string) $idCuotas, 7, '0', STR_PAD_LEFT)
            .$diferenciador;
    }

    /**
     * Próximo ultUpload (1–99) que no figure en $ocupados.
     *
     * Arranca en {@code $ultUploadActual + 1}. Si ese número ya existe en
     * cupones_a_pagar (reimpresión, subida SIRO previa o cuota regenerada),
     * avanza hasta el primero libre. La clave única es id_factura, que incluye
     * este número.
     *
     * @param  list<int>  $ocupados
     */
    public static function siguienteUltUploadLibre(int $ultUploadActual, array $ocupados): int
    {
        $usados = [];
        foreach ($ocupados as $n) {
            $usados[(int) $n] = true;
        }

        $candidato = max(0, $ultUploadActual) + 1;
        if ($candidato < 1) {
            $candidato = 1;
        }

        while ($candidato <= 99) {
            if (! isset($usados[$candidato])) {
                return $candidato;
            }
            $candidato++;
        }

        throw new \RuntimeException('No hay un número de cupón SIRO disponible para esta cuota (máximo 99).');
    }

    /**
     * @return array{idLegajos: int, idCuotas: int, ultUpload: int}|null
     */
    public static function decodificar(string $idFactura): ?array
    {
        $partes = self::partesCadena($idFactura);
        if ($partes === null) {
            return null;
        }

        $idLegajos = (int) substr($partes['digits'], 0, 8);
        $idCuotas = (int) substr($partes['digits'], 8, 7);
        $ultUpload = $partes['ultUpload'];

        if ($idLegajos <= 0 || $idCuotas <= 0 || $ultUpload <= 0) {
            return null;
        }

        return [
            'idLegajos' => $idLegajos,
            'idCuotas' => $idCuotas,
            'ultUpload' => $ultUpload,
        ];
    }

    /**
     * Partes de la cadena de 20 dígitos (sin validar que ultUpload > 0).
     *
     * @return array{digits: string, prefijoSinUpload: string, ultUpload: int, sufijoCuota: string}|null
     */
    public static function partesCadena(string $idFactura): ?array
    {
        $digits = preg_replace('/\D+/', '', $idFactura) ?? '';
        if (strlen($digits) < 20) {
            return null;
        }

        $digits = substr($digits, 0, 20);

        return [
            'digits' => $digits,
            'prefijoSinUpload' => substr($digits, 0, 15),
            'ultUpload' => (int) substr($digits, 15, 2),
            'sufijoCuota' => substr($digits, 17, 3),
        ];
    }
}
