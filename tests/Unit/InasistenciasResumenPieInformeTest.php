<?php

namespace Tests\Unit;

use App\Models\Inasistencia;
use App\Models\InasistenciaValor;
use App\Support\InasistenciasResumen;
use App\Support\InformeInasistenciasTcpdf;
use Tests\TestCase;

class InasistenciasResumenPieInformeTest extends TestCase
{
    public function test_pie_suma_todo_salvo_educacion_fisica_y_cuenta_registros_ef(): void
    {
        $inasistencias = collect([
            $this->inasistencia('Clase (1.00)', 1.00, 'J', '2'),
            $this->inasistencia('Llegada Tarde (5m)', 0.25, 'J', '3'),
            $this->inasistencia('Llegada Tarde (5m)', 0.25, 'J', '3'),
            $this->inasistencia('Clase (1.00)', 1.00, 'I', '2'),
            $this->inasistencia('Educación Física', 1.00, 'J', '5'),
            $this->inasistencia('Educación Física', 1.00, '', '8'),
        ]);

        $pie = InasistenciasResumen::totalesPieInforme($inasistencias);

        $this->assertSame('Total de Inasistencias', $pie[0]['etiqueta']);
        $this->assertSame('2,50', $pie[0]['texto']);
        $this->assertSame('Inasistencias Justificadas', $pie[1]['etiqueta']);
        $this->assertSame('1,50', $pie[1]['texto']);
        $this->assertSame('Inasistencias Injustificadas', $pie[2]['etiqueta']);
        $this->assertSame('1,00', $pie[2]['texto']);
        $this->assertSame('Inasistencias a Educación Física', $pie[3]['etiqueta']);
        $this->assertSame('2', $pie[3]['texto']);
    }

    public function test_pdf_incluye_el_pie_legacy(): void
    {
        $inasistencias = collect([
            $this->inasistencia('Llegada Tarde (5m)', 0.25, 'J', '3', '2026-09-14'),
            $this->inasistencia('Educación Física', 1.00, 'J', '5', '2026-09-14'),
        ]);

        $pdf = InformeInasistenciasTcpdf::generar([
            'ano' => 2026,
            'alumnoLinea' => 'PEREZ JUAN',
            'dni' => '12345678',
            'cursoLabel' => 'PRIMERO A',
            'fechaDesde' => '01/01/2026',
            'fechaHasta' => '17/09/2026',
            'filtroFechasActivo' => false,
            'inasistencias' => $inasistencias,
            'totalesCatalogo' => [],
            'totalesPieInforme' => InasistenciasResumen::totalesPieInforme($inasistencias),
        ], [
            'insti' => 'Instituto de prueba',
            'direccion' => '',
            'localidad' => '',
            'cue' => '',
            'ee' => '',
            'logo_file' => null,
        ]);

        $this->assertSame(1, $pdf->getNumPages());
        $binario = $pdf->Output('informe.pdf', 'S');
        $this->assertStringStartsWith('%PDF', $binario);
        $this->assertGreaterThan(1000, strlen($binario));
    }

    private function inasistencia(
        string $concepto,
        float $cantidad,
        string $just,
        string $tipo,
        string $fecha = '2026-09-01',
    ): Inasistencia {
        $inasistencia = new Inasistencia([
            'fecha' => $fecha,
            'cantidad' => $cantidad,
            'tipo' => $tipo,
            'just' => $just,
            'obs' => '',
        ]);
        $inasistencia->setRelation('valorTipo', new InasistenciaValor([
            'concepto' => $concepto,
        ]));

        return $inasistencia;
    }
}
