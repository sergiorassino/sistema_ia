<?php

namespace App\Support\Arca;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * Utilidades de maquetación compartidas para guías ARCA (paleta SE, A4 vertical).
 */
trait ArcaGuiaTcpdfLayout
{
    /** #40848D — primario SE */
    private const GUIA_COLOR_PRIMARIO = [64, 132, 141];

    /** #739FA5 — moonstone */
    private const GUIA_COLOR_SECUNDARIO = [115, 159, 165];

    /** #333333 — jet */
    private const GUIA_COLOR_TEXTO = [51, 51, 51];

    /** #F4F8F9 — fondo suave */
    private const GUIA_COLOR_CAJA = [244, 248, 249];

    /** #C1D7DA — light blue */
    private const GUIA_COLOR_CALLOUT = [193, 215, 218];

    private const GUIA_MARGEN_IZQ = 14.0;

    private const GUIA_MARGEN_DER = 14.0;

    /** Margen superior con header de marca (logo + nombre). */
    private const GUIA_MARGEN_SUP = 22.0;

    private const GUIA_MARGEN_INF = 12.0;

    private const GUIA_HEADER_LOGO = 10.0;

    /** @var array{titulo:string,subtitulo:string,version:string,generado:string,colegio:?string} */
    private array $guiaMeta = [];

    /** @var array<string, int> */
    private array $guiaSectionLinks = [];

    /**
     * Header TCPDF: logo + nombre de Sistemas Escolares (todas las páginas salvo portada).
     */
    public function Header(): void
    {
        if ($this->getPage() === 1) {
            return;
        }

        $x = self::GUIA_MARGEN_IZQ;
        $y = 5.5;
        $logoMm = self::GUIA_HEADER_LOGO;
        $logoPath = $this->guiaLogoSistemaPath();

        if ($logoPath !== null) {
            $this->SetFillColor(255, 255, 255);
            $this->RoundedRect($x, $y - 0.5, $logoMm + 1.5, $logoMm + 1.5, 1.5, '1111', 'F');
            TcpdfLogoInstitucional::dibujarAjustado($this, $x + 0.75, $y + 0.25, $logoMm, $logoMm, $logoPath);
        }

        $nombre = $this->guiaNombreSistema();
        $this->SetXY($x + $logoMm + 4.5, $y + 2.2);
        $this->SetTextColor(...self::GUIA_COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(0, 5, $nombre, 0, 1, 'L');

        $w = $this->getPageWidth();
        $this->SetDrawColor(...self::GUIA_COLOR_CALLOUT);
        $this->SetLineWidth(0.35);
        $lineY = $y + $logoMm + 3.0;
        $this->Line(self::GUIA_MARGEN_IZQ, $lineY, $w - self::GUIA_MARGEN_DER, $lineY);
    }

    /**
     * @param  array{titulo:string,subtitulo:string,version:string,generado:string,colegio:?string}  $meta
     */
    protected function guiaInicializar(TCPDF $pdf, array $meta): void
    {
        $this->guiaMeta = $meta;
        $nombre = $this->guiaNombreSistema();
        $pdf->SetCreator($nombre);
        $pdf->SetAuthor($nombre);
        $pdf->SetTitle($meta['titulo']);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);
        $pdf->SetHeaderMargin(0);
        $pdf->SetAutoPageBreak(true, self::GUIA_MARGEN_INF);
        $pdf->SetMargins(self::GUIA_MARGEN_IZQ, self::GUIA_MARGEN_SUP, self::GUIA_MARGEN_DER);
    }

    /**
     * @param  list<string>  $keys
     */
    protected function guiaCrearLinks(TCPDF $pdf, array $keys): void
    {
        $this->guiaSectionLinks = [];
        foreach ($keys as $key) {
            $this->guiaSectionLinks[$key] = $pdf->AddLink();
        }
    }

