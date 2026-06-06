<?php



namespace App\Support\Mora;



use App\Support\Pdf\TcpdfFuenteArial;

use Illuminate\Support\Facades\Storage;

use TCPDF;



/**

 * PDF «Notificación de deuda» — una página por familia (maquetación legacy FPDF).

 */

final class NotificacionDeudaTcpdf extends TCPDF

{

    private const MARGEN_IZQ = 20.0;



    private const ANCHO_BLOQUE = 180.0;



    private const MARGEN_DER = 10.0;



    private const ALTO_ENC_INST = 22.0;



    private const ALTO_FILA = 5.0;

    /** Texto de carta y párrafos (no grilla). */
    private const FUENTE_TEXTO = 9;

    private const ALTO_LINEA_TEXTO = 5.5;

    private const Y_MAX = 270.0;



    /** @var array<int, float> Suma = 180 mm */

    private const ANCHOS = [

        30.0,  // Estudiante

        10.0,  // D.N.I.

        19.0,  // Sala/Grado/Curso

        19.0,  // Cuota

        6.0,   // Año

        8.0,   // Beca

        12.0,  // 1º Venc

        12.0,  // Importe

        10.0,  // Bonif.

        10.0,  // Inter.

        10.0,  // Pagado

        12.0,  // Saldo

        10.0,  // Intereses

        12.0,  // A pagar

    ];



    private const ETIQUETAS = [

        'Estudiante',

        'D.N.I.',

        'Sala/Grado/Curso',

        'Cuota',

        'Año',

        'Beca',

        '1º Venc',

        'Importe',

        'Bonif.',

        'Inter.',

        'Pagado',

        'Saldo',

        'Intereses',

        'A pagar',

    ];



    /** @var array<string, mixed> */

    private array $datos;



    private float $yActual = 0.0;



    /**

     * @param  array<string, mixed>  $datos

     */

    private function __construct(array $datos)

    {

        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->datos = $datos;

        $this->SetCreator('Sistema Escolar');

        $this->SetAuthor('Sistema Escolar');

        $this->SetTitle('Notificación de deuda');

        $this->setPrintHeader(false);

        $this->setPrintFooter(false);

        $this->SetAutoPageBreak(false);

        $this->SetMargins(self::MARGEN_IZQ, 10.0, self::MARGEN_DER);

        $this->SetDrawColor(0, 0, 0);

        $this->SetFillColor(255, 255, 255);

    }



    /**

     * @param  array<string, mixed>  $datos

     */

    public static function generar(array $datos): self

