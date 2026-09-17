<?php

namespace Tests\Unit;

use App\Support\Cuotas\ResumenPagosEstudianteTcpdf;
use Tests\TestCase;

class ResumenPagosEstudianteTcpdfTest extends TestCase
{
    public function test_genera_pdf_con_fecha_de_pago_y_comprobante_interno(): void
    {
        $pdf = ResumenPagosEstudianteTcpdf::generar($this->datosUnaFila());

        $this->assertSame(1, $pdf->getNumPages());
        $binario = $pdf->Output('resumen.pdf', 'S');
        $this->assertStringStartsWith('%PDF', $binario);
        $this->assertGreaterThan(1000, strlen($binario));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosUnaFila(): array
    {
        return [
            'pdfHeader' => [
                'insti' => 'COLEGIO PARROQUIAL SAN FRANCISCO DE ASIS',
                'direccion' => '',
                'localidad' => '',
                'cue' => '',
                'ee' => '',
            ],
            'fechaImpresion' => '17/09/2026 11:00',
            'apellidoNombre' => 'ARIZA REYNOSO NAZARENO VALENTIN',
            'dni' => '12345678',
            'curso' => 'SEXTO B',
            'terlecAno' => '2026',
            'filas' => [
                [
                    'ano' => '2025',
                    'cuota' => 'MARZO 2025',
                    'fechaPago' => '07/03/2025',
                    'medioPago' => 'EFE',
                    'importe' => '90.000,00',
                    'bonificacion' => '0,00',
                    'interes' => '0,00',
                    'abonado' => '90.000,00',
                    'nroComp' => '00001-00023428',
                ],
            ],
            'totales' => [
                'importe' => '90.000,00',
                'bonificacion' => '0,00',
                'interes' => '0,00',
                'abonado' => '90.000,00',
            ],
        ];
    }
}
