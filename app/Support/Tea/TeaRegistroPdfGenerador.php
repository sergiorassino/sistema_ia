<?php

namespace App\Support\Tea;

use App\Models\ReincoRegistro;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF;

/**
 * Despacha el PDF de un registro TEA según tenant: plantilla estática (FPDI) o implementación TCPDF.
 */
final class TeaRegistroPdfGenerador
{
    public static function implementacion(): string
    {
        return tenantTeaRegistroImplementacion();
    }

    public static function generar(ReincoRegistro $registro): TCPDF
    {
        $idTipo = (int) $registro->idReinco_tipo;
        $plantilla = tenantTeaRegistroPlantillaPdf($idTipo);

        if ($plantilla !== null) {
            return self::generarDesdePlantillaEstatica($registro, $plantilla);
        }

        $datos = TeaRegistroDatos::desdeRegistro($registro);

        return match (self::implementacion()) {
            'montecristo' => TeaRegistroMontecristoTcpdf::generar($datos),
            default => TeaRegistroCaixalsfTcpdf::generar($datos),
        };
    }

    private static function generarDesdePlantillaEstatica(ReincoRegistro $registro, string $plantilla): Fpdi
    {
        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        $pageCount = $pdf->setSourceFile($plantilla);
        for ($i = 1; $i <= $pageCount; $i++) {
            $template = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($template);
            $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }

        self::superponerDatosPlantilla($pdf, $registro);

        return $pdf;
    }

    private static function superponerDatosPlantilla(Fpdi $pdf, ReincoRegistro $registro): void
    {
        $matricula = $registro->matricula;
        $legajo = $matricula?->legajo;
        $curso = $matricula?->curso;

        $nombre = trim((string) (($legajo?->apellido ?? '').', '.($legajo?->nombre ?? '')));
        $dni = trim((string) ($legajo?->dni ?? ''));
        $cursoNombre = trim((string) ($curso?->nombreParaListado() ?? ''));
        $fecha = $registro->fecha ? $registro->fecha->format('d/m/Y') : '';
        $obs = trim((string) ($registro->obs ?? ''));

        \App\Support\Pdf\TcpdfFuenteArial::aplicar($pdf, '', 10);
        $pdf->SetTextColor(33, 33, 33);

        $pdf->SetXY(15, $pdf->getPageHeight() - 28);
        $pdf->MultiCell(180, 4, implode("\n", array_filter([
            $nombre !== '' ? 'Estudiante: '.$nombre : null,
            $dni !== '' ? 'DNI: '.$dni : null,
            $cursoNombre !== '' ? 'Curso: '.$cursoNombre : null,
            $fecha !== '' ? 'Fecha registro: '.$fecha : null,
            $obs !== '' ? 'Obs.: '.$obs : null,
        ])), 0, 'L');
    }
}
