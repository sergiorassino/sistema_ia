<?php

namespace Tests\Unit;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionCalculo;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionMatchCuotaSinCupon448;
use Tests\TestCase;

class SiroDescargaRendicionCalculoTest extends TestCase
{
    public function test_desglose_con_bonificacion_desde_cupon(): void
    {
        $cuota = new CuotaGenerada([
            'id' => 1,
            'faltapa' => 111000.0,
        ]);

        $cupon = new CuponAPagar([
            'id_factura' => '00000999000008801088',
            'saldo_pagar' => 111000.0,
            'fecha1venc' => '2026-08-13',
            'importe1venc' => 99900.0,
            'fecha2venc' => '2026-08-20',
            'importe2venc' => 111000.0,
            'fecha3venc' => '2026-08-27',
            'importe3venc' => 111000.0,
        ]);

        $resultado = SiroDescargaRendicionCalculo::calcular(
            ['importePagadoCentavos' => 9990000, 'fechaPago' => '20260807'],
            $cuota,
            $cupon,
        );

        $this->assertTrue($resultado['descargable']);
        $this->assertSame(111000.0, $resultado['importe']);
        $this->assertSame(99900.0, $resultado['pagado']);
        $this->assertSame(0.0, $resultado['interes']);
        $this->assertSame(11100.0, $resultado['bonificacion']);
        $this->assertSame([], $resultado['advertencias']);
    }

    public function test_pago_repetido_usa_mismo_desglose_del_cupon(): void
    {
        $cuota = new CuotaGenerada([
            'id' => 2,
            'faltapa' => 0.0,
        ]);

        $cupon = new CuponAPagar([
            'id_factura' => '00000999000008801088',
            'saldo_pagar' => 111000.0,
            'fecha1venc' => '2026-08-13',
            'importe1venc' => 99900.0,
            'fecha2venc' => '2026-08-20',
            'importe2venc' => 111000.0,
            'fecha3venc' => '2026-08-27',
            'importe3venc' => 111000.0,
        ]);

        $resultado = SiroDescargaRendicionCalculo::calcular(
            ['importePagadoCentavos' => 9990000, 'fechaPago' => '20260807'],
            $cuota,
            $cupon,
        );

        $this->assertTrue($resultado['descargable']);
        $this->assertSame(111000.0, $resultado['importe']);
        $this->assertSame(99900.0, $resultado['pagado']);
        $this->assertSame(11100.0, $resultado['bonificacion']);
        $this->assertSame(0.0, $resultado['interes']);
        $this->assertNotEmpty($resultado['advertencias']);
        $this->assertStringContainsString('posible pago doble', $resultado['advertencias'][0]);
    }

    public function test_desglose_con_interes_desde_cupon(): void
    {
        $cuota = new CuotaGenerada([
            'id' => 3,
            'faltapa' => 108000.0,
        ]);

        $cupon = new CuponAPagar([
            'id_factura' => '00000123000001001088',
            'saldo_pagar' => 108000.0,
            'fecha1venc' => '2026-04-10',
            'importe1venc' => 108000.0,
            'fecha2venc' => '2026-05-10',
            'importe2venc' => 108000.0,
            'fecha3venc' => '2026-06-24',
            'importe3venc' => 123552.0,
        ]);

        $resultado = SiroDescargaRendicionCalculo::calcular(
            ['importePagadoCentavos' => 12355200, 'fechaPago' => '20260624'],
            $cuota,
            $cupon,
        );

        $this->assertTrue($resultado['descargable']);
        $this->assertSame(108000.0, $resultado['importe']);
        $this->assertSame(123552.0, $resultado['pagado']);
        $this->assertSame(15552.0, $resultado['interes']);
        $this->assertSame(0.0, $resultado['bonificacion']);
    }

    public function test_sin_cupon_no_es_descargable(): void
    {
        $cuota = new CuotaGenerada(['id' => 4, 'faltapa' => 1000.0]);

        $resultado = SiroDescargaRendicionCalculo::calcular(
            ['importePagadoCentavos' => 100000, 'fechaPago' => '20260807'],
            $cuota,
            null,
        );

        $this->assertFalse($resultado['descargable']);
        $this->assertSame(0.0, $resultado['importe']);
        $this->assertStringContainsString('cupones_a_pagar', $resultado['advertencias'][0]);
    }

