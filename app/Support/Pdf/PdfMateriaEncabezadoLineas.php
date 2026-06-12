<?php

namespace App\Support\Pdf;

/**
 * Parte el nombre de una materia en hasta tres renglones para encabezados verticales en PDF.
 */
final class PdfMateriaEncabezadoLineas
{
    /**
     * @return list<string> Entre 1 y 3 líneas (sin vacíos al final).
     */
    public static function partir(string $nombre, int $maxPorLinea = 15, bool $sinTruncar = false): array
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        if ($nombre === '') {
            return [];
        }

        $lineas = self::partirPorSeparadorEstructural($nombre, '/^(.+?):\s*(.+)$/u', $maxPorLinea, $sinTruncar);
        if ($lineas !== []) {
            return self::rebalancearSiHaceFalta($lineas, $maxPorLinea, $sinTruncar);
        }

        $lineas = self::partirPorSeparadorEstructural($nombre, '/^(.+?)\s+-\s+(.+)$/u', $maxPorLinea, $sinTruncar);
        if ($lineas !== []) {
            return self::rebalancearSiHaceFalta($lineas, $maxPorLinea, $sinTruncar);
        }

        if (mb_strlen($nombre) <= $maxPorLinea) {
            return [$nombre];
        }

        return self::partirPorPalabras($nombre, 3, $maxPorLinea, $sinTruncar);
    }

    /**
     * Reparte hasta 3 líneas entre la parte anterior y posterior de «:» o « - ».
     *
     * @return list<string>
     */
    private static function partirPorSeparadorEstructural(string $nombre, string $patron, int $maxPorLinea, bool $sinTruncar): array
    {
        if (preg_match($patron, $nombre, $coincidencia) !== 1) {
            return [];
        }

        $antes = trim($coincidencia[1]);
        $despues = trim($coincidencia[2]);
        if ($antes === '' || $despues === '') {
            return [];
        }

        $palabrasAntes = count(preg_split('/\s+/u', $antes) ?: []);
        $lineasParaAntes = $palabrasAntes <= 1 ? 1 : min(2, $palabrasAntes);

        $lineasAntes = self::partirPorPalabras($antes, $lineasParaAntes, $maxPorLinea, $sinTruncar);
        $restante = max(1, 3 - count($lineasAntes));

        return self::normalizarSalida(
            array_merge(
                $lineasAntes,
                self::partirPorPalabras($despues, $restante, $maxPorLinea, $sinTruncar),
            ),
            3,
        );
    }

    /**
     * Si quedó una línea demasiado larga y aún hay cupo, redivide el nombre completo en 3.
     *
     * @param  list<string>  $lineas
     * @return list<string>
     */
    private static function rebalancearSiHaceFalta(array $lineas, int $maxPorLinea, bool $sinTruncar): array
    {
        $maxLen = max(array_map(fn (string $l) => mb_strlen($l), $lineas));
        if ($maxLen <= $maxPorLinea) {
            return $lineas;
        }

        $texto = implode(' ', $lineas);
        $rebalanceadas = self::partirPorPalabras($texto, 3, $maxPorLinea, $sinTruncar);
        $nuevoMax = max(array_map(fn (string $l) => mb_strlen($l), $rebalanceadas));

        return $nuevoMax < $maxLen ? $rebalanceadas : $lineas;
    }

    /**
     * @return list<string>
     */
    private static function partirPorPalabras(string $texto, int $cantidadLineas, int $maxPorLinea, bool $sinTruncar): array
    {
        $cantidadLineas = max(1, min(3, $cantidadLineas));
        $palabras = preg_split('/\s+/u', $texto) ?: [];

        if ($palabras === []) {
            return [];
        }

        if (count($palabras) <= $cantidadLineas) {
            return self::normalizarSalida(
                array_map(fn (string $p) => self::limitar($p, $maxPorLinea, $sinTruncar), $palabras),
                $cantidadLineas,
            );
        }

        if ($cantidadLineas === 1) {
            return [self::limitar($texto, $maxPorLinea, $sinTruncar)];
        }

        if ($cantidadLineas === 2) {
            return self::partirEnDosPorPalabras($palabras, $maxPorLinea, $sinTruncar);
        }

        return self::partirEnTresPorPalabras($palabras, $maxPorLinea, $sinTruncar);
    }

    /**
     * @param  list<string>  $palabras
     * @return list<string>
     */
    private static function partirEnDosPorPalabras(array $palabras, int $maxPorLinea, bool $sinTruncar): array
    {
        $mejor = ['', ''];
        $mejorPuntaje = PHP_INT_MAX;

        for ($i = 1; $i < count($palabras); $i++) {
            $linea1 = implode(' ', array_slice($palabras, 0, $i));
            $linea2 = implode(' ', array_slice($palabras, $i));
            $puntaje = self::puntajeLineas([$linea1, $linea2]);

            if ($puntaje < $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = [$linea1, $linea2];
            }
        }

        return [
            self::limitar($mejor[0], $maxPorLinea, $sinTruncar),
            self::limitar($mejor[1], $maxPorLinea, $sinTruncar),
        ];
    }

    /**
     * @param  list<string>  $palabras
     * @return list<string>
     */
    private static function partirEnTresPorPalabras(array $palabras, int $maxPorLinea, bool $sinTruncar): array
    {
        if (count($palabras) <= 3) {
            return array_map(
                fn (string $p) => self::limitar($p, $maxPorLinea, $sinTruncar),
                $palabras,
            );
        }

        $mejor = ['', '', ''];
        $mejorPuntaje = PHP_INT_MAX;

        for ($i = 1; $i < count($palabras) - 1; $i++) {
            for ($j = $i + 1; $j < count($palabras); $j++) {
                $lineas = [
                    implode(' ', array_slice($palabras, 0, $i)),
                    implode(' ', array_slice($palabras, $i, $j - $i)),
                    implode(' ', array_slice($palabras, $j)),
                ];
                $puntaje = self::puntajeLineas($lineas);

                if ($puntaje < $mejorPuntaje) {
                    $mejorPuntaje = $puntaje;
                    $mejor = $lineas;
                }
            }
        }

        return array_map(
            fn (string $linea) => self::limitar($linea, $maxPorLinea, $sinTruncar),
            $mejor,
        );
    }

    /**
     * @param  list<string>  $lineas
     */
    private static function puntajeLineas(array $lineas): int
    {
        $longitudes = array_map(fn (string $l) => mb_strlen($l), $lineas);
        $max = max($longitudes);
        $min = min($longitudes);

        return ($max * 100) + ($max - $min);
    }

    /**
     * @param  list<string>  $lineas
     * @return list<string>
     */
    private static function normalizarSalida(array $lineas, int $maximo): array
    {
        $lineas = array_values(array_filter(
            array_map(fn (string $l) => trim($l), $lineas),
            fn (string $l) => $l !== '',
        ));

        return array_slice($lineas, 0, $maximo);
    }

    private static function limitar(string $texto, int $maxPorLinea, bool $sinTruncar): string
    {
        if ($sinTruncar || mb_strlen($texto) <= $maxPorLinea) {
            return $texto;
        }

        return mb_substr($texto, 0, max(1, $maxPorLinea - 1)).'…';
    }
}
