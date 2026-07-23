<?php

namespace App\Support\CertificacionServicios;

use App\Support\Pdf\TcpdfFuenteArial;
use Carbon\Carbon;
use TCPDF;

/**
 * PDF Certificación de Servicios (réplica legacy FPDF → TCPDF).
 */
final class CertificacionServiciosTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO = 170.0;

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
        $this->SetTitle('Certificación de Servicios');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, 15);
        $this->SetMargins(self::MARGEN_IZQ, 12, 20);
        $this->SetFillColor(255, 255, 255);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujar();

        return $pdf;
    }

    private function dibujar(): void
    {
        $d = $this->datos;

        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->Cell(self::ANCHO, 7, 'CERTIFICACIÓN DE SERVICIOS', 0, 1, 'C');

        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->MultiCell(self::ANCHO, 5, 'Instituto Privado Incorporado (adscripto) a la Enseñanza Oficial', 0, 'L', false, 1);
        $this->Cell(self::ANCHO, 5, (string) ($d['insti'] ?? ''), 0, 1, 'L');
        $this->Cell(self::ANCHO, 5, 'Nivel: '.(string) ($d['nivelNombre'] ?? ''), 0, 1, 'L');
        $ubic = trim((string) ($d['ubicacion'] ?? ''));
        if ($ubic !== '') {
            $this->Cell(self::ANCHO, 5, 'Ubicado en: '.$ubic, 0, 1, 'L');
        }
        $this->Ln(2);

        $nombre = (string) ($d['profesorNombre'] ?? '');
        $dni = (string) ($d['profesorDni'] ?? '');
        $intro = 'Certifico con carácter de declaración jurada que: '.$nombre
            .($dni !== '' ? ', DNI: '.$dni : '')
            .' presta/ó, en el establecimiento arriba mencionado, los servicios docentes que a continuación se indican.';
        $this->MultiCell(self::ANCHO, 5, $intro, 0, 'L', false, 1);
        $this->Ln(2);

        // Encabezado tabla servicios
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->celda(35, 7, 'Cargo Asignado', 1, 0, 'L');
        $this->celda(14, 7, 'Hs', 1, 0, 'C');
        $this->celda(20, 7, 'T/S', 1, 0, 'L');
        $this->celda(48, 7, 'Resol. Núm.', 1, 0, 'L');
        $this->celda(16, 7, 'Alta', 1, 0, 'C');
        $this->celda(20, 7, 'Baja', 1, 0, 'C');
        $this->celda(8, 7, 'Año', 1, 0, 'C');
        $this->celda(8, 7, 'Mes', 1, 0, 'C');
        $this->celda(7, 7, 'Dia', 1, 1, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        /** @var list<array<string, mixed>> $servicios */
        $servicios = $d['servicios'] ?? [];
        foreach ($servicios as $s) {
            $this->ensureSpace(8);
            $this->celda(35, 7, (string) ($s['cargo'] ?? ''), 1, 0, 'L');
            $this->celda(14, 7, (string) ($s['hsCatedra'] ?? ''), 1, 0, 'C');
            $this->celda(20, 7, (string) ($s['titularSuplente'] ?? ''), 1, 0, 'L');
            $this->celda(48, 7, (string) ($s['nroResolucion'] ?? ''), 1, 0, 'L');
            $this->celda(16, 7, (string) ($s['fechaAlta'] ?? ''), 1, 0, 'C');
            $this->celda(20, 7, (string) ($s['fechaBaja'] ?? ''), 1, 0, 'C');
            $this->celda(8, 7, (string) ($s['anios'] ?? 0), 1, 0, 'C');
            $this->celda(8, 7, (string) ($s['meses'] ?? 0), 1, 0, 'C');
            $this->celda(7, 7, (string) ($s['dias'] ?? 0), 1, 1, 'C');
        }

        $sub = $d['subtotal'] ?? ['anios' => 0, 'meses' => 0, 'dias' => 0];
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->celda(153, 7, 'Subtotal: ', 1, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->celda(8, 7, (string) $sub['anios'], 1, 0, 'C');
        $this->celda(8, 7, (string) $sub['meses'], 1, 0, 'C');
        $this->celda(7, 7, (string) $sub['dias'], 1, 1, 'C');

        $this->Ln(3);
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(self::ANCHO, 7, 'Hizo uso de licencias sin goce de haberes totales:', 0, 1, 'C');
        $this->Ln(1);

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(45, 7, '', 0, 0, 'L');
        $this->celda(28, 7, 'Fecha de Inicio', 1, 0, 'L');
        $this->celda(28, 7, 'Fecha de Fin', 1, 0, 'L');
        $this->celda(28, 7, 'Licencia Parcial', 1, 1, 'L');

        TcpdfFuenteArial::aplicar($this, '', 8);
        /** @var list<array<string, mixed>> $licencias */
        $licencias = $d['licencias'] ?? [];
        foreach ($licencias as $l) {
            $this->ensureSpace(8);
            $this->Cell(45, 7, '', 0, 0, 'L');
            $this->celda(28, 7, (string) ($l['fechaInicio'] ?? ''), 1, 0, 'L');
            $this->celda(28, 7, (string) ($l['fechaFin'] ?? ''), 1, 0, 'L');
            $this->celda(28, 7, (string) ($l['parcial'] ?? 'No'), 1, 1, 'L');
        }

        $this->Ln(3);
        $ant = $d['antiguedad'] ?? ['anios' => 0, 'meses' => 0, 'dias' => 0];
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $txtAnt = 'ANTIGÜEDAD TOTAL: '.$ant['anios'].' AÑOS '.$ant['meses'].' MESES Y '.$ant['dias'].' DIAS';
        $this->celda(self::ANCHO, 7, $txtAnt, 1, 1, 'L');

        $this->Ln(3);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(self::ANCHO, 7, 'Para presentar ante: '.(string) ($d['paraPresentar'] ?? ''), 0, 1, 'L');

        /** @var Carbon $fe */
        $fe = $d['fechaEmision'];
        $mes = self::mesEspanol((int) $fe->format('n'));
        $this->Cell(
            self::ANCHO,
            7,
            'Se extiende la presente a los '.$fe->format('d').' días del mes '.$mes.' de '.$fe->format('Y'),
            0,
            1,
            'L'
        );

        $this->Ln(10);
        $this->Cell(8, 7, '', 0, 0, 'L');
        $this->Cell(60, 7, '.........................................................................', 0, 0, 'L');
        $this->Cell(40, 7, '', 0, 0, 'L');
        $this->Cell(60, 7, '..........................................................................', 0, 1, 'L');
        $this->Cell(8, 7, '', 0, 0, 'L');
        $this->Cell(60, 7, 'Aclaración de la Firma', 0, 0, 'C');
        $this->Cell(40, 7, '', 0, 0, 'L');
        $this->Cell(60, 7, 'Firma', 0, 1, 'C');

        $this->Ln(5);
        $replegal = (string) ($d['replegal'] ?? '');
        $this->MultiCell(
            self::ANCHO,
            5,
            'El funcionario que suscribe certifica que la firma que antecede pertenece a: '.$replegal
            .' de acuerdo a la registrada en esta repartición.',
            0,
            'L',
            false,
            1
        );
        $this->Ln(3);
        $this->Cell(self::ANCHO, 7, 'Córdoba,   .........   de  ...........................  de  20 ......', 0, 1, 'R');
    }

    private function celda(float $w, float $h, string $txt, int $border, int $ln, string $align): void
    {
        $this->Cell($w, $h, $txt, $border, $ln, $align);
    }

    private function ensureSpace(float $neededMm): void
    {
        if ($this->GetY() + $neededMm > ($this->getPageHeight() - 20)) {
            $this->AddPage();
        }
    }

    private static function mesEspanol(int $mes): string
    {
        return match ($mes) {
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
            default => '',
        };
    }
}
