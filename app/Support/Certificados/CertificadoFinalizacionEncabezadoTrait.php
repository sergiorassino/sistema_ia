<?php

namespace App\Support\Certificados;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use TCPDF;

/**
 * Encabezado (escudos) y pie de firmas de certificados de finalización Córdoba.
 */
trait CertificadoFinalizacionEncabezadoTrait
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_UTIL = 170.0;

    /**
     * @param  array<string, mixed>  $institucion
     */
    private function dibujarEscudos(array $institucion, float $altoEscudoNac, float $yNac = 40.0, bool $conLeyenda = true): void
    {
        $nac = $institucion['escudo_nac'] ?? null;
        $leyenda = $institucion['leyenda_nacion'] ?? null;
        $prov = $institucion['escudo_prov'] ?? null;

        if (is_string($nac) && $nac !== '' && is_file($nac)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($nac), 25, $yNac, 23, $altoEscudoNac, '', '', '', false, 300);
        }

        if ($conLeyenda && is_string($leyenda) && $leyenda !== '' && is_file($leyenda)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($leyenda), 72, 30, 65, 7, '', '', '', false, 300);
        }

        if (is_string($prov) && $prov !== '' && is_file($prov)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($prov), 164, $yNac + 1.0, 20, 20, '', '', '', false, 300);
        }
    }

    /**
     * Escudos del modelo Word provincial (sexto): Nación a la izquierda, Córdoba a la derecha.
     * Tamaños según extents del .docx (EMU → mm).
     *
     * @param  array<string, mixed>  $institucion
     */
    private function dibujarEscudosModeloWord(array $institucion): void
    {
        $nac = $institucion['escudo_nac'] ?? null;
        $prov = $institucion['escudo_prov'] ?? null;
        $margenDer = 20.0;
        $anchoPagina = 210.0;

        if (is_string($nac) && $nac !== '' && is_file($nac)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($nac), self::MARGEN_IZQ, 18.0, 23.3, 27.5, '', '', '', false, 300);
        }

        if (is_string($prov) && $prov !== '' && is_file($prov)) {
            $anchoProv = 21.0;
            $xProv = $anchoPagina - $margenDer - $anchoProv;
            $this->Image(TcpdfImagenPng::fuenteTcpdf($prov), $xProv, 17.5, $anchoProv, 27.8, '', '', '', false, 300);
        }
    }

    private function dibujarPieFirmas(float $tamFuente): void
    {
        $this->SetY(255);
        TcpdfFuenteArial::aplicar($this, '', $tamFuente);
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(40, 3, '...........................................................', 0, 0, 'C');
        $this->Cell(47, 3, 'Sello de', 0, 0, 'C');
        $this->Cell(40, 3, '...........................................................', 0, 0, 'C');
        $this->Cell(47, 3, 'Sello de la', 0, 1, 'C');

        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(40, 3, 'Inspector de Zona DGIPE', 0, 0, 'C');
        $this->Cell(47, 3, 'DGIPE', 0, 0, 'C');
        $this->Cell(40, 3, 'Directora de la Institución', 0, 0, 'C');
        $this->Cell(47, 3, 'Institución', 0, 0, 'C');
    }

    /**
     * @param  list<array{t: string, b?: bool}>  $partes
     */
    private function writeMezclado(array $partes, float $alto = 7, float $tam = 11): void
    {
        foreach ($partes as $parte) {
            $texto = (string) ($parte['t'] ?? '');
            if ($texto === '') {
                continue;
            }
            TcpdfFuenteArial::aplicar($this, ! empty($parte['b']) ? 'B' : '', $tam);
            $this->Write($alto, $texto);
        }
    }

    /**
     * Párrafo justificado con tramos en negrita. La última línea queda a la izquierda (sin estirar).
     *
     * @param  list<array{t: string, b?: bool}>  $partes
     */
    private function writeMezcladoJustificado(array $partes, float $alto = 6.5, float $tam = 11): void
    {
        $palabras = $this->partesAPalabras($partes);
        if ($palabras === []) {
            return;
        }

        $x = $this->GetX();
        $ancho = self::ANCHO_UTIL;
        TcpdfFuenteArial::aplicar($this, '', $tam);
        $anchoEspacio = $this->GetStringWidth(' ');

        $lineas = [];
        $linea = [];
        $anchoLinea = 0.0;

        foreach ($palabras as $palabra) {
            TcpdfFuenteArial::aplicar($this, ! empty($palabra['b']) ? 'B' : '', $tam);
            $w = $this->GetStringWidth($palabra['t']);
            $sep = $linea === [] ? 0.0 : $anchoEspacio;
            if ($linea !== [] && ($anchoLinea + $sep + $w) > $ancho) {
                $lineas[] = $linea;
                $linea = [$palabra];
                $anchoLinea = $w;

                continue;
            }

            $linea[] = $palabra;
            $anchoLinea += $sep + $w;
        }

        if ($linea !== []) {
            $lineas[] = $linea;
        }

        $total = count($lineas);
        foreach ($lineas as $indice => $palabrasLinea) {
            $this->SetX($x);
            $esUltima = $indice === $total - 1;
            if ($esUltima || count($palabrasLinea) < 2) {
                $this->escribirLineaMezcladaIzquierda($palabrasLinea, $alto, $tam, $anchoEspacio);
            } else {
                $this->escribirLineaMezcladaJustificada($palabrasLinea, $alto, $tam, $ancho);
            }
            $this->Ln($alto);
        }
    }

    /**
     * @param  list<array{t: string, b?: bool}>  $partes
     * @return list<array{t: string, b: bool}>
     */
    private function partesAPalabras(array $partes): array
    {
        $palabras = [];
        foreach ($partes as $parte) {
            $texto = (string) ($parte['t'] ?? '');
            if (trim($texto) === '') {
                continue;
            }
            $bold = ! empty($parte['b']);
            $trozos = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY);
            if ($trozos === false) {
                continue;
            }
            foreach ($trozos as $trozo) {
                $palabras[] = ['t' => $trozo, 'b' => $bold];
            }
        }

        return $palabras;
    }

    /**
     * @param  list<array{t: string, b: bool}>  $palabras
     */
    private function escribirLineaMezcladaIzquierda(array $palabras, float $alto, float $tam, float $anchoEspacio): void
    {
        $ultimo = count($palabras) - 1;
        foreach ($palabras as $i => $palabra) {
            TcpdfFuenteArial::aplicar($this, $palabra['b'] ? 'B' : '', $tam);
            $this->Write($alto, $palabra['t']);
            if ($i < $ultimo) {
                TcpdfFuenteArial::aplicar($this, '', $tam);
                $this->Cell($anchoEspacio, $alto, '', 0, 0, 'L');
            }
        }
    }

    /**
     * @param  list<array{t: string, b: bool}>  $palabras
     */
    private function escribirLineaMezcladaJustificada(array $palabras, float $alto, float $tam, float $ancho): void
    {
        $anchoPalabras = 0.0;
        foreach ($palabras as $palabra) {
            TcpdfFuenteArial::aplicar($this, $palabra['b'] ? 'B' : '', $tam);
            $anchoPalabras += $this->GetStringWidth($palabra['t']);
        }

        $huecos = count($palabras) - 1;
        $extra = $huecos > 0 ? max(0.0, $ancho - $anchoPalabras) / $huecos : 0.0;
        $ultimo = count($palabras) - 1;

        foreach ($palabras as $i => $palabra) {
            TcpdfFuenteArial::aplicar($this, $palabra['b'] ? 'B' : '', $tam);
            $w = $this->GetStringWidth($palabra['t']);
            $this->Cell($w + ($i < $ultimo ? $extra : 0.0), $alto, $palabra['t'], 0, 0, 'L');
        }
    }

    private function textoOGuiones(string $valor, string $guiones = '_______'): string
    {
        $t = trim($valor);

        return $t !== '' ? $t : $guiones;
    }

    private function parrafoJustificado(string $texto, float $alto = 7): void
    {
        TcpdfMultiCellJustificado::escribir($this, self::ANCHO_UTIL, $alto, $texto);
    }
}
