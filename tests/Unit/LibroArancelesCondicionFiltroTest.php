<?php

namespace Tests\Unit;

use App\Support\Cuotas\LibroArancelesDatos;
use Tests\TestCase;

class LibroArancelesCondicionFiltroTest extends TestCase
{
    public function test_por_defecto_incluye_condiciones_1_a_4(): void
    {
        config(['tenant.cuotas.libro_aranceles.solo_regulares' => false]);

        $this->assertFalse(tenantCuotasLibroArancelesSoloRegulares());
        $this->assertSame([1, 2, 3, 4], LibroArancelesDatos::idsCondicionesParaQuery());
    }

    public function test_con_solo_regulares_incluye_solo_condicion_1(): void
    {
        config(['tenant.cuotas.libro_aranceles.solo_regulares' => true]);

        $this->assertTrue(tenantCuotasLibroArancelesSoloRegulares());
        $this->assertSame([1], LibroArancelesDatos::idsCondicionesParaQuery());
    }
}
