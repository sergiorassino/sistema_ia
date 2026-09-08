<?php

namespace Tests\Unit;

use App\Support\Cuotas\ComprobanteAfipTcpdf;
use Tests\TestCase;

class ComprobanteAfipTcpdfTest extends TestCase
{
    public function test_genera_pdf_con_logo_a_la_izquierda(): void
    {
        $pdf = ComprobanteAfipTcpdf::generar($this->datosFactura());

        $this->assertSame(1, $pdf->getNumPages());
        $binario = $pdf->Output('factura-afip.pdf', 'S');
        $this->assertStringStartsWith('%PDF', $binario);
        $this->assertGreaterThan(1000, strlen($binario));
    }

    public function test_genera_pdf_sin_logo_si_no_hay_archivo(): void
    {
        $datos = $this->datosFactura();
        $datos['logo_file'] = null;

        $pdf = ComprobanteAfipTcpdf::generar($datos);

        $binario = $pdf->Output('factura-afip.pdf', 'S');
        $this->assertStringStartsWith('%PDF', $binario);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFactura(): array
    {
        $logo = public_path('img/3.png');

        return [
            'nombreInstitucion' => 'Instituto Ramallo',
            'logo_file' => is_file($logo) ? $logo : null,
            'razonSocial' => 'Asociación Civil Instituto Ramallo',
            'telefonoInstitucion' => '03407-422000',
            'tipoComprobante' => 15,
            'numeroComprobanteTexto' => '00005-00099999',
            'cuitInstitucion' => '30-12345678-9',
            'domicilioComercial' => 'Belgrano 123, Ramallo, Buenos Aires',
            'ingresosBrutos' => 'Exento',
            'fechaInicioActividades' => '01/03/1990',
            'condicionIvaInstitucion' => 'IVA Exento',
            'aporteEstatal' => '100%',
            'fechaEmision' => '08/09/2026',
            'docNro' => '12.345.678',
            'nombreCliente' => 'Pérez Juan',
            'nombreResp' => 'Pérez María',
            'dniResp' => '20.111.222',
            'cursoTexto' => '1° AÑO A',
            'cuotaTexto' => 'SEPTIEMBRE 2026',
            'condicionIvaReceptorTexto' => 'Consumidor Final',
            'muestraCondicionVenta' => true,
            'condicionVenta' => 'Contado',
            'concepto' => 'Arancel septiembre 2026',
            'importeFmt' => '85.000,00',
            'lineas' => [[
                'concepto' => 'Arancel septiembre 2026',
                'importeFmt' => '85.000,00',
            ]],
            'becaPorcentaje' => 0,
            'becaImporteOriginalFmt' => '',
            'obsFacturaHtml' => '',
            'cae' => '12345678901234',
            'vtoCae' => '18/09/2026',
            'urlQr' => '',
        ];
    }
}