    protected function guiaRenderPortada(TCPDF $pdf, ?string $etiquetaExtra = null): void
    {
        $w = $pdf->getPageWidth();
        $h = $pdf->getPageHeight();
        $nombreSistema = $this->guiaNombreSistema();
        $logoPath = $this->guiaLogoSistemaPath();
        $logoMm = 14.0;

        $pdf->SetFillColor(...self::GUIA_COLOR_PRIMARIO);
        $pdf->Rect(0, 0, $w, 68, 'F');

        $pdf->SetFillColor(...self::GUIA_COLOR_SECUNDARIO);
        $pdf->Rect(0, 64, $w, 6, 'F');

        // Marca Sistemas Escolares (logo + nombre) en la franja de portada.
        $marcaY = 10.0;
        if ($logoPath !== null) {
            $pdf->SetFillColor(255, 255, 255);
            $pdf->RoundedRect(self::GUIA_MARGEN_IZQ, $marcaY, $logoMm + 2, $logoMm + 2, 2.0, '1111', 'F');
            TcpdfLogoInstitucional::dibujarAjustado(
                $pdf,
                self::GUIA_MARGEN_IZQ + 1,
                $marcaY + 1,
                $logoMm,
                $logoMm,
                $logoPath,
            );
            $pdf->SetXY(self::GUIA_MARGEN_IZQ + $logoMm + 5, $marcaY + 4.5);
        } else {
            $pdf->SetXY(self::GUIA_MARGEN_IZQ, $marcaY + 4.5);
        }
        $pdf->SetTextColor(255, 255, 255);
        TcpdfFuenteArial::aplicar($pdf, 'B', 11);
        $pdf->Cell(0, 6, $nombreSistema, 0, 1, 'L');

        $pdf->SetXY(self::GUIA_MARGEN_IZQ, $marcaY + $logoMm + 6);
        TcpdfFuenteArial::aplicar($pdf, 'B', 18);
        $pdf->MultiCell($w - self::GUIA_MARGEN_IZQ - self::GUIA_MARGEN_DER, 8.5, $this->guiaMeta['titulo'], 0, 'L', false, 1);

        TcpdfFuenteArial::aplicar($pdf, '', 10.5);
        $pdf->MultiCell($w - self::GUIA_MARGEN_IZQ - self::GUIA_MARGEN_DER, 5.5, $this->guiaMeta['subtitulo'], 0, 'L', false, 1);

        $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
        $pdf->SetFillColor(...self::GUIA_COLOR_CAJA);
        $pdf->RoundedRect(self::GUIA_MARGEN_IZQ, 88, $w - self::GUIA_MARGEN_IZQ - self::GUIA_MARGEN_DER, 48, 3.0, '1111', 'F');
        $pdf->SetXY(self::GUIA_MARGEN_IZQ + 6, 96);

        TcpdfFuenteArial::aplicar($pdf, 'B', 10.5);
        $pdf->Cell(0, 6, 'Datos del documento', 0, 1, 'L');
        TcpdfFuenteArial::aplicar($pdf, '', 9.5);

        if ($this->guiaMeta['colegio'] !== null) {
            $this->guiaLineaMeta($pdf, 'Institución', $this->guiaMeta['colegio']);
        }
        $this->guiaLineaMeta($pdf, 'Versión', $this->guiaMeta['version']);
        $this->guiaLineaMeta($pdf, 'Generado', $this->guiaMeta['generado']);
        if ($etiquetaExtra !== null && $etiquetaExtra !== '') {
            $this->guiaLineaMeta($pdf, 'Alcance', $etiquetaExtra);
        }

        $pdf->SetY($h - 30);
        $pdf->SetTextColor(115, 159, 165);
        TcpdfFuenteArial::aplicar($pdf, '', 8.5);
        $pdf->MultiCell(
            $w - self::GUIA_MARGEN_IZQ - self::GUIA_MARGEN_DER,
            4.5,
            'Guía de configuración en ARCA para colegios sin facturación electrónica. ' .
            'Los nombres en el portal pueden variar según actualizaciones del organismo.',
            0,
            'L',
        );
    }