    public function test_provisorio_448_sin_cupon_descarga_importe_archivo(): void
    {
        $cuota = new CuotaGenerada([
            'id' => 40,
            'faltapa' => 109800.0,
            'importe' => 109800.0,
        ]);

        $resultado = SiroDescargaRendicionCalculo::calcular(
            [
                'importePagadoCentavos' => 12200000,
                'fechaPago' => '20260810',
                'codigoBarras' => '04481013760880000002608130010980000140012200000515001102940',
            ],
            $cuota,
            null,
            SiroDescargaRendicionMatchCuotaSinCupon448::MATCH_TIPO,
        );

        $this->assertTrue($resultado['descargable']);
        $this->assertSame(109800.0, $resultado['importe']);
        $this->assertSame(122000.0, $resultado['pagado']);
        $this->assertSame(12200.0, $resultado['interes']);
        $this->assertSame(0.0, $resultado['bonificacion']);
        $this->assertStringContainsString('Provisorio 448', $resultado['advertencias'][0]);
    }

    public function test_provisorio_448_importe_distinto_al_cupon_usa_archivo(): void
    {
        $cuota = new CuotaGenerada(['id' => 41, 'faltapa' => 109800.0]);
        $cupon = new CuponAPagar([
            'id_factura' => '00001376000008801088',
            'origen' => CuponAPagar::ORIGEN_SUBIDA_SIRO,
            'saldo_pagar' => 109800.0,
            'fecha1venc' => '2026-08-13',
            'importe1venc' => 98700.0,
            'fecha2venc' => '2026-08-20',
            'importe2venc' => 109800.0,
            'fecha3venc' => '2026-08-27',
            'importe3venc' => 109800.0,
        ]);

        $resultado = SiroDescargaRendicionCalculo::calcular(
            [
                'importePagadoCentavos' => 12200000,
                'fechaPago' => '20260810',
                'codigoBarras' => '04481013760880000002608130010980000140012200000515001102940',
            ],
            $cuota,
            $cupon,
        );

        $this->assertTrue($resultado['descargable']);
        $this->assertSame(109800.0, $resultado['importe']);
        $this->assertSame(122000.0, $resultado['pagado']);
        $this->assertSame(12200.0, $resultado['interes']);
        $this->assertStringContainsString('Match provisorio', $resultado['advertencias'][0]);
    }

    public function test_cupon_autogestion_con_importe_distinto_usa_provisorio_2(): void
    {
        $cuota = new CuotaGenerada(['id' => 42, 'faltapa' => 109800.0]);
        $cupon = new CuponAPagar([
            'id_factura' => '00001376000008802088',
            'origen' => CuponAPagar::ORIGEN_IMPRESION_AUTOGESTION,
            'saldo_pagar' => 109800.0,
            'fecha1venc' => '2026-08-13',
            'importe1venc' => 98700.0,
            'fecha2venc' => '2026-08-20',
            'importe2venc' => 109800.0,
            'fecha3venc' => '2026-08-27',
            'importe3venc' => 109800.0,
        ]);

        $resultado = SiroDescargaRendicionCalculo::calcular(
            [
                'importePagadoCentavos' => 12200000,
                'fechaPago' => '20260810',
                'codigoBarras' => '04481013760880000002608130010980000140012200000515001102940',
            ],
            $cuota,
            $cupon,
        );

        $this->assertTrue($resultado['descargable']);
        $this->assertSame(109800.0, $resultado['importe']);
        $this->assertSame(122000.0, $resultado['pagado']);
        $this->assertSame(12200.0, $resultado['interes']);
        $this->assertStringContainsString('Match provisorio', $resultado['advertencias'][0]);
    }

