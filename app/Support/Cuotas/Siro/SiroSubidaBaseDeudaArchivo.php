<?php

namespace App\Support\Cuotas\Siro;

use App\Models\Ento;
use Carbon\CarbonInterface;

/**
 * Generación del archivo de subida de base de deuda SIRO (formato Full, 280 caracteres).
 *
 * Especificación: SIRO Developers — Subida Base Deuda v5.4.
 */
final class SiroSubidaBaseDeudaArchivo
{
    private const ID_NIVEL_ADMINISTRACION = 5;

    /** Longitud fija por registro exigida por SIRO (cabecera, detalle y pie). */
    public const LARGO_LINEA = 280;

    /**
     * @param  list<array<string, mixed>>  $detalles  Salida de {@see SiroSubidaBaseDeudaRegistro::armarDetalleArchivo}
     * @return array{contenido: string, nombre: string, cantidad: int, totalImporte1: float}
     */
    public static function generar(array $detalles, ?CarbonInterface $fechaArchivo = null): array
    {
        $fechaArchivo ??= now()->startOfDay();
        $fechaTxt = $fechaArchivo->format('Ymd');

        $lineas = [self::lineaCabecera($fechaTxt)];

        $totalImporte1 = 0.0;
        foreach ($detalles as $detalle) {
            $lineas[] = self::lineaDetalle($detalle, $fechaTxt);
            $totalImporte1 += (float) ($detalle['importe1'] ?? 0);
        }

        $lineas[] = self::lineaPie($fechaTxt, count($detalles), $totalImporte1);

        return [
            'contenido' => self::contenidoListoParaSiro($lineas),
            'nombre' => self::nombreArchivo($fechaArchivo),
            'cantidad' => count($detalles),
            'totalImporte1' => round($totalImporte1, 2),
        ];
    }

    /**
     * Arma el cuerpo del archivo en ASCII puro: CRLF, sin BOM, 280 bytes por línea.
     *
     * @param  list<string>  $lineas
     */
    public static function contenidoListoParaSiro(array $lineas): string
    {
        $normalizadas = [];
        foreach ($lineas as $indice => $linea) {
            if (preg_match('/[^\x20-\x7E]/', $linea) === 1) {
                throw new \RuntimeException(
                    'Línea SIRO '.($indice + 1).' contiene caracteres no ASCII.'
                );
            }
            if (strlen($linea) !== self::LARGO_LINEA) {
                throw new \RuntimeException(
                    'Línea SIRO '.($indice + 1).' inválida: se esperaban '.self::LARGO_LINEA.' bytes, hay '.strlen($linea).'.'
                );
            }
            $normalizadas[] = $linea;
        }

        return implode("\r\n", $normalizadas);
    }

    public static function nombreArchivo(?CarbonInterface $fecha = null): string
    {
        $fecha ??= now()->startOfDay();
        $cuit = self::cuitAdministrador();

        return $cuit.'.'.$fecha->format('Ymd');
    }

    public static function recortarAlfanumerico(string $texto, int $longitud): string
    {
        $texto = self::sinAcentos(mb_strtoupper(trim($texto)));
        $texto = preg_replace('/[^A-Z0-9 ]/', '', $texto) ?? '';

        return self::alfanumerico($texto, $longitud);
    }

    private static function lineaCabecera(string $fechaTxt): string
    {
        $linea = '';
        $linea .= self::numerico(0, 1);
        $linea .= self::numerico(400, 3);
        $linea .= self::numerico(0, 4);
        $linea .= self::numerico($fechaTxt, 8);
        $linea .= '1';
        $linea .= self::numerico(0, 263);

        return self::asegurarLongitud($linea, self::LARGO_LINEA);
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private static function lineaDetalle(array $detalle, string $fechaTxt): string
    {
        $cpe = (string) ($detalle['cpe'] ?? '');
        $idFactura = (string) ($detalle['idFactura'] ?? '');

        /** @var CarbonInterface $venc1 */
        $venc1 = $detalle['venc1'];
        /** @var CarbonInterface $venc2 */
        $venc2 = $detalle['venc2'];
        /** @var CarbonInterface $venc3 */
        $venc3 = $detalle['venc3'];

        $linea = '';
        $linea .= self::numerico(5, 1);
        $linea .= self::numerico($cpe, 19);
        $linea .= self::alfanumerico($idFactura, 20);
        $linea .= self::numerico(0, 1);
        $linea .= self::numerico($venc1->format('Ymd'), 8);
        $linea .= self::importe((float) ($detalle['importe1'] ?? 0), 11);
        $linea .= self::numerico($venc2->format('Ymd'), 8);
        $linea .= self::importe((float) ($detalle['importe2'] ?? 0), 11);
        $linea .= self::numerico($venc3->format('Ymd'), 8);
        $linea .= self::importe((float) ($detalle['importe3'] ?? 0), 11);
        $linea .= self::numerico(0, 19);
        $linea .= self::alfanumerico($cpe, 19);
        $linea .= self::alfanumerico((string) ($detalle['mensajeTicket'] ?? ''), 40);
        $linea .= self::alfanumerico((string) ($detalle['mensajePantalla'] ?? ''), 15);
        $linea .= self::alfanumerico('', 60);
        $linea .= self::numerico(0, 29);

        return self::asegurarLongitud($linea, self::LARGO_LINEA);
    }

    private static function lineaPie(string $fechaTxt, int $cantidad, float $totalImporte1): string
    {
        $linea = '';
        $linea .= self::numerico(9, 1);
        $linea .= self::numerico(400, 3);
        $linea .= self::numerico(0, 4);
        $linea .= self::numerico($fechaTxt, 8);
        $linea .= self::numerico($cantidad, 7);
        $linea .= self::numerico(0, 7);
        $linea .= self::importe($totalImporte1, 16);
        $linea .= self::numerico(0, 234);

        return self::asegurarLongitud($linea, self::LARGO_LINEA);
    }

    private static function cuitAdministrador(): string
    {
        $ento = Ento::query()->where('idNivel', self::ID_NIVEL_ADMINISTRACION)->first();
        $digits = preg_replace('/\D+/', '', (string) ($ento?->cuit ?? '')) ?? '';

        return $digits !== '' ? $digits : '00000000000';
    }

    private static function numerico(int|string $valor, int $longitud): string
    {
        $digits = preg_replace('/\D+/', '', (string) $valor) ?? '';

        return str_pad(substr($digits, -$longitud), $longitud, '0', STR_PAD_LEFT);
    }

    private static function importe(float $importe, int $longitud): string
    {
        $centavos = (int) round(abs($importe) * 100);

        return str_pad((string) $centavos, $longitud, '0', STR_PAD_LEFT);
    }

    private static function alfanumerico(string $texto, int $longitud): string
    {
        $texto = self::sinAcentos(mb_strtoupper($texto));
        $texto = preg_replace('/[^A-Z0-9 ]/', '', $texto) ?? '';
        while (strlen($texto) > $longitud) {
            $texto = substr($texto, 0, -1);
        }

        return str_pad($texto, $longitud, ' ', STR_PAD_RIGHT);
    }

    private static function sinAcentos(string $texto): string
    {
        $reemplazos = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
            'Ñ' => 'N',
        ];

        return strtr($texto, $reemplazos);
    }

    private static function asegurarLongitud(string $linea, int $longitud): string
    {
        if (strlen($linea) > $longitud) {
            return substr($linea, 0, $longitud);
        }

        if (strlen($linea) < $longitud) {
            return str_pad($linea, $longitud, ' ', STR_PAD_RIGHT);
        }

        return $linea;
    }
}
