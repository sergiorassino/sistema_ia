<?php

namespace App\Support\Certificados;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Certificado de estudios de educación inicial (sala de 5) — A4 vertical, layout legacy FPDF → TCPDF.
 */
final class CertificadoJardinTcpdf extends TCPDF
{
    use CertificadoFinalizacionEncabezadoTrait;

    /** @var array<string, mixed> */
    private array $hoja;

    /**
     * @param  array<string, mixed>  $hoja
     */
    private function __construct(array $hoja)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->hoja = $hoja;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Certificado de Estudios — Educación Inicial');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, 10, 20);
        $this->SetFillColor(232, 232, 232);
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     */
    public static function generarLote(array $hojas): self
    {
        $primero = $hojas[0] ?? [];
        $pdf = new self($primero);

        foreach ($hojas as $hoja) {
            $pdf->hoja = $hoja;
            $pdf->AddPage('P', 'A4');
            $pdf->dibujarHoja();
        }

        return $pdf;
    }

    public static function respuestaHttp(self $pdf, string $nombreArchivo): \Illuminate\Http\Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $binario = $pdf->Output($nombreArchivo, 'S');

        return response($binario, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombreArchivo.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function dibujarHoja(): void
    {
        $inst = $this->hoja['institucion'] ?? [];
        $alu = $this->hoja['alumno'] ?? [];
        $cert = $this->hoja['certificado'] ?? [];

        $this->dibujarEscudos($inst, 23.0);
        $this->Ln(30);

        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(self::ANCHO_UTIL, 5, 'LEY DE EDUCACIÓN NACIONAL Nº 26.206', 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_UTIL, 5, 'GOBIERNO DE LA PROVINCIA DE CÓRDOBA', 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(self::ANCHO_UTIL, 5, 'LEY DE EDUCACIÓN PROVINCIAL Nº 9870', 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_UTIL, 5, 'MINISTERIO DE EDUCACIÓN DE LA PROVINCIA DE CÓRDOBA', 0, 2, 'C');
        $this->Cell(self::ANCHO_UTIL, 5, 'DIRECCIÓN GENERAL DE INSTITUTOS PRIVADOS DE ENSEÑANZA', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->Ln(6);
        $this->Cell(self::ANCHO_UTIL, 5, 'CERTIFICADO DE ESTUDIOS', 0, 2, 'C');
        $this->Ln(3);
        $this->Cell(self::ANCHO_UTIL, 5, 'EDUCACIÓN INICIAL', 0, 2, 'C');

        $this->Ln(6);
        $this->SetX(130);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(30, 6, 'Serie:'.trim((string) ($cert['serie'] ?? '')), 0, 2, 'L');
        $this->Cell(30, 6, 'Legajo del Estudiante:'.trim((string) ($alu['legajo'] ?? '')), 0, 2, 'L');
        $this->Ln(6);

        TcpdfFuenteArial::aplicar($this, '', 11);
        $insti = trim((string) ($inst['insti'] ?? ''));
        $cue = trim((string) ($inst['cue'] ?? ''));
        $dire = trim((string) ($inst['direccion'] ?? ''));
        $loca = trim((string) ($inst['localidad'] ?? ''));
        $depa = trim((string) ($inst['departamento'] ?? ''));
        $prov = trim((string) ($inst['provincia'] ?? ''));

        $this->parrafoJustificado(
            'La Dirección del '.$insti.' C.U.E. Nº '.$cue.' ubicado en '.$dire
            .' de la Localidad de '.$loca.', Departamento '.$depa.', Provincia de '.$prov.'. '
        );

        $this->Ln(5);
        $apellido = trim((string) ($alu['apellido'] ?? ''));
        $nombre = trim((string) ($alu['nombre'] ?? ''));
        $this->parrafoJustificado(
            'CERTIFICA que   '.$apellido.' '.$nombre
            .', Documento Nacional de Identidad Nº '.trim((string) ($alu['dni'] ?? ''))
            .', nacido en '.trim((string) ($alu['ln_ciudad'] ?? ''))
            .', Dpto  '.trim((string) ($alu['ln_depto'] ?? ''))
            .' Provincia de '.trim((string) ($alu['ln_provincia'] ?? ''))
            .' de '.trim((string) ($alu['ln_pais'] ?? ''))
            .', el día '.trim((string) ($alu['dia_naci'] ?? ''))
            .' del mes de '.trim((string) ($alu['mes_naci'] ?? ''))
            .' del año '.trim((string) ($alu['ano_naci'] ?? ''))
            .', aprobó los estudios correspondientes a la Sección de 5 años de la Educación Inicial, acorde a la estructura del Sistema Educativo vigente,'
            .' en el mes de '.trim((string) ($cert['mesApro'] ?? ''))
            .' del año '.trim((string) ($cert['anoApro'] ?? ''))
            .' quedando acreditado para acceder a PRIMER GRADO de la Educación Primaria.'
        );

        $this->Ln(5);
        $this->parrafoJustificado(
            'ESTUDIOS CON VALIDEZ NACIONAL SEGÚN RESOLUCIÓN DEL MINISTERIO DE EDUCACIÓN DE LA NACIÓN Nº 509/06.'
        );

        $this->Ln(5);
        $this->parrafoJustificado(
            'Se extiende el presente certificado en '.trim((string) ($inst['localidad'] ?? ''))
            .', Departamento '.trim((string) ($inst['departamento'] ?? ''))
            .', a los '.trim((string) ($cert['diaEmision'] ?? ''))
            .' días del mes de '.trim((string) ($cert['mesEmision'] ?? ''))
            .' del año '.trim((string) ($cert['anoEmision'] ?? '')).'.'
        );

        $this->Ln(5);
        $ppi = trim((string) ($cert['ppi'] ?? ''));
        if ($ppi === '') {
            $this->parrafoJustificado(
                'Observaciones: ..........................................................		.....................................................................'
            );
        } else {
            $this->parrafoJustificado('Observaciones: '.$ppi);
        }

        $this->dibujarPieFirmas(6);
    }
}
