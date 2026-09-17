<?php

namespace Tests\Unit;

use App\Support\Cuotas\EstadisticaPagoCuotasDatos;
use PHPUnit\Framework\TestCase;

class EstadisticaPagoCuotasDatosTest extends TestCase
{
    public function test_esta_pagada_si_faltapa_es_cero_o_negativo(): void
    {
        $this->assertTrue(EstadisticaPagoCuotasDatos::estaPagada(0));
        $this->assertTrue(EstadisticaPagoCuotasDatos::estaPagada(0.004));
        $this->assertTrue(EstadisticaPagoCuotasDatos::estaPagada(-15.5));
        $this->assertFalse(EstadisticaPagoCuotasDatos::estaPagada(0.01));
        $this->assertFalse(EstadisticaPagoCuotasDatos::estaPagada(1200));
    }

    public function test_porcentaje_redondea_un_decimal(): void
    {
        $this->assertSame(0.0, EstadisticaPagoCuotasDatos::porcentaje(0, 0));
        $this->assertSame(0.0, EstadisticaPagoCuotasDatos::porcentaje(5, 0));
        $this->assertSame(50.0, EstadisticaPagoCuotasDatos::porcentaje(1, 2));
        $this->assertSame(66.7, EstadisticaPagoCuotasDatos::porcentaje(2, 3));
        $this->assertSame(100.0, EstadisticaPagoCuotasDatos::porcentaje(10, 10));
    }

    public function test_porcentaje_complemento_cierra_en_100(): void
    {
        $this->assertSame(100.0, EstadisticaPagoCuotasDatos::porcentajeComplemento(0));
        $this->assertSame(0.0, EstadisticaPagoCuotasDatos::porcentajeComplemento(100));
        $this->assertSame(33.3, EstadisticaPagoCuotasDatos::porcentajeComplemento(66.7));
    }

    public function test_armar_fila_calcula_porcentajes_de_cantidad_y_cobro(): void
    {
        $fila = EstadisticaPagoCuotasDatos::armarFila(12, 'Marzo · Cuota', [
            'total' => 10,
            'pagadas' => 7,
            'no_pagadas' => 3,
            'importe' => 10000,
            'pagado' => 7500,
            'saldo' => 2500,
        ]);

        $this->assertSame(12, $fila['id']);
        $this->assertSame('Marzo · Cuota', $fila['etiqueta']);
        $this->assertSame(10, $fila['total']);
        $this->assertSame(7, $fila['pagadas']);
        $this->assertSame(3, $fila['no_pagadas']);
        $this->assertSame(70.0, $fila['pct_pagadas']);
        $this->assertSame(30.0, $fila['pct_no_pagadas']);
        $this->assertSame(75.0, $fila['pct_cobrado']);
    }

    public function test_armar_fila_todas_impagas_da_cero_por_ciento(): void
    {
        $fila = EstadisticaPagoCuotasDatos::armarFila(3, 'Abril', [
            'total' => 8,
            'pagadas' => 0,
            'no_pagadas' => 8,
            'importe' => 8000,
            'pagado' => 0,
            'saldo' => 8000,
        ]);

        $this->assertSame(0.0, $fila['pct_pagadas']);
        $this->assertSame(100.0, $fila['pct_no_pagadas']);
        $this->assertSame(0.0, $fila['pct_cobrado']);
    }

    public function test_chart_barras_y_dona_usan_porcentajes_de_pago(): void
    {
        $filas = [
            EstadisticaPagoCuotasDatos::armarFila(1, 'Marzo', [
                'total' => 4,
                'pagadas' => 3,
                'no_pagadas' => 1,
                'importe' => 400,
                'pagado' => 300,
                'saldo' => 100,
            ]),
        ];

        $barras = EstadisticaPagoCuotasDatos::chartBarrasComparativo($filas);
        $this->assertSame(['Marzo'], $barras['labels']);
        $this->assertSame([75.0], $barras['datasets'][0]['data']);
        $this->assertSame([25.0], $barras['datasets'][1]['data']);

        $dona = EstadisticaPagoCuotasDatos::chartDona($filas[0]);
        $this->assertSame(['Pagadas', 'No pagadas'], $dona['labels']);
        $this->assertSame([75.0, 25.0], $dona['datasets'][0]['data']);
    }

    public function test_ordenar_ids_sigue_el_campo_orden_de_la_cuota(): void
    {
        $this->assertSame(
            [30, 10, 20],
            EstadisticaPagoCuotasDatos::ordenarIdsPorCampoOrden(
                [20, 30, 10],
                [10 => 2, 20 => 5, 30 => 1],
            ),
        );
        $this->assertSame(
            [2, 8],
            EstadisticaPagoCuotasDatos::ordenarIdsPorCampoOrden([8, 2], [2 => 4, 8 => 4]),
        );
    }
}
