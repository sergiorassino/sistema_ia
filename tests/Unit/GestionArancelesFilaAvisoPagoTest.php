<?php

namespace Tests\Unit;

use App\Models\CuotaGenerada;
use App\Support\Cuotas\GestionAranceles;
use PHPUnit\Framework\TestCase;

class GestionArancelesFilaAvisoPagoTest extends TestCase
{
    public function test_amarillo_solo_si_aviso_pago_y_cuota_impaga(): void
    {
        $impaga = new CuotaGenerada(['avisoPago' => 1, 'faltapa' => 1500.0]);
        $this->assertTrue(GestionAranceles::filaAvisoPago($impaga));
        $this->assertFalse(GestionAranceles::filaPagada($impaga));
    }

    public function test_pagada_con_aviso_pago_no_se_marca_amarillo(): void
    {
        $saldada = new CuotaGenerada(['avisoPago' => 1, 'faltapa' => 0.0]);
        $this->assertFalse(GestionAranceles::filaAvisoPago($saldada));
        $this->assertTrue(GestionAranceles::filaPagada($saldada));
    }

    public function test_impaga_sin_aviso_pago_no_se_marca_amarillo(): void
    {
        $impaga = new CuotaGenerada(['avisoPago' => 0, 'faltapa' => 800.0]);
        $this->assertFalse(GestionAranceles::filaAvisoPago($impaga));
    }
}