    public function test_cupon_autogestion_con_importe_coincidente_usa_el_registro(): void
    {
        $cuota = new CuotaGenerada(['id' => 43, 'faltapa' => 109800.0]);
        $cupon = new CuponAPagar([
            'id_factura' => '00001376000008802088',
            'origen' => CuponAPagar::ORIGEN_IMPRESION_AUTOGESTION,
            'saldo_pagar' => 109800.0,
            'fecha1venc' => '2026-08-13',
            'importe1venc' => 98700.0,
            'fecha2venc' => '2026-08-20',
            'importe2venc' => 109800.0,
            'fecha3venc' => '2026-08-27',
            'importe3venc' => 122000.0,
        ]);

        $resultado = SiroDescargaRendicionCalculo::calcular(
            [
                'importePagadoCentavos' => 12200000,
                'fechaPago' => '20260810',
                'codigoBarras' => '04481013760880000002608130010980000140012200000515001102940',
            ],
            $cuota,
            $cupon,
        );

        $this->assertTrue($resultado['descargable']);
        $this->assertSame(109800.0, $resultado['importe']);
        $this->assertSame(122000.0, $resultado['pagado']);
        $this->assertSame(12200.0, $resultado['interes']);
        $this->assertSame([], $resultado['advertencias']);
    }

    public function test_449_importe_distinto_al_cupon_usa_archivo_en_puesta_en_marcha(): void
    {
        $cuota = new CuotaGenerada(['id' => 45, 'faltapa' => 111000.0]);
        $cupon = new CuponAPagar([
            'id_factura' => '00001735000008703087',
            'saldo_pagar' => 111000.0,
            'fecha1venc' => '2026-08-13',
            'importe1venc' => 99900.0,
            'fecha2venc' => '2026-08-20',
            'importe2venc' => 111000.0,
            'fecha3venc' => '2026-08-27',
            'importe3venc' => 111000.0,
        ]);

        $resultado = SiroDescargaRendicionCalculo::calcular(
            [
                'importePagadoCentavos' => 11721600,
                'fechaPago' => '20260807',
                'codigoBarras' => '044909000173526081011721600000000000000000000515001105240',
            ],
            $cuota,
            $cupon,
        );

        $this->assertTrue($resultado['descargable']);
        $this->assertSame(111000.0, $resultado['importe']);
        $this->assertSame(117216.0, $resultado['pagado']);
        $this->assertSame(6216.0, $resultado['interes']);
        $this->assertStringContainsString('Match provisorio', $resultado['advertencias'][0]);
        $this->assertTrue($resultado['provisorioImporteArchivo']);
    }

    public function test_importe_no_coincide_con_cupon_no_es_descargable(): void
    {
        $cuota = new CuotaGenerada(['id' => 5, 'faltapa' => 111000.0]);
        $cupon = new CuponAPagar([
            'id_factura' => '00000999000008801088',
            'saldo_pagar' => 111000.0,
            'fecha1venc' => '2026-08-13',
            'importe1venc' => 99900.0,
            'fecha2venc' => '2026-08-20',
            'importe2venc' => 111000.0,
            'fecha3venc' => '2026-08-27',
            'importe3venc' => 111000.0,
        ]);

        $resultado = SiroDescargaRendicionCalculo::calcular(
            ['importePagadoCentavos' => 5000000, 'fechaPago' => '20260807'],
            $cuota,
            $cupon,
        );

        $this->assertFalse($resultado['descargable']);
        $this->assertStringContainsString('no coincide', $resultado['advertencias'][0]);
    }

    public function test_desglose_desde_capital_y_pagado(): void
    {
        $conBonif = SiroDescargaRendicionCalculo::desgloseDesdeCapitalYPagado(111000.0, 99900.0);
        $this->assertSame(111000.0, $conBonif['importe']);
        $this->assertSame(11100.0, $conBonif['bonificacion']);
        $this->assertSame(0.0, $conBonif['interes']);

        $conInteres = SiroDescargaRendicionCalculo::desgloseDesdeCapitalYPagado(108000.0, 123552.0);
        $this->assertSame(108000.0, $conInteres['importe']);
        $this->assertSame(15552.0, $conInteres['interes']);
        $this->assertSame(0.0, $conInteres['bonificacion']);
    }
}
