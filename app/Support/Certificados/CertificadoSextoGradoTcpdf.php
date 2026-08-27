<?php

namespace App\Support\Certificados;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Certificado de estudios primarios (sexto grado) — A4 vertical, plantilla Word Monte Cristo.
 */
final class CertificadoSextoGradoTcpdf extends TCPDF
{
    use CertificadoFinalizacionEncabezadoTrait;

    private const ALTO_CUERPO = 6.2;

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
        $this->SetTitle('Certificado de Estudios — Educación Primaria');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, 20, 20);
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

        $this->dibujarEscudosModeloWord($inst);

        $this->SetY(21);
        TcpdfFuenteArial::aplicar($this, 'B', 14);
        $this->Cell(self::ANCHO_UTIL, 6, 'REPÚBLICA ARGENTINA', 0, 1, 'C');
        $this->Ln(4);

        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(self::ANCHO_UTIL, 4, 'LEY DE EDUCACIÓN NACIONAL Nº 26.206', 0, 1, 'C');
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_UTIL, 4, 'GOBIERNO DE LA PROVINCIA DE CÓRDOBA', 0, 1, 'C');
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(self::ANCHO_UTIL, 4, 'LEY DE EDUCACIÓN PROVINCIAL Nº 9870', 0, 1, 'C');
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_UTIL, 4, 'MINISTERIO DE EDUCACIÓN DE LA PROVINCIA DE CÓRDOBA', 0, 1, 'C');
        $this->Cell(self::ANCHO_UTIL, 4, 'DIRECCIÓN GENERAL DE INSTITUTOS PRIVADOS DE ENSEÑANZA', 0, 1, 'C');

        $this->Ln(3);
        TcpdfFuenteArial::aplicar($this, '', 9);
        $serie = trim((string) ($cert['serie'] ?? ''));
        $this->Cell(self::ANCHO_UTIL, 5, $serie !== '' ? 'Serie  '.$serie : '', 0, 1, 'R');

        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, 'B', 14);
        $this->Cell(self::ANCHO_UTIL, 7, 'CERTIFICADO DE ESTUDIOS', 0, 1, 'C');
        $this->Cell(self::ANCHO_UTIL, 7, 'EDUCACIÓN PRIMARIA', 0, 1, 'C');

        $this->Ln(6);
        $this->SetX(self::MARGEN_IZQ);

        $insti = trim((string) ($inst['insti'] ?? ''));
        $this->writeMezcladoJustificado([
            ['t' => 'La Dirección del '],
            ['t' => $insti !== '' ? $insti : $this->textoOGuiones(''), 'b' => true],
            ['t' => ', C.U.E. Nº '.$this->textoOGuiones((string) ($inst['cue'] ?? ''))
                .' ubicado en '.$this->textoOGuiones((string) ($inst['direccion'] ?? ''))
                .', Localidad '.$this->textoOGuiones((string) ($inst['localidad'] ?? ''))
                .', Departamento '.$this->textoOGuiones((string) ($inst['departamento'] ?? ''))
                .', Provincia de '.$this->textoOGuiones((string) ($inst['provincia'] ?? '')).'.'],
        ], self::ALTO_CUERPO, 11);

        $this->Ln(3);
        $this->SetX(self::MARGEN_IZQ);

        $nombreCompleto = mb_strtoupper(
            trim((string) ($alu['nombre'] ?? '').' '.(string) ($alu['apellido'] ?? '')),
            'UTF-8'
        );
        $mesApro = trim((string) ($cert['mesApro'] ?? ''));
        $anoApro = CertificadoFinalizacionTextoEs::enLetrasDesdeTexto((string) ($cert['anoApro'] ?? ''));

        $this->writeMezcladoJustificado([
            ['t' => 'CERTIFICA', 'b' => true],
            ['t' => 'que:'],
            ['t' => $nombreCompleto !== '' ? $nombreCompleto : $this->textoOGuiones(''), 'b' => true],
            ['t' => ', Documento Nacional de Identidad Nº: '
                .$this->textoOGuiones((string) ($alu['dni_puntos'] ?? $alu['dni'] ?? ''))
                .', nacido en '.$this->textoOGuiones((string) ($alu['ln_ciudad'] ?? ''))
                .', Dpto. '.$this->textoOGuiones((string) ($alu['ln_depto'] ?? ''))
                .', Provincia de '.$this->textoOGuiones((string) ($alu['ln_provincia'] ?? ''))
                .', País '.$this->textoOGuiones((string) ($alu['ln_pais'] ?? ''))
                .', el día '.$this->textoOGuiones((string) ($alu['dia_naci'] ?? ''))
                .' de '.$this->textoOGuiones((string) ($alu['mes_naci'] ?? ''))
                .' de '.$this->textoOGuiones((string) ($alu['ano_naci'] ?? '')).','],
            ['t' => 'aprobó', 'b' => true],
            ['t' => 'los estudios correspondientes al SEXTO GRADO de Educación Primaria acorde a la estructura del Sistema Educativo vigente, en el mes de: '
                .$this->textoOGuiones($mesApro)
                .' del año: '
                .$this->textoOGuiones($anoApro).','],
            ['t' => 'quedando acreditado para acceder al PRIMER AÑO de Educación Secundaria.', 'b' => true],
        ], self::ALTO_CUERPO, 11);

        $this->Ln(3);
        $this->SetX(self::MARGEN_IZQ);
        $this->writeMezcladoJustificado([
            ['t' => 'ESTUDIOS CON VALIDEZ NACIONAL otorgada por Decreto del Poder Ejecutivo Nacional Nº 209/05 – Resolución Nacional Nº 2565/16 – E-APN-ME Y Resoluciones Ministeriales Provinciales Nº 345/11 y 270/19 – Normativas que aprueban el DISEÑO CURRICULAR PARA LA EDUCACIÓN PRIMARIA DE LA PROVINCIA DE CÓRDOBA –', 'b' => true],
        ], self::ALTO_CUERPO, 11);

        $this->Ln(3);
        $this->SetX(self::MARGEN_IZQ);
        $diaEmision = CertificadoFinalizacionTextoEs::enLetrasDesdeTexto((string) ($cert['diaEmision'] ?? ''));
        $mesEmision = trim((string) ($cert['mesEmision'] ?? ''));
        $anoEmision = CertificadoFinalizacionTextoEs::enLetrasDesdeTexto((string) ($cert['anoEmision'] ?? ''));

        $this->writeMezcladoJustificado([
            ['t' => 'Se extiende el presente certificado en: '
                .$this->textoOGuiones((string) ($inst['localidad'] ?? ''))
                .', Departamento: '
                .$this->textoOGuiones((string) ($inst['departamento'] ?? ''))
                .', a los '
                .$this->textoOGuiones($diaEmision)
                .' días del mes de '
                .$this->textoOGuiones($mesEmision)
                .' del año '
                .$this->textoOGuiones($anoEmision).'.'],
        ], self::ALTO_CUERPO, 11);

        $this->Ln(4);
        $this->SetX(self::MARGEN_IZQ);
        TcpdfFuenteArial::aplicar($this, '', 11);
        $ppi = trim((string) ($cert['ppi'] ?? ''));
        if ($ppi === '') {
            $this->Cell(self::ANCHO_UTIL, self::ALTO_CUERPO, 'Observaciones:', 0, 1, 'L');
        } else {
            $this->parrafoJustificado('Observaciones: '.$ppi, self::ALTO_CUERPO);
        }

        $this->dibujarPieFirmasSexto();
    }

    private function dibujarPieFirmasSexto(): void
    {
        $this->SetY(248);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetX(self::MARGEN_IZQ);
        $anchoCol = self::ANCHO_UTIL / 4.0;

        $this->Cell($anchoCol, 4, 'FIRMA Y SELLO DEL', 0, 0, 'C');
        $this->Cell($anchoCol, 4, 'SELLO DE INSPECCIÓN', 0, 0, 'C');
        $this->Cell($anchoCol, 4, 'FIRMA Y SELLO DEL', 0, 0, 'C');
        $this->Cell($anchoCol, 4, 'SELLO DE LA INSTITUCIÓN', 0, 1, 'C');

        $this->SetX(self::MARGEN_IZQ);
        $this->Cell($anchoCol, 4, 'INSPECTOR', 0, 0, 'C');
        $this->Cell($anchoCol, 4, '', 0, 0, 'C');
        $this->Cell($anchoCol, 4, 'DIRECTOR', 0, 0, 'C');
        $this->Cell($anchoCol, 4, '', 0, 1, 'C');
    }
}