    /**
     * @param  list<array{0:string,1:int}>  $items  [etiqueta, linkId]
     */
    protected function guiaRenderIndice(TCPDF $pdf, array $items): void
    {
        $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($pdf, 'B', 16);
        $pdf->Cell(0, 10, 'Índice', 0, 1, 'L');

        TcpdfFuenteArial::aplicar($pdf, '', 10);
        $pdf->SetTextColor(115, 159, 165);
        $pdf->MultiCell(0, 5.5, 'Hacé clic sobre un ítem para ir a la sección (visores PDF compatibles).', 0, 'L', false, 1);
        $pdf->Ln(2);

        $pdf->SetTextColor(...self::GUIA_COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($pdf, 'B', 11);

        foreach ($items as [$label, $linkId]) {
            $this->guiaTocItem($pdf, $label, $linkId);
        }
    }

    /**
     * Si queda poco espacio útil, pasa a la página siguiente (evita títulos huérfanos).
     * No fuerza un salto por sección: el contenido fluye de corrido.
     */
    protected function guiaAsegurarEspacioSeccion(TCPDF $pdf, float $minMm = 42.0): void
    {
        $limite = $pdf->getPageHeight() - self::GUIA_MARGEN_INF;
        if ($pdf->GetY() + $minMm > $limite) {
            $pdf->AddPage();
        }
    }

    /**
     * @param  callable():void  $contenido
     */
    protected function guiaRenderSeccion(TCPDF $pdf, string $titulo, string $keyLink, callable $contenido): void
    {
        $this->guiaAsegurarEspacioSeccion($pdf);

        if (isset($this->guiaSectionLinks[$keyLink])) {
            $pdf->SetLink($this->guiaSectionLinks[$keyLink], 0, -1);
        }

        $pdf->Bookmark($titulo, 0, 0, '', 'B', self::GUIA_COLOR_PRIMARIO);

        $y = $pdf->GetY();
        $w = $pdf->getPageWidth() - self::GUIA_MARGEN_IZQ - self::GUIA_MARGEN_DER;
        $pdf->SetFillColor(...self::GUIA_COLOR_PRIMARIO);
        $pdf->Rect(self::GUIA_MARGEN_IZQ, $y, 3, 9, 'F');

        $pdf->SetXY(self::GUIA_MARGEN_IZQ + 6, $y);
        $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($pdf, 'B', 14);
        $pdf->MultiCell($w - 6, 7.5, $titulo, 0, 'L', false, 1);
        $pdf->Ln(2);

        $contenido();

        $pdf->Ln(4);
    }

    protected function guiaLinkId(string $key): int
    {
        return $this->guiaSectionLinks[$key] ?? 0;
    }

    protected function guiaH2(TCPDF $pdf, string $text): void
    {
        $pdf->Ln(2);
        $pdf->SetTextColor(...self::GUIA_COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($pdf, 'B', 11.5);
        $pdf->MultiCell(0, 6, $text, 0, 'L', false, 1);
        $pdf->Ln(0.5);
    }

    protected function guiaP(TCPDF $pdf, string $text): void
    {
        $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($pdf, '', 10);
        $pdf->MultiCell(0, 5.6, $text, 0, 'L', false, 1);
        $pdf->Ln(1);
    }

    /**
     * @param  list<string>  $items
     */
    protected function guiaBullets(TCPDF $pdf, array $items): void
    {
        $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($pdf, '', 10);
        foreach ($items as $it) {
            $pdf->MultiCell(0, 5.4, '• '.$it, 0, 'L', false, 1);
        }
        $pdf->Ln(1);
    }

    /**
     * @param  list<string>  $items
     */
    protected function guiaNumbered(TCPDF $pdf, array $items): void
    {
        $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($pdf, '', 10);
        $n = 1;
        foreach ($items as $it) {
            $pdf->MultiCell(0, 5.4, $n.'. '.$it, 0, 'L', false, 1);
            $n++;
        }
        $pdf->Ln(1);
    }

    /**
     * @param  list<array{0:string,1:string}>  $rows
     */
    protected function guiaBox(TCPDF $pdf, string $title, array $rows): void
    {
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $w = $pdf->getPageWidth() - self::GUIA_MARGEN_IZQ - self::GUIA_MARGEN_DER;
        $altoEstimado = 8 + (count($rows) * 6.2) + 2;

        if ($pdf->GetY() + $altoEstimado > ($pdf->getPageHeight() - self::GUIA_MARGEN_INF)) {
            $pdf->AddPage();
            $x = $pdf->GetX();
            $y = $pdf->GetY();
        }

        $pdf->SetDrawColor(...self::GUIA_COLOR_CALLOUT);
        $pdf->SetFillColor(...self::GUIA_COLOR_CAJA);
        $pdf->RoundedRect($x, $y, $w, $altoEstimado, 2.5, '1111', 'DF');
        $pdf->SetXY($x + 5, $y + 4);

        $pdf->SetTextColor(...self::GUIA_COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($pdf, 'B', 10.5);
        $pdf->Cell(0, 6, $title, 0, 1, 'L');
        $pdf->Ln(0.2);

        foreach ($rows as [$k, $v]) {
            TcpdfFuenteArial::aplicar($pdf, 'B', 9.3);
            $pdf->SetTextColor(64, 132, 141);
            $pdf->MultiCell(50, 5.6, $k.':', 0, 'L', false, 0);
            TcpdfFuenteArial::aplicar($pdf, '', 9.3);
            $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
            $pdf->MultiCell($w - 50 - 8, 5.6, $v, 0, 'L', false, 1);
        }

        $pdf->SetXY($x, $y + $altoEstimado + 3);
    }

    protected function guiaCallout(TCPDF $pdf, string $title, string $text): void
    {
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $w = $pdf->getPageWidth() - self::GUIA_MARGEN_IZQ - self::GUIA_MARGEN_DER;

        if ($y + 22 > ($pdf->getPageHeight() - self::GUIA_MARGEN_INF)) {
            $pdf->AddPage();
            $x = $pdf->GetX();
            $y = $pdf->GetY();
        }

        $pdf->SetFillColor(225, 237, 239);
        $pdf->SetDrawColor(...self::GUIA_COLOR_PRIMARIO);
        $pdf->RoundedRect($x, $y, $w, 20, 2.5, '1111', 'DF');
        $pdf->SetXY($x + 5, $y + 4);

        $pdf->SetTextColor(...self::GUIA_COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($pdf, 'B', 10.2);
        $pdf->Cell(0, 5.5, $title, 0, 1, 'L');

        $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($pdf, '', 9.5);
        $pdf->MultiCell($w - 10, 5.2, $text, 0, 'L', false, 1);

        $pdf->Ln(3);
    }

    /**
     * @param  list<array{0:string,1:string}>  $rows
     */
    protected function guiaErrorTable(TCPDF $pdf, array $rows): void
    {
        $x = self::GUIA_MARGEN_IZQ;
        $w = $pdf->getPageWidth() - self::GUIA_MARGEN_IZQ - self::GUIA_MARGEN_DER;
        $col1 = 52.0;
        $col2 = $w - $col1;

        if ($pdf->GetY() + 8 + (count($rows) * 7) > ($pdf->getPageHeight() - self::GUIA_MARGEN_INF)) {
            $pdf->AddPage();
        }

        $pdf->SetFillColor(...self::GUIA_COLOR_PRIMARIO);
        $pdf->SetTextColor(255, 255, 255);
        TcpdfFuenteArial::aplicar($pdf, 'B', 9.5);
        $pdf->SetXY($x, $pdf->GetY());
        $pdf->Cell($col1, 7, 'Síntoma / mensaje', 1, 0, 'L', true);
        $pdf->Cell($col2, 7, 'Qué revisar en ARCA', 1, 1, 'L', true);

        TcpdfFuenteArial::aplicar($pdf, '', 9);
        foreach ($rows as $i => [$sintoma, $causa]) {
            $fill = $i % 2 === 0;
            $pdf->SetFillColor(...($fill ? self::GUIA_COLOR_CAJA : [255, 255, 255]));
            $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
            $yRow = $pdf->GetY();
            $pdf->SetXY($x, $yRow);
            $pdf->MultiCell($col1, 6.5, $sintoma, 1, 'L', $fill, 0);
            $hRow = max(6.5, $pdf->GetY() - $yRow);
            $pdf->SetXY($x + $col1, $yRow);
            $pdf->MultiCell($col2, 6.5, $causa, 1, 'L', $fill, 1);
            if ($pdf->GetY() - $yRow < $hRow) {
                $pdf->SetY($yRow + $hRow);
            }
        }

        $pdf->Ln(2);
    }

    /**
     * Bloque de comandos (ventana de terminal / CMD).
     *
     * @param  list<string>  $lines
     */
    protected function guiaCodeBlock(TCPDF $pdf, ?string $title, array $lines, ?string $nota = null): void
    {
        $x = self::GUIA_MARGEN_IZQ;
        $w = $pdf->getPageWidth() - self::GUIA_MARGEN_IZQ - self::GUIA_MARGEN_DER;
        $lineH = 4.6;
        $pad = 4.0;
        $innerW = $w - ($pad * 2);

        $pdf->SetFont('courier', '', 8.2);
        $heights = [];
        foreach ($lines as $line) {
            $heights[] = max($lineH, $pdf->getStringHeight($innerW, $line));
        }
        $bodyH = array_sum($heights);
        $titleH = ($title !== null && $title !== '') ? 6.5 : 0.0;
        $notaH = 0.0;
        if ($nota !== null && $nota !== '') {
            TcpdfFuenteArial::aplicar($pdf, '', 8.5);
            $notaH = $pdf->getStringHeight($innerW, $nota) + 2.0;
        }
        $boxH = $pad + $titleH + $bodyH + ($notaH > 0 ? $notaH + 2 : 0) + $pad;

        if ($pdf->GetY() + $boxH > ($pdf->getPageHeight() - self::GUIA_MARGEN_INF)) {
            $pdf->AddPage();
        }

        $y = $pdf->GetY();
        $pdf->SetDrawColor(55, 65, 81);
        $pdf->SetFillColor(30, 41, 59);
        $pdf->RoundedRect($x, $y, $w, $boxH, 2.0, '1111', 'DF');
        $pdf->SetXY($x + $pad, $y + $pad);

        if ($titleH > 0) {
            $pdf->SetTextColor(193, 215, 218);
            TcpdfFuenteArial::aplicar($pdf, 'B', 8.5);
            $pdf->Cell(0, 5.5, $title, 0, 1, 'L');
            $pdf->Ln(0.5);
        }

        $pdf->SetTextColor(226, 232, 240);
        $pdf->SetFont('courier', '', 8.2);
        foreach ($lines as $i => $line) {
            $pdf->MultiCell($innerW, $lineH, $line, 0, 'L', false, 1);
            if ($i < count($lines) - 1) {
                $pdf->Ln(0.3);
            }
        }

        if ($notaH > 0) {
            $pdf->Ln(1);
            $pdf->SetTextColor(193, 215, 218);
            TcpdfFuenteArial::aplicar($pdf, '', 8.2);
            $pdf->MultiCell($innerW, 4.2, $nota, 0, 'L', false, 1);
        }

        $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
        $pdf->SetXY($x, $y + $boxH + 3);
    }

    protected function guiaFlujoPasos(TCPDF $pdf, array $pasos): void
    {
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $w = $pdf->getPageWidth() - self::GUIA_MARGEN_IZQ - self::GUIA_MARGEN_DER;
        $alto = 6 + (count($pasos) * 9.5);

        if ($y + $alto > ($pdf->getPageHeight() - self::GUIA_MARGEN_INF)) {
            $pdf->AddPage();
            $x = $pdf->GetX();
            $y = $pdf->GetY();
        }

        $pdf->SetDrawColor(...self::GUIA_COLOR_CALLOUT);
        $pdf->SetFillColor(...self::GUIA_COLOR_CAJA);
        $pdf->RoundedRect($x, $y, $w, $alto, 2.5, '1111', 'DF');
        $pdf->SetXY($x + 5, $y + 4);

        $n = 1;
        foreach ($pasos as $paso) {
            $pdf->SetFillColor(...self::GUIA_COLOR_PRIMARIO);
            $pdf->SetTextColor(255, 255, 255);
            TcpdfFuenteArial::aplicar($pdf, 'B', 9);
            $pdf->Cell(7, 7, (string) $n, 0, 0, 'C', true);
            $pdf->SetXY($pdf->GetX() + 2, $pdf->GetY());
            $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
            TcpdfFuenteArial::aplicar($pdf, '', 9.5);
            $pdf->MultiCell($w - 18, 5.5, $paso, 0, 'L', false, 1);
            $pdf->Ln(1.5);
            $n++;
        }

        $pdf->SetXY($x, $y + $alto + 3);
    }

    public static function guiaRespuestaHttp(TCPDF $pdf, string $nombreArchivo): \Illuminate\Http\Response
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

    private function guiaTocItem(TCPDF $pdf, string $label, int $linkId): void
    {
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->Write(6.5, $label, $linkId, false, 'L', true);
        $pdf->Link($x, $y, 180, 6.5, $linkId);
    }

    private function guiaLineaMeta(TCPDF $pdf, string $k, string $v): void
    {
        TcpdfFuenteArial::aplicar($pdf, 'B', 9.5);
        $pdf->SetTextColor(115, 159, 165);
        $pdf->MultiCell(34, 5.5, $k.':', 0, 'L', false, 0);
        TcpdfFuenteArial::aplicar($pdf, '', 9.5);
        $pdf->SetTextColor(...self::GUIA_COLOR_TEXTO);
        $pdf->MultiCell(0, 5.5, $v, 0, 'L', false, 1);
    }

    private function guiaNombreSistema(): string
    {
        $nombre = trim((string) config('app.name', 'Sistemas Escolares'));

        return $nombre !== '' ? $nombre : 'Sistemas Escolares';
    }

    private function guiaLogoSistemaPath(): ?string
    {
        $fallback = public_path('img/3.png');

        return is_file($fallback) ? $fallback : null;
    }
}
