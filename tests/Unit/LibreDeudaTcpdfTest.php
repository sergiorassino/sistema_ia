<?php

namespace Tests\Unit;

use App\Support\Alumnos\LibreDeudaTcpdf;
use Tests\TestCase;

class LibreDeudaTcpdfTest extends TestCase
{
    public function test_genera_pdf_con_datos_minimos(): void
    {
        $pdf = LibreDeudaTcpdf::generar([
            'id_legajo' => 123,
            'apellido' => 'Acevedo Vadora',
            'nombre' => 'Paulina',
            'apenom' => 'Acevedo Vadora Paulina',
            'dni' => '49767925',
            'cursec' => 'QUINTO B',
            'nivel' => 'Nivel Secundario',
            'fecha' => '03/09/2026',
            'lugar' => 'Monte Cristo',
            'header' => [
                'insti' => 'Instituto Parroquial Monte Cristo',
                'direccion' => '',
                'localidad' => 'Monte Cristo',
                'provincia' => '',
                'cue' => '',
                'ee' => '',
                'logo_file' => null,
            ],
            'firma_file' => public_path('img/tenants/montecristo/libre-deuda-firma.png'),
            'sello_file' => public_path('img/tenants/montecristo/libre-deuda-sello.png'),
        ]);

        $binario = $pdf->Output('libre-deuda.pdf', 'S');

        $this->assertNotSame('', $binario);
        $this->assertStringStartsWith('%PDF', $binario);
    }
}
