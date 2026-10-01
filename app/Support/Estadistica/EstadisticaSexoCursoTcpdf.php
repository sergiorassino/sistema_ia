<?php

namespace App\Support\Estadistica;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * Estadística por sexo y curso — A4 vertical, TCPDF, Arial.
 */
final class EstadisticaSexoCursoTcpdf extends TCPDF
{
    private const MARGEN = 10.0;

    private const ANCHO = 190.0;

    private const Y_MAX = 282.0;

    private const ALTO_FILA = 6.0;

    private const ALTO_CABEZA = 8.0;

    /** @var array<string, mixed> */
    private array $datos;

    /** @var list<float> */
    private array $anchos = [];

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Estadística por sexo y curso');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 8);
        $this->SetMargins(self::MARGEN, self::MARGEN, self::MARGEN);
        $this->setCellPaddings(0.8, 0.4, 0.8, 0.4);
        $this->anchos = self::calcularAnchos(count((array) ($datos['columnas'] ?? [])));
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

    /**
     * @return list<float>
     */
    private static function calcularAnchos(int $cantidadSexos): array
    {
        $anio = 16.0;
        $nivel = 38.0;
        $curso = 46.0;
        $total = 16.0;
        $fijos = $anio + $nivel + $curso + $total;
        $resto = max(20.0, self::ANCHO - $fijos);
        $sexo = $cantidadSexos > 0 ? $resto / $cantidadSexos : 0.0;

        $anchos = [$anio, $nivel, $curso];
        for ($i = 0; $i < $cantidadSexos; $i++) {
            $anchos[] = $sexo;
        }
        $anchos[] = $total;

        return $anchos;
    }

    private function dibujarDocumento(): void
    {
        $y = $this->dibujarEncabezadoInstitucional(self::MARGEN);
        $y = $this->dibujarCabezaTabla($y);

        $filas = (array) ($this->datos['filas'] ?? []);
        if ($filas === []) {
            TcpdfFuenteArial::aplicar($this, '', 9);
            $this->SetXY(self::MARGEN, $y + 4);
            $this->Cell(self::ANCHO, 6, 'No hay cursos en este ciclo y nivel.', 0, 1, 'C');

            return;
        }

        $ano = (string) ($this->datos['ano'] ?? '');
        $nivel = (string) ($this->datos['nivel'] ?? '');
        $columnas = (array) ($this->datos['columnas'] ?? []);

        foreach ($filas as $fila) {
            $y = $this->asegurarEspacio($y, self::ALTO_FILA);
            $fila = (array) $fila;
            $conteos = (array) ($fila['conteos'] ?? []);
            $textos = [$ano, $nivel, (string) ($fila['curso'] ?? '')];
            foreach ($columnas as $columna) {
                $clave = (string) ($columna['clave'] ?? '');
                $textos[] = (string) (int) ($conteos[$clave] ?? 0);
            }
            $textos[] = (string) (int) ($fila['total'] ?? 0);
            $this->dibujarCeldas($y, $textos, self::ALTO_FILA, false, false);
            $y += self::ALTO_FILA;
        }

        $y = $this->asegurarEspacio($y, self::ALTO_FILA);
        $totales = (array) ($this->datos['totales'] ?? []);
        $textos = [
            'Total acumulado ('.count($filas).') — Suma',
            '',
            '',
        ];
        foreach ($columnas as $columna) {
            $clave = (string) ($columna['clave'] ?? '');
            $textos[] = (string) (int) ($totales[$clave] ?? 0);
        }
        $textos[] = (string) (int) ($this->datos['total_general'] ?? 0);
        $this->dibujarFilaTotal($y, $textos);
    }

