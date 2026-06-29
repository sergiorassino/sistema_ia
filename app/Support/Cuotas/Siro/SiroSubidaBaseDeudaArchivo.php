<?php

namespace App\Support\Cuotas\Siro;

use Carbon\CarbonInterface;

/**
 * Generación del archivo de subida de base de deuda SIRO (280 caracteres por registro).
 *
 * Formato alineado al legacy Scriptcase / portal SIRO (cabecera + detalle + pie).
 */
final class SiroSubidaBaseDeudaArchivo
{
    /** Longitud fija por registro exigida por SIRO (cabecera, detalle y pie). */
    public const LARGO_LINEA = 280;

    /** Relleno numérico de cabecera tras la fecha (200 + 64). */
    private const CABECERA_FILLER_PRIMERO = 200;

    private const CABECERA_FILLER_SEGUNDO = 64;

    /** Total 1.er vencimiento en pie: 11 dígitos (centavos). */
    private const PIE_TOTAL_IMPORTE = 11;

    /** Relleno numérico final del pie. */
    private const PIE_FILLER = 239;

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

        $contenido = self::contenidoListoParaSiro($lineas);
        self::validarContenidoParaSiro($contenido);

        return [
            'contenido' => $contenido,
            'nombre' => self::nombreArchivo($fechaArchivo, $detalles),
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
            $linea = ltrim($linea, "\xEF\xBB\xBF");
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

        // Legacy Scriptcase: PHP_EOL al final de cada línea, inclusive la última.
        $contenido = implode("\r\n", $normalizadas)."\r\n";
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }

