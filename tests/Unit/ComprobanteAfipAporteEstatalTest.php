<?php

namespace Tests\Unit;

use App\Support\Cuotas\ComprobanteAfipDatos;
use Tests\TestCase;

class ComprobanteAfipAporteEstatalTest extends TestCase
{
    public function test_formatea_valores_tipicos_a_porcentaje(): void
    {
        $this->assertSame('', ComprobanteAfipDatos::formatearAporteEstatalParaPdf(''));
        $this->assertSame('100%', ComprobanteAfipDatos::formatearAporteEstatalParaPdf('100'));
        $this->assertSame('50%', ComprobanteAfipDatos::formatearAporteEstatalParaPdf('50'));
        $this->assertSame('100%', ComprobanteAfipDatos::formatearAporteEstatalParaPdf('100%'));
        $this->assertSame('50%', ComprobanteAfipDatos::formatearAporteEstatalParaPdf('50 %'));
        $this->assertSame('50%', ComprobanteAfipDatos::formatearAporteEstatalParaPdf('50,00'));
        $this->assertSame('100%', ComprobanteAfipDatos::formatearAporteEstatalParaPdf('100.00'));
    }

    public function test_el_aporte_del_nivel_del_alumno_prevalece_sobre_el_snapshot(): void
    {
        $this->assertSame(
            '50%',
            ComprobanteAfipDatos::resolverAporteEstatalParaPdf('50', '100', '100'),
        );
    }

    public function test_sin_aporte_del_alumno_usa_el_snapshot_guardado(): void
    {
        $this->assertSame(
            '100%',
            ComprobanteAfipDatos::resolverAporteEstatalParaPdf('', '100', '80'),
        );
    }

    public function test_flag_de_impresion_apagado_por_default(): void
    {
        config(['tenant.cuotas.facturacion_afip.mostrar_aporte_estatal' => false]);

        $this->assertFalse(tenantCuotasFacturacionAfipMuestraAporteEstatal());
    }

    public function test_flag_de_impresion_se_activa_por_tenant(): void
    {
        config(['tenant.cuotas.facturacion_afip.mostrar_aporte_estatal' => true]);

        $this->assertTrue(tenantCuotasFacturacionAfipMuestraAporteEstatal());
    }
}
