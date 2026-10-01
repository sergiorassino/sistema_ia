<?php

namespace Tests\Unit;

use App\Support\Estadistica\EstadisticaPorEdadDatos;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class EstadisticaPorEdadDatosTest extends TestCase
{
    public function test_clasifica_edad_cumplida_en_las_bandas_de_la_planilla(): void
    {
        $fecha = Carbon::parse('2026-10-01')->startOfDay();

        $this->assertSame(EstadisticaPorEdadDatos::MENOS_DE_3, EstadisticaPorEdadDatos::clasificar('2024-05-01', $fecha));
        $this->assertSame('12', EstadisticaPorEdadDatos::clasificar('2014-10-01', $fecha));
        $this->assertSame('13', EstadisticaPorEdadDatos::clasificar('2012-10-02', $fecha));
        $this->assertSame('14', EstadisticaPorEdadDatos::clasificar('2012-10-01', $fecha));
        $this->assertSame(EstadisticaPorEdadDatos::DE_20_A_25, EstadisticaPorEdadDatos::clasificar('2006-10-01', $fecha));
        $this->assertSame(EstadisticaPorEdadDatos::DE_20_A_25, EstadisticaPorEdadDatos::clasificar('2001-10-01', $fecha));
        $this->assertSame(EstadisticaPorEdadDatos::MAS_DE_25, EstadisticaPorEdadDatos::clasificar('2000-10-01', $fecha));
        $this->assertSame(EstadisticaPorEdadDatos::SIN_FECHA, EstadisticaPorEdadDatos::clasificar(null, $fecha));
        $this->assertSame(EstadisticaPorEdadDatos::SIN_FECHA, EstadisticaPorEdadDatos::clasificar('0000-00-00', $fecha));
        $this->assertSame(EstadisticaPorEdadDatos::SIN_FECHA, EstadisticaPorEdadDatos::clasificar('2027-01-01', $fecha));
    }

    public function test_la_planilla_incluye_las_edades_del_impreso(): void
    {
        $etiquetas = array_column(EstadisticaPorEdadDatos::filasDefinicion(), 'etiqueta');

        $this->assertSame('Menos de 3 años', $etiquetas[0]);
        $this->assertContains('3 años', $etiquetas);
        $this->assertContains('19 años', $etiquetas);
        $this->assertContains('20 a 25 años', $etiquetas);
        $this->assertContains('Más de 25 años', $etiquetas);
        $this->assertContains('Sin fecha de nacimiento', $etiquetas);
    }
}