        return $contenido;
    }

    /**
     * Normaliza el cuerpo antes de enviarlo al navegador (sin BOM ni bytes previos).
     */
    public static function bytesParaDescarga(string $contenido): string
    {
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }

        self::validarContenidoParaSiro($contenido);

        return $contenido;
    }

    /**
     * Verifica estructura mínima exigida por el portal SIRO antes de descargar.
     */
    public static function validarContenidoParaSiro(string $contenido): void
    {
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            throw new \RuntimeException('El archivo SIRO no puede incluir BOM UTF-8.');
        }

        if ($contenido === '' || ($contenido[0] ?? '') !== '0') {
            throw new \RuntimeException('El archivo SIRO debe comenzar con el registro cabecera (tipo 0).');
        }

        $lineas = explode("\r\n", rtrim($contenido, "\r\n"));
        if ($lineas === [] || $lineas[0] === '') {
            throw new \RuntimeException('El archivo SIRO está vacío.');
        }

        $cabecera = $lineas[0];
        if (strlen($cabecera) !== self::LARGO_LINEA) {
            throw new \RuntimeException(
                'Cabecera SIRO inválida: se esperaban '.self::LARGO_LINEA.' bytes, hay '.strlen($cabecera).'.'
            );
        }

        if (! str_starts_with($cabecera, '04000000')) {
            throw new \RuntimeException('Cabecera SIRO inválida: debe comenzar con 04000000.');
        }

        if (($cabecera[16] ?? '') !== '0') {
            throw new \RuntimeException('Cabecera SIRO inválida: formato legacy (posición 17 en cero).');
        }

        $fechaCabecera = substr($cabecera, 8, 8);
        if (! self::esFechaSiroValida($fechaCabecera)) {
            throw new \RuntimeException('Cabecera SIRO inválida: fecha de archivo incorrecta.');
        }

        $ultima = $lineas[array_key_last($lineas)] ?? '';
        if ($ultima === '' || ($ultima[0] ?? '') !== '9') {
            throw new \RuntimeException('El archivo SIRO debe terminar con un registro pie (tipo 9).');
        }

        foreach ($lineas as $indice => $linea) {
            if (strlen($linea) !== self::LARGO_LINEA) {
                throw new \RuntimeException(
                    'Línea SIRO '.($indice + 1).' inválida: se esperaban '.self::LARGO_LINEA.' bytes, hay '.strlen($linea).'.'
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $detalles
     */
    public static function nombreArchivo(?CarbonInterface $fecha = null, array $detalles = []): string
    {
        $fecha ??= now()->startOfDay();
        $cuit = self::cuitDesdeDetalles($detalles);

        if (strlen($cuit) < 11) {
            throw new \RuntimeException('CUIT no configurado para el nivel de los cupones incluidos.');
        }

        return $cuit.'.'.$fecha->format('Ymd');
    }

    public static function recortarAlfanumerico(string $texto, int $longitud): string
    {
        $texto = self::sinAcentos(mb_strtoupper(trim($texto)));
        $texto = preg_replace('/[^A-Z0-9 ]/', '', $texto) ?? '';

        return self::alfanumerico($texto, $longitud);
    }

    /**
     * Fecha de vencimiento SIRO (AAAAMMDD). Sin vencimiento informado: 00000000.
     */
    public static function fechaSiro(?CarbonInterface $fecha, bool $requerida = false): string
    {
        if ($fecha === null) {
            if ($requerida) {
                throw new \RuntimeException('Falta una fecha de vencimiento obligatoria para el archivo SIRO.');
            }

            return str_repeat('0', 8);
        }

        $ymd = $fecha->toDateString();
        if ($ymd === '' || str_starts_with($ymd, '0000')) {
            if ($requerida) {
                throw new \RuntimeException('Fecha de vencimiento inválida para el archivo SIRO.');
            }

            return str_repeat('0', 8);
        }

        $partes = explode('-', $ymd);
        if (count($partes) !== 3 || ! checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0])) {
            if ($requerida) {
                throw new \RuntimeException('Fecha de vencimiento inválida para el archivo SIRO: '.$ymd);
            }

            return str_repeat('0', 8);
        }

        return sprintf('%04d%02d%02d', (int) $partes[0], (int) $partes[1], (int) $partes[2]);
    }

    private static function esFechaSiroValida(string $ymd): bool
    {
        if (strlen($ymd) !== 8 || ! ctype_digit($ymd)) {
            return false;
        }

        $y = (int) substr($ymd, 0, 4);
        $m = (int) substr($ymd, 4, 2);
        $d = (int) substr($ymd, 6, 2);

        return checkdate($m, $d, $y);
    }

    private static function lineaCabecera(string $fechaTxt): string
    {
        $linea = '';
        $linea .= self::numerico(0, 1);
        $linea .= self::numerico(400, 3);
        $linea .= self::numerico(0, 4);
        $linea .= self::numerico($fechaTxt, 8);
        $linea .= self::numerico(0, self::CABECERA_FILLER_PRIMERO);
        $linea .= self::numerico(0, self::CABECERA_FILLER_SEGUNDO);

        return self::asegurarLongitudNumerica($linea, self::LARGO_LINEA);
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
        $venc2 = $detalle['venc2'] ?? null;
        $venc3 = $detalle['venc3'] ?? null;

        $fecha1 = self::fechaSiro($venc1, true);
        $fecha2 = self::fechaSiro($venc2 instanceof CarbonInterface ? $venc2 : null);
        $fecha3 = self::fechaSiro($venc3 instanceof CarbonInterface ? $venc3 : null);

        $importe1 = (float) ($detalle['importe1'] ?? 0);
        $importe2 = $fecha2 === str_repeat('0', 8) ? 0.0 : (float) ($detalle['importe2'] ?? 0);
        $importe3 = $fecha3 === str_repeat('0', 8) ? 0.0 : (float) ($detalle['importe3'] ?? 0);

        $mensajeTicket1 = (string) ($detalle['mensajeTicket1'] ?? '');
        $mensajeTicket2 = (string) ($detalle['mensajeTicket2'] ?? '');
        $mensajePantalla = (string) ($detalle['mensajePantalla'] ?? $mensajeTicket1);

        $linea = '';
        $linea .= self::numerico(5, 1);
        $linea .= self::numerico($cpe, 19);
        $linea .= self::numerico($idFactura, 20);
        $linea .= self::numerico(0, 1);
        $linea .= self::numerico($fecha1, 8);
        $linea .= self::importe($importe1, 11);
        $linea .= self::numerico($fecha2, 8);
        $linea .= self::importe($importe2, 11);
        $linea .= self::numerico($fecha3, 8);
        $linea .= self::importe($importe3, 11);
        $linea .= self::numerico(0, 19);
        $linea .= self::numerico($cpe, 19);
        $linea .= self::alfanumerico($mensajeTicket1, 15);
        $linea .= self::alfanumerico($mensajeTicket2, 25);
        $linea .= self::alfanumerico($mensajePantalla, 15);
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
        $linea .= self::importe($totalImporte1, self::PIE_TOTAL_IMPORTE);
        $linea .= self::numerico(0, self::PIE_FILLER);

        return self::asegurarLongitudNumerica($linea, self::LARGO_LINEA);
    }

    /**
     * @param  list<array<string, mixed>>  $detalles
     */
    private static function cuitDesdeDetalles(array $detalles): string
    {
        $cuits = [];

        foreach ($detalles as $detalle) {
            $idNivel = (int) ($detalle['idNivel'] ?? 0);
            if ($idNivel <= 0) {
                continue;
            }

            $cuit = SiroCodigoPagoElectronico::cuitPorNivel($idNivel);
            if (strlen($cuit) >= 11) {
                $cuits[$cuit] = true;
            }
        }

        $unicos = array_keys($cuits);
        if (count($unicos) === 1) {
            return $unicos[0];
        }

        if (count($unicos) > 1) {
            throw new \RuntimeException(
                'Los cupones seleccionados pertenecen a niveles con distinto CUIT. Genere un archivo por nivel.'
            );
        }

        return '';
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

    private static function asegurarLongitudNumerica(string $linea, int $longitud): string
    {
        if (strlen($linea) > $longitud) {
            return substr($linea, 0, $longitud);
        }

        if (strlen($linea) < $longitud) {
            return str_pad($linea, $longitud, '0', STR_PAD_RIGHT);
        }

        return $linea;
    }
}