    {

        $pdf = new self($datos);



        /** @var list<array<string, mixed>> $paginas */

        $paginas = $datos['paginas'] ?? [];

        foreach ($paginas as $pagina) {

            $pdf->renderPaginaFamilia((array) $pagina);

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



    /**

     * @param  array<string, mixed>  $pagina

     */

    private function renderPaginaFamilia(array $pagina): void

    {

        $this->AddPage('P', 'A4');

        $this->yActual = $this->dibujarEncabezadoInstitucional(10.0);

        $this->yActual += 1.0;

        $this->dibujarCarta($pagina);

        $this->yActual += 2.0;

        $this->dibujarTituloFamilia((string) ($pagina['tituloFamilia'] ?? ''));

        $this->dibujarEncabezadoColumnas();



        foreach ((array) ($pagina['filas'] ?? []) as $fila) {

            $this->asegurarEspacioTabla(self::ALTO_FILA);

            $this->dibujarFila((array) $fila);

        }



        $this->asegurarEspacioTabla(self::ALTO_FILA);

        $this->dibujarTotales((array) ($pagina['totales'] ?? []));

        $this->yActual += 3.0;

        $this->dibujarTextoFinal($pagina);

        $this->dibujarFirma();

    }



    private function asegurarEspacioTabla(float $alto): void

    {

        if ($this->yActual + $alto > self::Y_MAX) {

            $this->AddPage('P', 'A4');

            $this->yActual = $this->dibujarEncabezadoInstitucional(10.0) + 2.0;

            $this->dibujarEncabezadoColumnas();

        }

    }



    private function dibujarEncabezadoInstitucional(float $y): float

    {

        $header = (array) ($this->datos['pdfHeader'] ?? []);

        $insti = trim((string) ($header['insti'] ?? config('tenant.nombre', '')));



        $this->Rect(self::MARGEN_IZQ, $y, self::ANCHO_BLOQUE, self::ALTO_ENC_INST);



        $logo = $this->resolverLogoArchivo($header);

        if ($logo !== null) {

            $this->Image($logo, self::MARGEN_IZQ + 2, $y + 2, 16, 16, '', '', '', false, 300);

        }



        $this->SetXY(self::MARGEN_IZQ, $y + 2);

        TcpdfFuenteArial::aplicar($this, 'B', 10);

        $this->Cell(self::ANCHO_BLOQUE, 7, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');



        TcpdfFuenteArial::aplicar($this, '', 8);

        $this->Cell(self::ANCHO_BLOQUE, 5, 'NOTIFICACIÓN DE DEUDA', 0, 2, 'C');



        return $y + self::ALTO_ENC_INST;

    }



    /**

     * @param  array<string, mixed>  $pagina

     */

    private function dibujarCarta(array $pagina): void

    {

        $localidad = trim((string) ($this->datos['localidad'] ?? ''));

        $fechaCarta = (string) ($this->datos['fechaCarta'] ?? '');

        $lineaLugar = $localidad !== '' ? $localidad.',  '.$fechaCarta : $fechaCarta;



        $this->SetXY(self::MARGEN_IZQ, $this->yActual);

        TcpdfFuenteArial::aplicar($this, '', self::FUENTE_TEXTO);

        $this->Cell(170, self::ALTO_LINEA_TEXTO, $lineaLugar, 0, 2, 'R');

        $destinatario = 'Sr/Sra/Srta: '.trim((string) ($pagina['familiaLinea'] ?? ''));

        $this->Cell(self::ANCHO_BLOQUE, self::ALTO_LINEA_TEXTO, $destinatario, 0, 1, 'L');

        $this->yActual = $this->GetY() + 2.0;



        $textoInicial = trim((string) ($this->datos['textoInicial'] ?? ''));

        if ($textoInicial !== '') {
            $this->SetXY(self::MARGEN_IZQ, $this->yActual);
            TcpdfFuenteArial::aplicar($this, '', self::FUENTE_TEXTO);
            $this->MultiCell(self::ANCHO_BLOQUE, self::ALTO_LINEA_TEXTO, $textoInicial, 0, 'L');
            $this->yActual = $this->GetY();
        }

    }



    private function dibujarTituloFamilia(string $titulo): void

    {

        $this->SetXY(self::MARGEN_IZQ, $this->yActual);

        TcpdfFuenteArial::aplicar($this, 'B', 7);

        $this->Cell(self::ANCHO_BLOQUE, 5, $titulo !== '' ? $titulo : 'Familia / Responsable: —', 1, 1, 'L', true);

        $this->yActual += 5.0;

    }



    private function dibujarEncabezadoColumnas(): void

    {

        $this->SetXY(self::MARGEN_IZQ, $this->yActual);

        TcpdfFuenteArial::aplicar($this, '', 5);



        foreach (self::ETIQUETAS as $i => $texto) {

            $align = in_array($i, [7, 8, 9, 10, 11, 12, 13], true) ? 'R' : 'C';

            $this->Cell(self::ANCHOS[$i], self::ALTO_FILA, $texto, 1, $i === count(self::ETIQUETAS) - 1 ? 1 : 0, $align, true);

        }



        $this->yActual += self::ALTO_FILA;

    }



    /**

     * @param  array<string, string>  $fila

     */

    private function dibujarFila(array $fila): void

    {

        $this->SetXY(self::MARGEN_IZQ, $this->yActual);



        $valores = [

            (string) ($fila['estudiante'] ?? ''),

            (string) ($fila['dni'] ?? ''),

            (string) ($fila['curso'] ?? ''),

            (string) ($fila['cuota'] ?? ''),

            (string) ($fila['ano'] ?? ''),

            (string) ($fila['beca'] ?? ''),

            (string) ($fila['venc1'] ?? ''),

            (string) ($fila['importe'] ?? ''),

            (string) ($fila['bonificacion'] ?? ''),

            (string) ($fila['interes'] ?? ''),

            (string) ($fila['pagado'] ?? ''),

            (string) ($fila['saldo'] ?? ''),

            (string) ($fila['intereses'] ?? ''),

            (string) ($fila['aPagar'] ?? ''),

        ];



        foreach ($valores as $i => $texto) {

            if ($i === 5) {

                TcpdfFuenteArial::aplicar($this, '', 4);

            } else {

                TcpdfFuenteArial::aplicar($this, '', 6);

            }



            $align = match ($i) {

                0, 2, 3 => 'L',

                7, 8, 9, 10, 11, 12, 13 => 'R',

                default => 'C',

            };

            $this->Cell(self::ANCHOS[$i], self::ALTO_FILA, $texto, 1, $i === count($valores) - 1 ? 1 : 0, $align, true);

        }



        $this->yActual += self::ALTO_FILA;

    }



    /**

     * @param  array<string, string>  $totales

     */

    private function dibujarTotales(array $totales): void

    {

        $this->SetXY(self::MARGEN_IZQ, $this->yActual);

        TcpdfFuenteArial::aplicar($this, 'B', 6);



        $anchoEtiqueta = array_sum(array_slice(self::ANCHOS, 0, 7));

        $this->Cell($anchoEtiqueta, self::ALTO_FILA, 'Totales', 1, 0, 'R', true);



        $cols = ['importe', 'bonificacion', 'interes', 'pagado', 'saldo', 'intereses', 'aPagar'];

        foreach ($cols as $idx => $key) {

            $i = 7 + $idx;

            $this->Cell(self::ANCHOS[$i], self::ALTO_FILA, (string) ($totales[$key] ?? '0,00'), 1, $idx === count($cols) - 1 ? 1 : 0, 'R', true);

        }



        $this->yActual += self::ALTO_FILA;

    }



    /**

     * @param  array<string, mixed>  $pagina

     */

    private function dibujarTextoFinal(array $pagina): void

    {

        $usarBec = (bool) ($pagina['usarTextoFinalBec'] ?? false);

        $texto = $usarBec

            ? trim((string) ($this->datos['textoFinalBec'] ?? ''))

            : trim((string) ($this->datos['textoFinal'] ?? ''));



        if ($texto === '') {

            return;

        }



        $this->SetXY(self::MARGEN_IZQ, $this->yActual);
        TcpdfFuenteArial::aplicar($this, '', self::FUENTE_TEXTO);
        $this->MultiCell(self::ANCHO_BLOQUE, self::ALTO_LINEA_TEXTO, $texto, 0, 'L');
        $this->yActual = $this->GetY();
    }



    private function dibujarFirma(): void

    {

        $nombre = trim((string) config('tenant.mora.notificacion_deuda.firma_nombre', ''));

        $cargo = trim((string) config('tenant.mora.notificacion_deuda.firma_cargo', 'Representante Legal'));

        $imagenRel = config('tenant.mora.notificacion_deuda.firma_imagen');

        $imagen = null;

        if (is_string($imagenRel) && $imagenRel !== '') {

            $abs = public_path($imagenRel);

            if (is_file($abs)) {

                $imagen = $abs;

            }

        }



        $y = $this->yActual;

        if ($imagen !== null) {

            $this->Image($imagen, 120.0, $y, 35.0, 20.0, '', '', '', false, 300);

            $y += 20.0;

        } else {

            $y += 12.0;

        }



        if ($nombre === '' && $cargo === '') {

            return;

        }



        $this->SetLeftMargin(110.0);

        $this->SetXY(110.0, $y);

        if ($nombre !== '') {

            TcpdfFuenteArial::aplicar($this, '', 6);

            $this->Cell(50, 3, $nombre, 0, 2, 'C', true);

        }

        if ($cargo !== '') {

            TcpdfFuenteArial::aplicar($this, '', 5);

            $this->Cell(50, 3, $cargo, 0, 2, 'C', true);

        }

        $this->SetLeftMargin(self::MARGEN_IZQ);

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

        if (is_file($fallback)) {

            return $fallback;

        }



        return null;

    }

}

