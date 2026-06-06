<?php

namespace App\Support\InasistenciasDocentes;

use App\Models\Profesor;
use App\Support\InasistenciasDocentes as InasDocentesModulo;
use Illuminate\Support\Facades\DB;
use TCPDF;

/**
 * PDF informe bimestral de inasistencias docente (TCPDF, UTF-8).
 * Listado y totales consolidados por DNI en vivo; cada fila sigue ligada a su {@see Profesor::id}.
 */
final class InformeInasistenciasDocenteTcpdf extends TCPDF
{
    private const FUENTE = 'dejavusans';

    private function __construct()
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, 15);
        $this->SetMargins(20, 10, 15);
    }

    public static function render(int $idProfesor, int $bimestre, int $anio, string $nombreInstitucion): string
    {
        $profesor = Profesor::query()->findOrFail($idProfesor);
        $idsProfesores = InasDocentesModulo::idsProfesoresMismoDni($idProfesor);
        $b = InasDocentesModulo::BIMESTRES[$bimestre] ?? InasDocentesModulo::BIMESTRES[1];
        [$mesIni, $mesFin] = CalculoFaltasDescuento::BIMESTRE_MESES[$bimestre];

        $inasistencias = InasDocentesModulo::inasistenciasBimestrePorProfesor($idProfesor, $bimestre, $anio);
        $calculo = CalculoFaltasDescuento::calcular($idProfesor, $bimestre, $anio);
        $detalleCargos = $calculo['detalle'] ?? [];

        $pdf = new self();
        $pdf->SetCreator('Sistema Escolar');
        $pdf->SetTitle('Informe de inasistencias docentes');
        $pdf->AddPage();
        $pdf->SetFillColor(192, 192, 192);

        $pdf->SetFont(self::FUENTE, 'B', 12);
        $pdf->SetXY(20, 10);
        $pdf->Rect(20, 10, 170, 22);
        $pdf->Cell(170, 7, $nombreInstitucion, 0, 2, 'C');
        $pdf->SetFont(self::FUENTE, '', 10);
        $pdf->Cell(170, 5, 'INFORME DE INASISTENCIAS DOCENTES', 0, 2, 'C');

        $apenom = trim($profesor->apellido.', '.$profesor->nombre);
        $pdf->Ln(8);
        $pdf->SetFont(self::FUENTE, 'B', 11);
        $pdf->Cell(170, 7, $apenom, 0, 2, 'C');
        $pdf->SetFont(self::FUENTE, '', 10);
        $pdf->Cell(170, 7, 'Informe del bimestre: '.$b['titulo'].' '.$anio, 0, 2, 'C');

        $pdf->SetFont(self::FUENTE, '', 7);
        $pdf->Cell(25, 5, 'Nivel', 1, 0, 'C', true);
        $pdf->Cell(22, 5, 'Cargo', 1, 0, 'C', true);
        $pdf->Cell(78, 5, 'Motivo', 1, 0, 'C', true);
        $pdf->Cell(15, 5, 'Fecha', 1, 0, 'C', true);
        $pdf->Cell(10, 5, 'Cant', 1, 0, 'C', true);
        $pdf->Cell(8, 5, 'Just', 1, 0, 'C', true);
        $pdf->Cell(12, 5, 'Obs', 1, 1, 'C', true);

        foreach ($inasistencias as $i) {
            $pdf->Cell(25, 5, $i->nivel?->nivel ?? '', 1, 0, 'C');
            $pdf->Cell(22, 5, (string) ($i->nombreCargo ?? ''), 1, 0, 'C');
            $pdf->Cell(78, 5, $i->tipo?->motivo ?? '', 1, 0, 'L');
            $pdf->Cell(15, 5, $i->fecha ? $i->fecha->format('d/m/Y') : '', 1, 0, 'C');
            $pdf->Cell(10, 5, (string) $i->cantObligIna, 1, 0, 'C');
            $pdf->Cell(8, 5, (int) ($i->justif ?? 0) === 1 ? 'Sí' : 'No', 1, 0, 'C');
            $pdf->Cell(12, 5, mb_substr((string) ($i->obs ?? ''), 0, 16), 1, 1, 'L');
        }

        $pdf->Ln(10);
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->Cell(170, 5, 'TOTALES', 1, 1, 'C', true);
        $pdf->Ln(2);
        $pdf->Cell(40, 5, 'Nivel', 1, 0, 'C', true);
        $pdf->Cell(50, 5, 'Cargo', 1, 0, 'C', true);
        $pdf->Cell(25, 5, 'Cant.Oblig.', 1, 0, 'C', true);
        $pdf->Cell(25, 5, 'Justif.', 1, 0, 'C', true);
        $pdf->Cell(30, 5, 'Injust.', 1, 1, 'C', true);

        if (InasDocentesModulo::tieneCargos()) {
            $cargos = DB::table('cargosxprofesor as cxp')
                ->join('niveles as n', 'n.id', '=', 'cxp.idNiveles')
                ->join('cargos as c', 'c.id', '=', 'cxp.idCargos')
                ->whereIn('cxp.idProfesores', $idsProfesores)
                ->where('cxp.idCargos', '<>', CalculoFaltasDescuento::ID_CARGO_PROFESOR)
                ->orderBy('n.nivel')
                ->orderBy('c.cargo')
                ->get(['cxp.id', 'cxp.idNiveles', 'n.nivel', 'c.cargo', 'cxp.cant']);

            foreach ($cargos as $row) {
                $tot = (float) DB::table('inasdocentes')
                    ->where('idCargosXProfesor', $row->id)
                    ->where('idNivel', $row->idNiveles)
                    ->whereYear('fecha', $anio)
                    ->whereRaw('MONTH(fecha) IN (?, ?)', [$mesIni, $mesFin])
                    ->sum('cantObligIna');
                $just = (float) DB::table('inasdocentes')
                    ->where('idCargosXProfesor', $row->id)
                    ->where('idNivel', $row->idNiveles)
                    ->where('justif', 1)
                    ->whereYear('fecha', $anio)
                    ->whereRaw('MONTH(fecha) IN (?, ?)', [$mesIni, $mesFin])
                    ->sum('cantObligIna');

                $pdf->SetFont(self::FUENTE, '', 7);
                $pdf->Cell(40, 5, (string) $row->nivel, 1, 0, 'C');
                $pdf->Cell(50, 5, $row->cargo.' ('.$row->cant.')', 1, 0, 'C');
                $pdf->Cell(25, 5, (string) round($tot, 2), 1, 0, 'C');
                $pdf->Cell(25, 5, (string) round($just, 2), 1, 0, 'C');
                $pdf->Cell(30, 5, (string) round($tot - $just, 2), 1, 1, 'C');
            }

            $cantHorProf = (float) DB::table('cargosxprofesor')
                ->whereIn('idProfesores', $idsProfesores)
                ->where('idCargos', CalculoFaltasDescuento::ID_CARGO_PROFESOR)
                ->sum('cant');
            $totProf = (float) DB::table('inasdocentes as i')
                ->join('cargosxprofesor as cxp', 'i.idCargosXProfesor', '=', 'cxp.id')
                ->whereIn('i.idProfesores', $idsProfesores)
                ->where('cxp.idCargos', CalculoFaltasDescuento::ID_CARGO_PROFESOR)
                ->whereYear('i.fecha', $anio)
                ->whereRaw('MONTH(i.fecha) IN (?, ?)', [$mesIni, $mesFin])
                ->sum('i.cantObligIna');
            $justProf = (float) DB::table('inasdocentes as i')
                ->join('cargosxprofesor as cxp', 'i.idCargosXProfesor', '=', 'cxp.id')
                ->whereIn('i.idProfesores', $idsProfesores)
                ->where('cxp.idCargos', CalculoFaltasDescuento::ID_CARGO_PROFESOR)
                ->where('i.justif', 1)
                ->whereYear('i.fecha', $anio)
                ->whereRaw('MONTH(i.fecha) IN (?, ?)', [$mesIni, $mesFin])
                ->sum('i.cantObligIna');

            $pdf->Cell(40, 5, count($idsProfesores) > 1 ? 'Todos los niveles' : (string) (schoolCtx()->nivelNombre() ?: 'Nivel'), 1, 0, 'C');
            $pdf->Cell(50, 5, 'PROFESOR/A ('.$cantHorProf.')', 1, 0, 'C');
            $pdf->Cell(25, 5, (string) round($totProf, 2), 1, 0, 'C');
            $pdf->Cell(25, 5, (string) round($justProf, 2), 1, 0, 'C');
            $pdf->Cell(30, 5, (string) round($totProf - $justProf, 2), 1, 1, 'C');
        }

        $pdf->Ln(4);
        foreach ($detalleCargos as $d) {
            $pdf->Cell(170, 5, ($d['cargo'] ?? '').' (máx. faltas posibles): '.($d['maxFaltasPosibles'] ?? 0), 1, 1, 'C');
            $pdf->Cell(170, 5, 'A descuento: '.($d['aDescuento'] ?? 0), 1, 1, 'C');
        }

        return $pdf->Output('', 'S');
    }
}
