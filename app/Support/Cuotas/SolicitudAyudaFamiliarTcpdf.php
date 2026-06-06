<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Solicitud de Ayuda Familiar (A4 vertical, formato legacy FPDF → TCPDF).
 */
final class SolicitudAyudaFamiliarTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 12.0;

    private const MARGEN_DER = 12.0;

    private const MARGEN_SUP = 10.0;

    private const ANCHO_UTIL = 186.0;

    private const ANCHO_CONTENIDO = 177.0;

    private const LOGO_ANCHO = 17.0;

    private const LOGO_ALTO = 17.0;

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
        $this->SetTitle('Solicitud de Ayuda Familiar');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 15);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetDrawColor(0, 0, 0);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarDocumento();

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

    private function dibujarDocumento(): void
    {
        $header = (array) ($this->datos['pdfHeader'] ?? []);
        $y = $this->dibujarHeaderInstitucional(self::MARGEN_SUP, $header);
        $this->dibujarTituloDocumento($y + 3);

        $nro = (int) ($this->datos['nroSolicitud'] ?? 0);
        $fecha = trim((string) ($this->datos['fechaEmision'] ?? ''));
        $apenom = trim((string) ($this->datos['apenom'] ?? ''));
        $anoLectivo = trim((string) ($this->datos['anoLectivo'] ?? ''));

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(
            self::ANCHO_CONTENIDO,
            5,
            'Fecha de emisión de la Solicitud:  '.$fecha.'                                                                        Número de Solicitud: '.$nro,
            0,
            2,
            'L',
        );

        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->MultiCell(
            self::ANCHO_CONTENIDO,
            5,
            'Recordamos que este apoyo económico es para Familias en Situación Económica Adversa con deseos de superación permitiendo así continuar con su proyecto educativo y la realización de metas personales del estudiante y su familia. Por este motivo, se tendrá especialmente en cuenta al momento de otorgar y mantener este estímulo, el compromiso de los padres y de los hijos en el valor de la educación, demostrándolo con su rendimiento y conducta',
            0,
            'L',
        );

        $this->Ln(4);
        $this->Cell(
            self::ANCHO_CONTENIDO,
            5,
            '                                                                                                  Córdoba, ......... de .............................................. de. ....................',
            0,
            2,
            'L',
        );

        $this->Ln(4);
        $textoAlumno = 'El que suscribe, ..................................................................................... , solicita a las autoridades del Colegio, sea estudiada la posibilidad del otorgamiento de una Ayuda Familiar, para el/la alumno/a '
            .$apenom
            .' (hermano/a mayor de la Institución), Matrícula Nº ................. que cursa el ........... grado/año (tachar lo que no corresponda), división ............. , Nivel ..................................................... durante el Ciclo Lectivo '
            .$anoLectivo;
        $this->MultiCell(self::ANCHO_CONTENIDO, 5, $textoAlumno, 0, 'L');

        $this->Ln(2);
        $this->dibujarTablaPadres();
        $this->dibujarTablaHermanos();
        $this->dibujarCierre();
        $this->dibujarTalon();
    }

    private function dibujarTituloDocumento(float $y): void
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_UTIL, 5, 'SOLICITUD DE AYUDA FAMILIAR', 0, 2, 'C');
        $this->Ln(4);
    }

    /**
     * Encabezado institucional estándar (mismo criterio que comprobantes / resumen de pagos).
     *
     * @param  array<string, mixed>  $header
     */
    private function dibujarHeaderInstitucional(float $y, array $header): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;
        $yTexto = $y + 3;

        $this->SetXY($x, $yTexto);

        $insti = trim((string) ($header['insti'] ?? ''));
        $direccion = trim((string) ($header['direccion'] ?? ''));
        $localidad = trim((string) ($header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($header['cue'] ?? ''));
        $ee = trim((string) ($header['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell($w, 4.5, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        if ($lineaDir !== '') {
            TcpdfFuenteArial::aplicar($this, '', 7);
            $this->Cell($w, 3.5, $lineaDir, 0, 2, 'C');
        }

        if ($lineaIds !== '') {
            TcpdfFuenteArial::aplicar($this, '', 5.5);
            $this->Cell($w, 3, $lineaIds, 0, 2, 'C');
        }

        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->Cell($w, 3.5, 'IVA: Exento - Ing. Brutos: Exento', 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, '', 5);
        $this->MultiCell(
            $w,
            3.2,
            'Entidad Exenta al cumplimiento de la RG (AFIP) 1415 y modificatorias, por aplicación del artículo 5 y del Anexo 1, apartado K de la citada norma',
            0,
            'C',
        );

        $yFin = $this->GetY();
        $h = max(self::LOGO_ALTO + 4, $yFin - $y + 2);

        $this->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        $logo = $this->resolverLogoArchivo($header);
        if ($logo !== null) {
            $this->Image(
                $logo,
                $x + 3,
                $y + (($h - self::LOGO_ALTO) / 2),
                self::LOGO_ANCHO,
                self::LOGO_ALTO,
                '',
                '',
                '',
                false,
                300,
            );
        }

        return $y + $h;
    }

    private function dibujarTablaPadres(): void
    {
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(37, 8, 'DATOS', 1, 0, 'C');
        $this->Cell(70, 8, 'PADRE', 1, 0, 'C');
        $this->Cell(70, 8, 'MADRE', 1, 1, 'C');

        TcpdfFuenteArial::aplicar($this, '', 7);
        $filas = [
            ['Apellidos', 6],
            ['Nombres', 5],
            ['D.N.I. Nº', 5],
            ['Domicilio (Calle, Nº, Barrio', 5],
            ['Teléfonos', 5],
            ['Ocupación principal', 5],
            ['Lugar de Trabajo', 5],
            ['Otros trabajos', 5],
        ];

        foreach ($filas as [$etiqueta, $alto]) {
            $this->Cell(37, $alto, $etiqueta, 1, 0, 'L');
            $this->Cell(70, $alto, '', 1, 0, 'C');
            $this->Cell(70, $alto, '', 1, 1, 'C');
        }

        $yArriba = $this->GetY();
        $this->MultiCell(37, 6, 'Ingresos mensuales (s/recibo o aproximados)', 1, 'L');
        $this->SetXY(self::MARGEN_IZQ + 37, $yArriba);
        $this->Cell(70, 6.37, '', 1, 0, 'C');
        $this->Cell(70, 6.37, '', 1, 1, 'C');
    }

    private function dibujarTablaHermanos(): void
    {
        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->MultiCell(
            self::ANCHO_CONTENIDO,
            5,
            'Consignar el Nombre, Número de Matrícula, nivel, grado/curso y división de cada uno de los hermanos del beneficiario que asiste a este Colegio (de mayor a menor)',
            0,
            'L',
        );

        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(7, 6, '', 1, 0, 'C');
        $this->Cell(75, 6, 'NOMBRE', 1, 0, 'C');
        $this->Cell(30, 6, 'NIVEL (Primario/Medio', 1, 0, 'C');
        $this->Cell(30, 6, 'CURSO / GRADO', 1, 0, 'C');
        $this->Cell(15, 6, 'DIV.', 1, 0, 'C');
        $this->Cell(20, 6, 'Nº DE MATRÍC.', 1, 1, 'C');

        for ($i = 1; $i <= 5; $i++) {
            $this->Cell(7, 5, (string) $i, 1, 0, 'C');
            $this->Cell(75, 5, '', 1, 0, 'C');
            $this->Cell(30, 5, '', 1, 0, 'C');
            $this->Cell(30, 5, '', 1, 0, 'C');
            $this->Cell(15, 5, '', 1, 0, 'C');
            $this->Cell(20, 5, '', 1, 1, 'C');
        }
    }

    private function dibujarCierre(): void
    {
        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(75, 4, 'Otros integrantes del grupo familiar conviviente a cargo: cantidad y vínculo:', 0, 1, 'L');
        $this->Cell(175, 4, '........................................................................................................................................................................................................', 0, 1, 'L');
        $this->Cell(175, 4, '........................................................................................................................................................................................................', 0, 1, 'L');

        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, 'I', 10);
        $this->MultiCell(
            175,
            6,
            'RAZONES DE LA SOLICITUD (Presentar nota explicando los motivos de la solicitud, en conjunto con la documentacion solicitada)',
            0,
            'L',
        );

        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(175, 5, 'DOCUMENTACION A ADJUNTAR: (detallada en mail)', 0, 1, 'L');

        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->MultiCell(
            175,
            5,
            'CABE ACLARAR QUE LA PRESENTE TIENE CARÁCTER DE DECLARACIÓN JURADA, POR TANTO LOS DATOS DEBEN SER CIERTOS Y COMPROBABLES.',
            0,
            'L',
        );

        $this->Ln(15);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(175, 3, '                  .........................................................................                                                    ................................................................', 0, 1, 'L');
        $this->Cell(175, 3, '                          Firma y Aclaración del Responsable                                                                    Firma y fecha de recepción', 0, 1, 'L');
    }

    private function dibujarTalon(): void
    {
        $nro = (int) ($this->datos['nroSolicitud'] ?? 0);
        $apenom = trim((string) ($this->datos['apenom'] ?? ''));

        $this->Cell(
            175,
            5,
            '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ',
            0,
            1,
            'L',
        );

        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(50, 5, 'CONSTANCIA DE RECEPCIÓN', 1, 1, 'C');
        $this->Ln(1);
        $this->Cell(50, 5, 'Solicitud de Ayuda Familiar Nro: '.$nro, 0, 1, 'L');
        $this->Cell(50, 5, $apenom, 0, 1, 'L');

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(175, 3, '                                                                                                                                                 ................................................................', 0, 1, 'L');
        $this->Cell(175, 3, '                                                                                                                                                                 Firma y fecha de recepción', 0, 1, 'L');
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function resolverLogoArchivo(array $header): ?string
    {
        $logo = $header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            return $logo;
        }

        $path = entoInstitutionalLogoStoragePath();
        if (is_string($path) && $path !== '') {
            $abs = Storage::disk('public')->path($path);
            if (is_string($abs) && $abs !== '' && is_file($abs)) {
                return $abs;
            }
        }

        $fallback = public_path('img/3.png');

        return is_file($fallback) ? $fallback : null;
    }
}
