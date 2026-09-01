<?php

namespace Tests\Unit;

use App\Support\Cuotas\ComprobantePagoImputacionTcpdf;
use Tests\TestCase;

class ComprobantePagoImputacionTcpdfTest extends TestCase
{
    public function test_una_copia_por_hoja_cuando_el_flag_esta_apagado(): void
    {
        config(['tenant.cuotas.comprobante_imputacion.dos_copias_por_hoja' => false]);

        $pdf = ComprobantePagoImputacionTcpdf::generar($this->datosUnaCuota());

        $this->assertSame(1, $pdf->getNumPages());
        $binario = $pdf->Output('comprobante.pdf', 'S');
        $this->assertStringStartsWith('%PDF', $binario);
        $this->assertGreaterThan(1000, strlen($binario));
    }

    public function test_dos_copias_siguen_en_una_sola_hoja_a4(): void
    {
        config(['tenant.cuotas.comprobante_imputacion.dos_copias_por_hoja' => true]);

        $pdf = ComprobantePagoImputacionTcpdf::generar($this->datosUnaCuota());

        $this->assertSame(1, $pdf->getNumPages());
        $binario = $pdf->Output('comprobante.pdf', 'S');
        $this->assertStringStartsWith('%PDF', $binario);
        $this->assertGreaterThan(1000, strlen($binario));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosUnaCuota(): array
    {
        return [
            'pdfHeader' => [
                'insti' => 'E.P. SAN FRANCISCO',
                'direccion' => '',
                'localidad' => '',
                'cue' => '',
                'ee' => '',
            ],
            'fechaImpresion' => '01/09/2026 20:00',
            'nroComprobanteTexto' => '00001-00027034',
            'apellidoNombre' => 'ACOSTA CISNERO KAREN',
            'cursec' => 'SALA DE 4',
            'nivel' => 'NIVEL INICIAL',
            'cuotaNombre' => 'SEPTIEMBRE 2025',
            'importeOriginalFmt' => '31.500,00',
            'medioPago' => 'Efectivo',
            'importeFmt' => '31.500,00',
            'bonificacionFmt' => '0,00',
            'interesFmt' => '8.000,00',
            'abonadoFmt' => '39.500,00',
            'fechaPagoEsp' => '01/09/2026',
            'lineas' => [],
            'esMultiple' => false,
        ];
    }
}
