<?php

namespace Tests\Unit;

use App\Support\Listados\ListadoFamiliasConsulta;
use App\Support\Listados\ListadoFamiliasExport;
use PHPUnit\Framework\TestCase;

class ListadoFamiliasConsultaTest extends TestCase
{
    public function test_curso_y_seccion_usa_campos_c_y_s(): void
    {
        $curso = (object) ['c' => '3', 's' => 'A', 'cursec' => '3° A'];

        $this->assertSame(
            ['curso' => '3', 'seccion' => 'A'],
            ListadoFamiliasConsulta::cursoYSeccion($curso),
        );
    }

    public function test_curso_y_seccion_cae_a_cursec_si_faltan_c_y_s(): void
    {
        $curso = (object) ['c' => '', 's' => '  ', 'cursec' => 'Sala 5 Azul'];

        $this->assertSame(
            ['curso' => 'Sala 5 Azul', 'seccion' => ''],
            ListadoFamiliasConsulta::cursoYSeccion($curso),
        );
    }

    public function test_curso_y_seccion_vacio_si_no_hay_curso(): void
    {
        $this->assertSame(
            ['curso' => '', 'seccion' => ''],
            ListadoFamiliasConsulta::cursoYSeccion(null),
        );
    }

    public function test_encabezados_export_tienen_diez_columnas(): void
    {
        $encabezados = ListadoFamiliasExport::encabezados();

        $this->assertCount(10, $encabezados);
        $this->assertSame('Familia', $encabezados[1]);
        $this->assertSame('Apellido', $encabezados[5]);
        $this->assertSame('Curso', $encabezados[8]);
        $this->assertSame('Sección', $encabezados[9]);
    }
}
