<?php

namespace Tests\Unit;

use App\Support\Mora\LibreDeudaFamiliarTcpdf;
use Tests\TestCase;

class LibreDeudaFamiliarTcpdfTest extends TestCase
{
    public function test_genera_pdf_con_datos_minimos(): void
    {
        $pdf = LibreDeudaFamiliarTcpdf::generar([
            'apenom' => 'Pérez Ana',
            'dni' => '30111222',
            'cursec' => 'QUINTO B',
            'nivel' => 'Nivel Secundario',
            'fecha' => '28/09/2026',
            'lugar' => 'Córdoba',
            'replegal' => 'Guillermo S. Contreras',
            'firma_file' => public_path('img/firmaRepLegal.jpg'),
            'header' => [
                'insti' => 'Instituto Ejemplo',
                'direccion' => 'José A. Guardado 184 - Bº Las Flores',
                'cuit' => '30-67760914-8',
                'condicion_iva' => 'Exento',
                'ingresos_brutos' => 'Exento',
                'logo_file' => null,
            ],
        ]);

        $binario = $pdf->Output('libre-deuda-familiar.pdf', 'S');

        $this->assertNotSame('', $binario);
        $this->assertStringStartsWith('%PDF', $binario);
    }

    public function test_omite_el_curso_cuando_es_cero(): void
    {
        $pdf = LibreDeudaFamiliarTcpdf::generar([
            'apenom' => 'Gómez Luis',
            'dni' => '28999888',
            'cursec' => '0',
            'nivel' => 'Nivel Primario',
            'fecha' => '28/09/2026',
            'lugar' => 'Córdoba',
            'replegal' => '',
            'firma_file' => null,
            'header' => [
                'insti' => 'Instituto Ejemplo',
                'direccion' => '',
                'cuit' => '',
                'condicion_iva' => '',
                'ingresos_brutos' => '',
                'logo_file' => null,
            ],
        ]);

        $binario = $pdf->Output('libre-deuda-familiar.pdf', 'S');

        $this->assertStringStartsWith('%PDF', $binario);
    }
}
