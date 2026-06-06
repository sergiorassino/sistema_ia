<?php



namespace App\Support\MatrizAnaliticos;



use TCPDF;



/**

 * Certificado analítico — frente (formato Legal, legacy FPDF → TCPDF).

 */

final class AnaliticoFrenteTcpdf extends TCPDF

{

    use AnaliticoTcpdfGrilla;



    private const MARGEN_IZQ = 20.0;



    private const MARGEN_DER = 15.0;



    private const MARGEN_SUP = 10.0;



    private const ANCHO_UTIL = 175.0;

    /** Ancho de la grilla de calificaciones (mm), borde derecho = MARGEN_IZQ + ANCHO_TABLA. */
    private const ANCHO_TABLA = 190.0;

    private const FUENTE = 'dejavusans';

    private const ALTURA_LINEA_ENC = 4.0;



    /** @var array<string, mixed> */

    private array $datos;



    /**

     * @param  array<string, mixed>  $datos

     */

    private function __construct(array $datos)

    {

        parent::__construct('P', 'mm', 'LEGAL', true, 'UTF-8', false);

        $this->datos = $datos;

        $this->SetCreator('Sistema Escolar');

        $this->SetAuthor('Sistema Escolar');

        $this->SetTitle('Certificado analítico — frente');

        $this->setPrintHeader(false);

        $this->setPrintFooter(false);

        $this->SetAutoPageBreak(true, 12);

        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);

