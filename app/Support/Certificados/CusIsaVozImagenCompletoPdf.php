<?php

namespace App\Support\Certificados;

use App\Support\Pdf\PdfCombinadorArchivos;
use TCPDF;

/**
 * C.U.S., I.S.A. y uso de imagen y voz de un alumno, en ese orden, en un solo PDF.
 */
final class CusIsaVozImagenCompletoPdf
{
    /**
     * @param  list<array<string, mixed>>  $alumnos
     */
    public static function generarBinario(array $alumnos, string $insti): string
    {
        if ($alumnos === []) {
            throw new \InvalidArgumentException('No hay alumnos para el PDF.');
        }

        $temporales = [];

        try {
            foreach ($alumnos as $alumno) {
                $uno = [$alumno];
                $temporales[] = self::volcar(CertificadoUnicoSaludTcpdf::generarLote($uno));
                $temporales[] = self::volcar(InformeSaludAnualTcpdf::generarLote($uno, $insti));
                $temporales[] = self::volcar(UsoImagenVozTcpdf::generarLote($uno, $insti));
            }

            $salida = self::rutaPdfTemporal();
            $temporales[] = $salida;
            $fuentes = array_slice($temporales, 0, -1);
            PdfCombinadorArchivos::combinar($fuentes, $salida);

            $binario = file_get_contents($salida);
            if (! is_string($binario) || $binario === '') {
                throw new \RuntimeException('No se pudo armar el PDF.');
            }

            return $binario;
        } finally {
            foreach ($temporales as $ruta) {
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }
    }

    private static function volcar(TCPDF $pdf): string
    {
        $pdf->setPDFVersion('1.4');
        $ruta = self::rutaPdfTemporal();
        $escrito = file_put_contents($ruta, $pdf->Output('certificado.pdf', 'S'));
        if ($escrito === false) {
            throw new \RuntimeException('No se pudo escribir el PDF temporal.');
        }

        return $ruta;
    }

    private static function rutaPdfTemporal(): string
    {
        $base = tempnam(sys_get_temp_dir(), 'se-cus-');
        if ($base === false) {
            throw new \RuntimeException('No se pudo crear un archivo temporal.');
        }

        $ruta = $base.'.pdf';
        if (! @rename($base, $ruta)) {
            @unlink($base);
            throw new \RuntimeException('No se pudo preparar el PDF temporal.');
        }

        return $ruta;
    }
}
