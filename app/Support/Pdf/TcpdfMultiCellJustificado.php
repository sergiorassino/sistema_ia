<?php

namespace App\Support\Pdf;

use TCPDF;

/**
 * Texto justificado en TCPDF sin estirar la última línea de cada párrafo.
 *
 * {@see TCPDF::MultiCell()} con alineación `J` justifica también la línea final (queda espaciada de más).
 */
final class TcpdfMultiCellJustificado
{
    /**
     * Escribe un bloque de texto: líneas completas justificadas, última línea alineada a la izquierda.
     *
     * Respeta saltos de línea explícitos (`\n`) como párrafos distintos (cada uno con su propia última línea a la izquierda).
     */
    public static function escribir(TCPDF $pdf, float $ancho, float $alturaLinea, string $texto): void
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        if (trim($texto) === '') {
            return;
        }

        $x = $pdf->GetX();
        $parrafos = preg_split("/\n/", $texto) ?: [];

        foreach ($parrafos as $parrafo) {
            $parrafo = trim($parrafo);
            if ($parrafo === '') {
                continue;
            }

            $lineas = self::partirEnLineas($pdf, $ancho, $parrafo);
            $total = count($lineas);

            foreach ($lineas as $indice => $linea) {
                $pdf->SetX($x);
                $alinear = ($total > 1 && $indice < $total - 1) ? 'J' : 'L';
                $pdf->Cell($ancho, $alturaLinea, $linea, 0, 1, $alinear);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function partirEnLineas(TCPDF $pdf, float $ancho, string $parrafo): array
    {
        $palabras = preg_split('/\s+/u', $parrafo, -1, PREG_SPLIT_NO_EMPTY);
        if ($palabras === false || $palabras === []) {
            return [$parrafo];
        }

        $lineas = [];
        $lineaActual = '';

        foreach ($palabras as $palabra) {
            $candidata = $lineaActual === '' ? $palabra : $lineaActual.' '.$palabra;
            if ($pdf->GetStringWidth($candidata) <= $ancho) {
                $lineaActual = $candidata;

                continue;
            }

            if ($lineaActual !== '') {
                $lineas[] = $lineaActual;
                $lineaActual = $palabra;

                continue;
            }

            // Palabra más ancha que el renglón: se imprime igual para no perder contenido.
            $lineas[] = $palabra;
            $lineaActual = '';
        }

        if ($lineaActual !== '') {
            $lineas[] = $lineaActual;
        }

        return $lineas !== [] ? $lineas : [''];
    }
}
