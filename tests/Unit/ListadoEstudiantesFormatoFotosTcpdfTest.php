<?php

namespace Tests\Unit;

use App\Support\Listados\ListadoEstudiantesFormatoFotosTcpdf;
use App\Support\Listados\ListadoEstudiantesFormatoTamanoFoto;
use Tests\TestCase;

class ListadoEstudiantesFormatoFotosTcpdfTest extends TestCase
{
    public function test_genera_pdf_con_alumnos_sin_foto(): void
    {
        $pdf = ListadoEstudiantesFormatoFotosTcpdf::generar($this->datos(['tamanoFoto' => ListadoEstudiantesFormatoTamanoFoto::MEDIANO]));

        $this->assertSame(1, $pdf->getNumPages());
        $binario = $pdf->Output('listado-fotos.pdf', 'S');
        $this->assertStringStartsWith('%PDF', $binario);
        $this->assertGreaterThan(1000, strlen($binario));
    }

    public function test_tamano_grande_tambien_genera_pdf(): void
    {
        $pdf = ListadoEstudiantesFormatoFotosTcpdf::generar($this->datos(['tamanoFoto' => ListadoEstudiantesFormatoTamanoFoto::GRANDE]));

        $this->assertGreaterThanOrEqual(1, $pdf->getNumPages());
        $binario = $pdf->Output('listado-fotos.pdf', 'S');
        $this->assertStringStartsWith('%PDF', $binario);
    }

    public function test_tamano_pequeno_genera_pdf(): void
    {
        $pdf = ListadoEstudiantesFormatoFotosTcpdf::generar($this->datos(['tamanoFoto' => ListadoEstudiantesFormatoTamanoFoto::PEQUENO]));

        $this->assertSame(1, $pdf->getNumPages());
        $binario = $pdf->Output('listado-fotos.pdf', 'S');
        $this->assertStringStartsWith('%PDF', $binario);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function datos(array $extra = []): array
    {
        return array_merge([
            'bloques' => [[
                'cursoLabel' => '4 A',
                'curso' => '4',
                'seccion' => 'A',
                'alumnos' => collect([
                    (object) ['apellido' => 'García', 'nombre' => 'Ana'],
                    (object) ['apellido' => 'Pérez', 'nombre' => 'Luis'],
                ]),
            ]],
            'nivelNombre' => 'Primario',
            'ano' => 2026,
            'pdfHeader' => [
                'insti' => 'Escuela de prueba',
                'direccion' => '',
                'localidad' => '',
                'cue' => '',
                'ee' => '',
                'logo_file' => null,
            ],
            'tamanoFoto' => ListadoEstudiantesFormatoTamanoFoto::MEDIANO,
        ], $extra);
    }
}
