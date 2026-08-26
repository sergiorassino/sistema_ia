<?php



namespace App\Support\Certificados;



use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;



/**

 * Solicitud de pase (A4, formato legacy FPDF → TCPDF).

 */

final class SolicitudDePaseTcpdf extends TCPDF

{

    private const MARGEN_IZQ = 20.0;



    private const ANCHO_UTIL = 170.0;



    private const FUENTE = 'dejavusans';

    private const LOGO_X = 25.0;

    private const LOGO_Y = 11.0;

    private const LOGO_ANCHO = 15.0;

    private const LOGO_ALTO = 20.0;

    private const SEP_LOGO_TITULO = 2.0;

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

        $this->SetTitle('Solicitud de Pase');

        $this->setPrintHeader(false);

        $this->setPrintFooter(false);

        $this->SetAutoPageBreak(true, 12);

        $this->SetMargins(self::MARGEN_IZQ, 9, 20);

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

        $pdf->dibujarSolicitud();

        $pdf->dibujarFirmasSolicitud();

        $pdf->dibujarSeccionPase();

        $pdf->dibujarFirmasAutoridades();

        $pdf->dibujarTroquel();



        return $pdf;

    }



    private function dibujarEncabezado(): void
    {
        $inst = $this->datos['institucion'] ?? [];
        $logo = $inst['logo_abs'] ?? null;

        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), self::LOGO_X, self::LOGO_Y, self::LOGO_ANCHO, self::LOGO_ALTO, '', '', '', false, 300);
        }

        $this->SetFont(self::FUENTE, '', 8);
        $this->SetXY(self::MARGEN_IZQ, 9);
        $this->Cell(self::ANCHO_UTIL, 5, 'ANEXO', 0, 2, 'R');
        $this->Cell(self::ANCHO_UTIL, 5, 'Resolución CFE Nº 59/08', 0, 2, 'R');

        $xTitulo = self::LOGO_X + self::LOGO_ANCHO + self::SEP_LOGO_TITULO;
        $anchoTitulo = (self::MARGEN_IZQ + self::ANCHO_UTIL) - $xTitulo;
        $yTitulo = self::LOGO_Y + self::LOGO_ALTO - 6.0;
        $altoTitulo = 6.0;

        $this->SetFont(self::FUENTE, 'B', 10);
        $this->SetXY($xTitulo, $yTitulo);
        $this->Cell($anchoTitulo, $altoTitulo, 'SOLICITUD DE PASE', 1, 0, 'C');

        $this->SetY(max(self::LOGO_Y + self::LOGO_ALTO, $yTitulo + $altoTitulo) + 2);
    }



    private function dibujarSolicitud(): void

    {

        $leg = $this->datos['legajo'] ?? [];

        $sol = $this->datos['solicitud'] ?? [];

        $inst = $this->datos['institucion'] ?? [];



        $apellido = trim((string) ($leg['apellido'] ?? ''));

        $nombre = trim((string) ($leg['nombre'] ?? ''));

        $curso = trim((string) ($sol['curso'] ?? ''));

        $localidad = trim((string) ($inst['localidad'] ?? ''));

        $insti = trim((string) ($inst['insti'] ?? ''));

        $dia = trim((string) ($sol['diaEmision'] ?? ''));

        $mes = trim((string) ($sol['mesEmision'] ?? ''));

        $anio = trim((string) ($sol['anioEmision'] ?? ''));



        $this->SetXY(self::MARGEN_IZQ, 41);

        $this->SetFont(self::FUENTE, '', 10);



        $fechaLinea = $localidad.', '.$dia.' de '.$mes.' del '.$anio;

        $this->Cell(self::ANCHO_UTIL, 5, $fechaLinea, 0, 2, 'R');

        $this->Ln(2);



        $destinatario = $insti !== '' ? $insti : 'este establecimiento';
        $html1 = 'Sr. Director del <b>'.$this->escapeHtml($destinatario).'</b>';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $html1, 0, 1, false, true, 'J', true);



        $html2 = 'El que suscribe: Alumna/o <b>'.$this->escapeHtml($apellido.' '.$nombre).'</b> de '

            .$this->escapeHtml($curso).', del establecimiento <b>'

            .$this->escapeHtml($insti !== '' ? $insti : 'este establecimiento').'</b>'

            .' por razones de índole particular, solicita le conceda el PASE y certificación de estudios incompletos para la prosecución de estudios.';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $html2, 0, 1, false, true, 'J', true);



        $html3 = 'Saluda a Ud. muy atte.';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $html3, 0, 1, false, true, 'J', true);

    }



    private function dibujarFirmasSolicitud(): void

    {

        $this->Ln(10);

        $y = $this->GetY();



        $this->SetFont(self::FUENTE, '', 7);

        $this->SetXY(40, $y);

        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');

        $this->SetXY(40, $y + 3);

        $this->Cell(50, 5, 'Firma del Padre / Madre', 0, 0, 'C');



        $this->SetXY(120, $y);

        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');

        $this->SetXY(120, $y + 3);

        $this->Cell(50, 5, 'Firma de alumno/a', 0, 0, 'C');



        $this->SetY($y + 10);

    }



    private function dibujarSeccionPase(): void

    {

        $leg = $this->datos['legajo'] ?? [];

        $sol = $this->datos['solicitud'] ?? [];

        $inst = $this->datos['institucion'] ?? [];



        $apellido = trim((string) ($leg['apellido'] ?? ''));

        $nombre = trim((string) ($leg['nombre'] ?? ''));

        $dni = trim((string) ($leg['dni'] ?? ''));

        $curso = trim((string) ($sol['curso'] ?? ''));

        $plan = trim((string) ($sol['plan'] ?? ''));

        $cursosCompletos = trim((string) ($sol['cursosCompletos'] ?? ''));

        $mateAdeud = trim((string) ($sol['mateAdeud'] ?? ''));

        $cursar = trim((string) ($sol['cursar'] ?? ''));

        $preAnte = trim((string) ($sol['preAnte'] ?? ''));

        $localidad = trim((string) ($inst['localidad'] ?? ''));

        $insti = trim((string) ($inst['insti'] ?? ''));

        $dia = trim((string) ($sol['diaEmision'] ?? ''));

        $mes = trim((string) ($sol['mesEmision'] ?? ''));

        $anio = trim((string) ($sol['anioEmision'] ?? ''));



        $this->Ln(3);

        $this->SetFont(self::FUENTE, 'B', 10);

        $this->Cell(self::ANCHO_UTIL, 5, 'PASE', 1, 2, 'C');

        $this->Ln(3);



        $this->SetFont(self::FUENTE, '', 10);



        $bloques = [

            'Establecimiento educativo '.$this->escapeHtml($insti !== '' ? $insti : ''),

            'Se hace constar que '.$this->escapeHtml($apellido.' '.$nombre).' de '.$this->escapeHtml($curso)

                .', plan de estudios de '.$this->escapeHtml($plan)

                .' tiene en trámite su certificado de estudios incompletos (analítico parcial).',

            'Tipo y Nº de documento DNI: '.$this->escapeHtml($dni),

            'Cursos completos: '.$this->escapeHtml($cursosCompletos),

            'Espacio Curricular que adeuda: '.$this->escapeHtml($mateAdeud),

            'Cursar y aprobar todos los espacios curriculares: '.$this->escapeHtml($cursar),

            'Observaciones: .........................................................................................................',

            'A pedido del interesado/a y a solo efecto de ser presentada ante las autoridades educativas del centro educativo '

                .$this->escapeHtml($preAnte)

                .' se extiende la presente, sin enmiendas ni raspaduras, en '.$this->escapeHtml($localidad)

                .' a los '.$this->escapeHtml($dia).' de '.$this->escapeHtml($mes).' del '.$this->escapeHtml($anio),

        ];



        foreach ($bloques as $html) {

            $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $html, 0, 1, false, true, 'J', true);

        }

    }



    private function dibujarFirmasAutoridades(): void

    {

        $this->Ln(20);

        $y = $this->GetY();



        $this->SetFont(self::FUENTE, '', 8);

        $this->SetXY(40, $y);

        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');

        $this->SetXY(40, $y + 3);

        $this->Cell(50, 5, 'Secretario/a', 0, 0, 'C');



        $this->SetXY(120, $y);

        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');

        $this->SetXY(120, $y + 3);

        $this->Cell(50, 5, 'Director', 0, 0, 'C');



        $this->SetY($y + 10);

    }



    private function dibujarTroquel(): void

    {

        $this->Ln(2);

        $this->SetFont(self::FUENTE, '', 10);

        $this->Cell(self::ANCHO_UTIL, 5, str_repeat('-', 120), 0, 1, 'C');

        $this->Ln(6);



        $inst = $this->datos['institucion'] ?? [];
        $instiTroquel = trim((string) ($inst['insti'] ?? ''));
        if ($instiTroquel === '') {
            $instiTroquel = 'este establecimiento';
        }

        $this->MultiCell(self::ANCHO_UTIL, 5, 'Sres. Padres:', 0, 'J', false, 1);

        $this->MultiCell(

            self::ANCHO_UTIL,

            5,

            'Completar en la escuela receptora y entregar este troquel al '.$instiTroquel.'. Debe constar en el legajo del alumno como constancia de matrícula y para confeccionar el analítico parcial.',

            0,

            'J',

            false,

            1,

        );

        $this->Ln(3);

        $this->MultiCell(

            self::ANCHO_UTIL,

            5,

            'La Institución receptora:  .........................................................................  Nº CUE  .........................  EE:  .........................................  Con domicilio en  .................................................  jurisdicción de  ...................................................  notifica a la Institución de origen que el alumno/a  ........................................................  DNI......................................................  ha sido matriculado en el presente establecimiento.',

            0,

            'J',

            false,

            1,

        );

        $this->Ln(20);

        $this->SetFont(self::FUENTE, '', 7);

        $this->MultiCell(

            self::ANCHO_UTIL,

            5,

            'Sello del Establecimiento                                                     Firmas de las autoridades del establecimiento educativo',

            0,

            'C',

            false,

            1,

        );

    }



    private function escapeHtml(string $texto): string

    {

        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    }

}

