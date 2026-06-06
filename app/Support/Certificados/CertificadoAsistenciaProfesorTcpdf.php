<?php



namespace App\Support\Certificados;



use TCPDF;



/**

 * Certificado de asistencia del profesor (A4, formato legacy FPDF → TCPDF).

 */

final class CertificadoAsistenciaProfesorTcpdf extends TCPDF

{

    private const MARGEN_IZQ = 20.0;



    private const ANCHO_UTIL = 180.0;



    private const FUENTE = 'dejavusans';



    /** Separación entre el último párrafo y el bloque de firmas (3,5 cm, legacy). */

    private const ESPACIO_TEXTO_FIRMAS_MM = 35.0;



    /** @var array<string, mixed> */

    private array $datos;



    /**

     * @param  array<string, mixed>  $datos

     */

    private function __construct(array $datos)

    {

        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->datos = $datos;

        $this->SetCreator('Sistema Escolar');

        $this->SetAuthor('Sistema Escolar');

        $this->SetTitle('Certificado de Asistencia del Profesor');

        $this->setPrintHeader(false);

        $this->setPrintFooter(false);

        $this->SetAutoPageBreak(true, 15);

        $this->SetMargins(self::MARGEN_IZQ, 10, 15);

        $this->SetFillColor(255, 255, 255);

    }



    /**

     * @param  array<string, mixed>  $datos

     */

    public static function generar(array $datos): self

    {

        $pdf = new self($datos);

        $pdf->AddPage();

        $pdf->dibujarEncabezado();

        $pdf->dibujarCuerpo();

        $pdf->dibujarFirmas();



        return $pdf;

    }



    private function dibujarEncabezado(): void

    {

        $inst = $this->datos['institucion'] ?? [];

        $insti = trim((string) ($inst['insti'] ?? ''));

        $logo = $inst['logo_abs'] ?? null;



        $y0 = 10.0;

        $this->Rect(self::MARGEN_IZQ, $y0, self::ANCHO_UTIL, 22.0, 'D');



        if (is_string($logo) && $logo !== '' && is_file($logo)) {

            $this->Image($logo, self::MARGEN_IZQ + 5, $y0 + 1, 15, 20, '', '', '', false, 300);

        }



        $this->SetXY(self::MARGEN_IZQ, $y0 + 5);

        $this->SetFont(self::FUENTE, 'B', 12);

        $this->Cell(self::ANCHO_UTIL, 7, $insti, 0, 2, 'C');



        $this->SetFont(self::FUENTE, '', 10);

        $this->Cell(self::ANCHO_UTIL, 5, '', 0, 2, 'C');

    }



    private function dibujarCuerpo(): void

    {

        $prof = $this->datos['profesor'] ?? [];

        $cert = $this->datos['certificado'] ?? [];



        $apellido = trim((string) ($prof['apellido'] ?? ''));

        $nombre = trim((string) ($prof['nombre'] ?? ''));

        $dni = trim((string) ($prof['dni'] ?? ''));

        $texto = trim((string) ($cert['texto'] ?? ''));

        $parapre = trim((string) ($cert['parapre'] ?? ''));

        $diaEm = trim((string) ($cert['diaEmision'] ?? ''));

        $mesEm = trim((string) ($cert['mesEmision'] ?? ''));

        $anioEm = trim((string) ($cert['anioEmision'] ?? ''));



        $this->SetXY(self::MARGEN_IZQ, 41);

        $this->SetFont(self::FUENTE, '', 10);



        $indent = str_repeat(' ', 28);

        $this->Write(5, $indent.'Se hace constar que: ');

        $this->Ln(10);



        $nombreCompleto = trim($apellido.' '.$nombre);

        $htmlNombre = '<b>'.$this->escapeHtml($nombreCompleto).'</b>'

            .', D.N.I. Nº '.$this->escapeHtml($dni).' .';



        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $htmlNombre, 0, 1, false, true, 'L', true);



        $this->Ln(6);



        $htmlCuerpo = 'Es PROFESOR/A en este Establecimiento, '.$this->escapeHtml($texto);

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $htmlCuerpo, 0, 1, false, true, 'J', true);



        $this->Ln(6);



        $htmlPie = 'A pedido del/de la interesado/a y a los fines que se requieran, se extiende la presente, a los '

            .$this->escapeHtml($diaEm).' días del mes de '.$this->escapeHtml($mesEm)

            .' del año '.$this->escapeHtml($anioEm).', para ser presentada '.$this->escapeHtml($parapre).'.';



        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $htmlPie, 0, 1, false, true, 'J', true);

    }



    private function dibujarFirmas(): void

    {

        $y = $this->GetY() + self::ESPACIO_TEXTO_FIRMAS_MM;



        $this->SetFont(self::FUENTE, '', 8);

        $this->SetXY(50, $y);

        $this->Cell(20, 5, 'Sello', 0, 0, 'C');



        $this->SetXY(120, $y);

        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');



        $this->SetXY(120, $y + 3);

        $this->Cell(50, 5, 'Firma Autorizada', 0, 0, 'C');

    }



    private function escapeHtml(string $texto): string

    {

        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    }

}