    private function dibujarEncabezadoInstitucional(float $y): float
    {
        $header = schoolPdfHeaderData();
        $insti = trim((string) ($header['insti'] ?? ''));
        if ($insti === '') {
            $insti = trim((string) config('tenant.nombre', ''));
        }

        $alto = 18.0;
        $this->SetDrawColor(64, 132, 141);
        $this->Rect(self::MARGEN, $y, self::ANCHO, $alto);

        TcpdfLogoInstitucional::dibujar($this, self::MARGEN + 2, $y + 1.5, 14, 14, $header['logo_file'] ?? null);

        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->SetTextColor(51, 51, 51);
        $this->SetXY(self::MARGEN + 18, $y + 2);
        $this->Cell(self::ANCHO - 22, 5, $insti !== '' ? $insti : 'Estadística por sexo y curso', 0, 1, 'L');

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->SetTextColor(64, 132, 141);
        $this->SetX(self::MARGEN + 18);
        $this->Cell(self::ANCHO - 22, 4.5, 'Estadística por Sexo y Curso (Solo regulares)', 0, 1, 'L');

        $nivel = trim((string) ($this->datos['nivel'] ?? ''));
        $ano = trim((string) ($this->datos['ano'] ?? ''));
        $fecha = trim((string) ($this->datos['fecha'] ?? ''));
        $linea = trim($nivel.($ano !== '' ? ' · Ciclo '.$ano : '').($fecha !== '' ? ' · '.$fecha : ''));
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetTextColor(51, 51, 51);
        $this->SetX(self::MARGEN + 18);
        $this->Cell(self::ANCHO - 22, 4, $linea, 0, 1, 'L');

        return $y + $alto + 3;
    }

    private function dibujarCabezaTabla(float $y): float
    {
        $columnas = (array) ($this->datos['columnas'] ?? []);
        $textos = ['Año', 'Nivel', 'Curso y sección'];
        foreach ($columnas as $columna) {
            $textos[] = (string) ($columna['etiqueta'] ?? '');
        }
        $textos[] = 'Total';
        $this->dibujarCeldas($y, $textos, self::ALTO_CABEZA, true, true);

        return $y + self::ALTO_CABEZA;
    }

    private function asegurarEspacio(float $y, float $alto): float
    {
        if ($y + $alto <= self::Y_MAX) {
            return $y;
        }

        $this->AddPage();
        $y = self::MARGEN;

        return $this->dibujarCabezaTabla($y);
    }

    /**
     * @param  list<string>  $textos
     */
    private function dibujarCeldas(float $y, array $textos, float $alto, bool $cabeza, bool $relleno): void
    {
        $this->SetDrawColor(180, 180, 180);
        if ($cabeza) {
            $this->SetFillColor(226, 232, 234);
            TcpdfFuenteArial::aplicar($this, 'B', 8);
        } else {
            $this->SetFillColor(255, 255, 255);
            TcpdfFuenteArial::aplicar($this, '', 8);
        }
        $this->SetTextColor(51, 51, 51);

        $x = self::MARGEN;
        foreach ($this->anchos as $i => $ancho) {
            $texto = (string) ($textos[$i] ?? '');
            $align = $i >= 3 ? 'C' : 'L';
            $this->MultiCell(
                $ancho,
                $alto,
                $texto,
                1,
                $align,
                $relleno || $cabeza,
                0,
                $x,
                $y,
                true,
                0,
                false,
                true,
                $alto,
                'M',
                true
            );
            $x += $ancho;
        }
    }

    /**
     * @param  list<string>  $textos
     */
    private function dibujarFilaTotal(float $y, array $textos): void
    {
        $this->SetDrawColor(180, 180, 180);
        $this->SetFillColor(220, 224, 226);
        $this->SetTextColor(51, 51, 51);
        TcpdfFuenteArial::aplicar($this, 'B', 8);

        $anchoEtiqueta = $this->anchos[0] + $this->anchos[1] + $this->anchos[2];
        $this->MultiCell(
            $anchoEtiqueta,
            self::ALTO_FILA,
            (string) ($textos[0] ?? ''),
            1,
            'L',
            true,
            0,
            self::MARGEN,
            $y,
            true,
            0,
            false,
            true,
            self::ALTO_FILA,
            'M',
            true
        );

        $x = self::MARGEN + $anchoEtiqueta;
        $desde = 3;
        for ($i = $desde; $i < count($this->anchos); $i++) {
            $ancho = $this->anchos[$i];
            $this->MultiCell(
                $ancho,
                self::ALTO_FILA,
                (string) ($textos[$i] ?? ''),
                1,
                'C',
                true,
                0,
                $x,
                $y,
                true,
                0,
                false,
                true,
                self::ALTO_FILA,
                'M',
                true
            );
            $x += $ancho;
        }
    }
}