        $this->grillaConfigurarFill();

    }



    /**

     * @param  array<string, mixed>  $datos

     */

    public static function generar(array $datos): self

    {

        $pdf = new self($datos);

        $pdf->AddPage();

        $pdf->dibujarIdentificacionSuperior();

        $pdf->dibujarEncabezadoInstitucional();

        $pdf->dibujarTextoCertificacion();



        foreach ($datos['anios'] ?? [] as $bloque) {

            if (! is_array($bloque)) {

                continue;

            }

            $pdf->grillaDibujarBloqueAnio(

                self::MARGEN_IZQ,

                self::ANCHO_UTIL,

                (string) ($bloque['titulo'] ?? ''),

                is_array($bloque['filas'] ?? null) ? $bloque['filas'] : [],

            );

            $pdf->Ln(2);

        }



        return $pdf;

    }



    private function dibujarIdentificacionSuperior(): void

    {

        $id = $this->datos['identificacion'] ?? [];

        $serie = trim((string) ($id['serie'] ?? ''));

        $numero = trim((string) ($id['numero'] ?? ''));

        $folio = trim((string) ($id['analLibroFolio'] ?? ''));

        $linea = implode('-', array_filter([$serie, $numero, $folio], fn ($p) => $p !== ''));



        $this->SetY(10);

        $this->SetFont(self::FUENTE, '', 6);

        $this->Cell(self::ANCHO_UTIL, 4, $linea, 0, 1, 'R');



        $logo = $this->datos['institucion']['logo_abs'] ?? null;

        if (is_string($logo) && $logo !== '' && is_file($logo)) {

            $this->Image($logo, 100, 10, 12, 15, '', '', '', false, 300);

        }



        $this->SetY(26);

        $this->SetFont(self::FUENTE, '', 9);

        foreach ([

            'REPÚBLICA ARGENTINA',

            'LEY DE EDUCACIÓN NACIONAL Nº 26.206',

            'PROVINCIA DE CÓRDOBA',

            'LEY PROVINCIAL DE EDUCACIÓN Nº 9870',

            'MINISTERIO DE EDUCACIÓN DE LA PROVINCIA DE CÓRDOBA',

            'DIRECCIÓN GENERAL DE EDUCACIÓN PÚBLICA DE GESTIÓN PRIVADA',

        ] as $lineaLegal) {

            $this->Cell(self::ANCHO_UTIL, 4, $lineaLegal, 0, 1, 'C');

        }

    }



    private function dibujarEncabezadoInstitucional(): void
    {
        $inst = $this->datos['institucion'] ?? [];
        $leg = $this->datos['legajo'] ?? [];

        $insti = trim((string) ($inst['insti'] ?? ''));
        $cue = trim((string) ($inst['cue'] ?? ''));
        $cueTxt = $cue !== '' ? 'C.U.E. Nº '.$cue : '';
        $lineaInsti = trim($insti.'      '.$cueTxt);

        $partesDir = array_filter([
            trim((string) ($inst['direccion'] ?? '')),
            trim((string) ($inst['localidad'] ?? '')),
        ]);
        $depto = trim((string) ($inst['departamento'] ?? ''));
        $prov = trim((string) ($inst['provincia'] ?? ''));
        $dirLinea = 'ubicado en     '.implode(', ', $partesDir);
        if ($depto !== '') {
            $dirLinea .= ($partesDir !== [] ? ', ' : '').'Departamento '.$depto;
        }
        if ($prov !== '') {
            $dirLinea .= ($dirLinea !== 'ubicado en     ' ? ', ' : '').$prov;
        }

        $apellido = trim((string) ($leg['apellido'] ?? ''));
        $nombre = trim((string) ($leg['nombre'] ?? ''));
        $lnCiudad = trim((string) ($leg['ln_ciudad'] ?? ''));
        $lnProv = trim((string) ($leg['ln_provincia'] ?? ''));
        $dia = trim((string) ($leg['dia'] ?? ''));
        $mes = trim((string) ($leg['mes'] ?? ''));
        $anioNac = trim((string) ($leg['anio'] ?? ''));
        $dni = trim((string) ($leg['dni'] ?? ''));

        $bordeDer = self::MARGEN_IZQ + self::ANCHO_TABLA;
        $h = self::ALTURA_LINEA_ENC;
        $labelInsti = 'La autoridad del Establecimiento Educativo';
        $sepInstiMm = 5.0;

        $this->Ln(1);
        $this->SetFont(self::FUENTE, '', 8);

        $y = $this->GetY();
        $anchoLabelInsti = $this->GetStringWidth($labelInsti);
        $xCampoInsti = self::MARGEN_IZQ + $anchoLabelInsti + $sepInstiMm;
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell($anchoLabelInsti, $h, $labelInsti, 0, 0, 'L');
        $this->SetXY($xCampoInsti, $y);
        $this->Cell($bordeDer - $xCampoInsti, $h, $lineaInsti, 0, 1, 'L');
        $this->subrayadoEncabezado($xCampoInsti, $bordeDer, $y);

        $y = $this->GetY();
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(80, $h, $dirLinea, 0, 1, 'L');
        $this->subrayadoEncabezado(40, $bordeDer, $y);

        $y = $this->GetY();
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(90, $h, 'CERTIFICA QUE              '.$apellido.', '.$nombre, 0, 1, 'L');
        $this->subrayadoEncabezado(45, $bordeDer, $y);

        $y = $this->GetY();
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell(115, $h, 'nacido en       '.$lnCiudad.'  ('.$lnProv.')', 0, 0, 'L');
        $this->subrayadoEncabezado(35, 140, $y);
        $this->SetXY(140, $y);
        $this->Cell(12, $h, '    el día', 0, 0, 'L');
        $this->textoCentradoEnRango(152, 162, $y, $h, $dia);
        $this->subrayadoEncabezado(152, 162, $y);
        $this->SetXY(162, $y);
        $this->Cell(8, $h, '    de', 0, 0, 'L');
        $this->textoCentradoEnRango(170, $bordeDer, $y, $h, $mes);
        $this->subrayadoEncabezado(170, $bordeDer, $y);
        $this->Ln($h);

        $y = $this->GetY();
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell(12, $h, 'del año', 0, 0, 'L');
        $this->textoCentradoEnRango(32, 64, $y, $h, $anioNac);
        $this->subrayadoEncabezado(32, 64, $y);
        $this->SetXY(64, $y);
        $this->Cell(46, $h, ' . Tipo de Documento D.N.I. Nº ', 0, 0, 'L');
        $this->textoCentradoEnRango(110, 160, $y, $h, $dni);
        $this->subrayadoEncabezado(110, 160, $y);
        $this->SetXY(160, $y);
        $this->Cell($bordeDer - 160, $h, 'acreditó los espacios curriculares', 0, 1, 'R');
        $this->SetY($y + $h);
    }

    private function dibujarTextoCertificacion(): void
    {
        $this->SetX(self::MARGEN_IZQ);
        $this->SetFont(self::FUENTE, '', 8);
        $this->MultiCell(
            self::ANCHO_TABLA,
            self::ALTURA_LINEA_ENC,
            'correspondientes a la Educación Secundaria Obligatoria, con sus respectivas calificaciones que a continuación se expresan:',
            0,
            'L',
        );
    }

    private function textoCentradoEnRango(float $x1, float $x2, float $y, float $h, string $texto): void
    {
        if ($texto === '') {
            return;
        }
        $this->SetFont(self::FUENTE, '', 8);
        $this->SetXY($x1, $y);
        $this->Cell($x2 - $x1, $h, $texto, 0, 0, 'C');
    }

    private function subrayadoEncabezado(float $x1, float $x2, float $y): void
    {
        $this->Line($x1, $y + 3.5, $x2, $y + 3.5);
    }
}

