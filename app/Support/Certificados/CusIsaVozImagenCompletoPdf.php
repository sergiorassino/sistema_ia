<?php

namespace App\Support\Certificados;

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

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema Escolar');
        $pdf->SetAuthor('Sistema Escolar');
        $pdf->SetTitle('C.U.S. / I.S.A. / Voz-Imagen');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $plantillaCus = CusIsaVozImagenDatos::rutaPlantilla('cus.jpg');
        $plantillaIsa = CusIsaVozImagenDatos::rutaPlantilla('isa.jpg');
        $plantillaVoz = CusIsaVozImagenDatos::rutaPlantilla('autorizacionImagen.jpg');

        foreach ($alumnos as $alumno) {
            CertificadoUnicoSaludTcpdf::aplicarMargenes($pdf);
            $pdf->AddPage('P', 'A4');
            CertificadoUnicoSaludTcpdf::dibujarPagina($pdf, $alumno, $plantillaCus);

            InformeSaludAnualTcpdf::aplicarMargenes($pdf);
            $pdf->AddPage('P', 'A4');
            InformeSaludAnualTcpdf::dibujarPagina($pdf, $alumno, $plantillaIsa, $insti);

            UsoImagenVozTcpdf::aplicarMargenes($pdf);
            $pdf->AddPage('P', 'A4');
            UsoImagenVozTcpdf::dibujarPagina($pdf, $alumno, $plantillaVoz, $insti);
        }

        return $pdf->Output('cus_isa_voz_imagen.pdf', 'S');
    }
}
