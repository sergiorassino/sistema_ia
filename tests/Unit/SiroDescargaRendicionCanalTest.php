<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionCanal;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class SiroDescargaRendicionCanalTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setMapaAbrev(null);
        parent::tearDown();
    }

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

    public function test_es_medio_pago_conocido_solo_si_esta_en_mapa(): void
    {
        $this->setMapaAbrev(['BPD' => 1, 'TQR' => 2, 'PF' => 3]);

        $this->assertTrue(SiroDescargaRendicionCanal::esMedioPagoConocido('BPD'));
        $this->assertTrue(SiroDescargaRendicionCanal::esMedioPagoConocido('tqr'));
        $this->assertFalse(SiroDescargaRendicionCanal::esMedioPagoConocido('BPR'));
        $this->assertFalse(SiroDescargaRendicionCanal::esMedioPagoConocido('DDR'));
        $this->assertFalse(SiroDescargaRendicionCanal::esMedioPagoConocido(''));
    }

    public function test_detalle_rechazo_canal_incluye_texto_siro(): void
    {
        $detalle = SiroDescargaRendicionCanal::detalleRechazoCanal('BPR', '402SERVICIO INVALIDO');

        $this->assertStringContainsString('BPR', $detalle);
        $this->assertStringContainsString('rechazo SIRO', $detalle);
        $this->assertStringContainsString('402SERVICIO INVALIDO', $detalle);
    }

    /** @param  array<string, int>|null  $mapa */
    private function setMapaAbrev(?array $mapa): void
    {
        $prop = new ReflectionProperty(SiroDescargaRendicionCanal::class, 'porAbrev');
        $prop->setAccessible(true);
        $prop->setValue(null, $mapa);
    }
}
