<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionCanal;
use Tests\TestCase;

class SiroDescargaRendicionCanalTest extends TestCase
{
    public function test_coincide_canal_planilla_por_abrev(): void
    {
        $opcion = ['abrev' => 'ROE', 'tipoPago' => 'Banco Roela'];

        $this->assertTrue(SiroDescargaRendicionCanal::coincideCanalPlanilla($opcion, ['ROE']));
        $this->assertFalse(SiroDescargaRendicionCanal::coincideCanalPlanilla($opcion, ['PF']));
    }

    public function test_coincide_canal_planilla_por_nombre(): void
    {
        $opcion = ['abrev' => 'ROE', 'tipoPago' => 'Roela'];

        $this->assertTrue(SiroDescargaRendicionCanal::coincideCanalPlanilla($opcion, ['Roela']));
        $this->assertTrue(SiroDescargaRendicionCanal::coincideCanalPlanilla($opcion, ['roela']));
    }
}
