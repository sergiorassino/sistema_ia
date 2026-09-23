<?php

namespace Tests\Unit;

use App\Support\Cuotas\EstadisticaPagoPorCursoDatos;
use PHPUnit\Framework\TestCase;

class EstadisticaPagoPorCursoDatosTest extends TestCase
{
    public function test_porcentaje_deuda_es_cero_si_no_hay_importe_esperado(): void
    {
        $this->assertSame(0.0, EstadisticaPagoPorCursoDatos::porcentajeDeuda(500, 0));
        $this->assertSame(6.73, EstadisticaPagoPorCursoDatos::porcentajeDeuda(121000, 1796850));
    }

    public function test_esperado_es_la_suma_facturada_y_el_abonado_resta_intereses(): void
    {
        $celda = EstadisticaPagoPorCursoDatos::armarCelda([
            'cant' => 14,
            'cant_pag' => 14,
            'importe' => 1694000,
            'pagado' => 1548800,
            'interes' => 0,
            'cant_bon' => 12,
            'cant_sin_bon' => 2,
            'adeudado' => 0,
        ]);

        $this->assertSame(1694000.0, $celda['esperado']);
        $this->assertSame(1548800.0, $celda['abonado']);
        $this->assertSame(0.0, $celda['porcentaje']);
        $this->assertSame(12, $celda['cant_bon']);
        $this->assertSame(2, $celda['cant_sin_bon']);
    }

    public function test_abonado_no_incluye_los_intereses_cobrados(): void
    {
        $celda = EstadisticaPagoPorCursoDatos::armarCelda([
            'cant' => 15,
            'cant_pag' => 14,
            'importe' => 1796850,
            'pagado' => 1570943,
            'interes' => 26378,
            'cant_bon' => 11,
            'cant_sin_bon' => 3,
            'adeudado' => 121000,
        ]);

        $this->assertSame(1796850.0, $celda['esperado']);
        $this->assertSame(1544565.0, $celda['abonado']);
        $this->assertSame(121000.0, $celda['adeudado']);
        $this->assertSame(6.73, $celda['porcentaje']);
    }

    public function test_totales_recalculan_el_porcentaje_sobre_la_suma(): void
    {
        $a = EstadisticaPagoPorCursoDatos::armarCelda([
            'cant' => 10,
            'cant_pag' => 8,
            'importe' => 1000,
            'pagado' => 700,
            'interes' => 0,
            'cant_bon' => 0,
            'cant_sin_bon' => 8,
            'adeudado' => 300,
        ]);
        $b = EstadisticaPagoPorCursoDatos::armarCelda([
            'cant' => 5,
            'cant_pag' => 5,
            'importe' => 500,
            'pagado' => 500,
            'interes' => 20,
            'cant_bon' => 1,
            'cant_sin_bon' => 4,
            'adeudado' => 0,
        ]);

        $total = EstadisticaPagoPorCursoDatos::sumarCeldas([$a, $b]);

        $this->assertSame(15, $total['cant']);
        $this->assertSame(13, $total['cant_pag']);
        $this->assertSame(1500.0, $total['esperado']);
        $this->assertSame(1180.0, $total['abonado']);
        $this->assertSame(1, $total['cant_bon']);
        $this->assertSame(12, $total['cant_sin_bon']);
        $this->assertSame(300.0, $total['adeudado']);
        $this->assertSame(20.0, $total['porcentaje']);
    }
}
