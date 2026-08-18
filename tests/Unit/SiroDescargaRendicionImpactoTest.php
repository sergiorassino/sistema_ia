<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionImpacto;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionImpactoTest extends TestCase
{
    public function test_aviso_cadena_ya_imputada_no_bloquea_el_impacto(): void
    {
        $this->assertNull(SiroDescargaRendicionImpacto::avisoCadenaYaEnCuotaspagos(false));
        $this->assertSame(
            'Pago duplicado: misma cadena SIRO ya imputada; se impacta igual y el saldo puede quedar negativo.',
            SiroDescargaRendicionImpacto::avisoCadenaYaEnCuotaspagos(true),
        );
    }

    public function test_limpiar_obs_quita_omitido_por_misma_cadena(): void
    {
        $obs = 'PAGO DUPLICADO: La cuota ya tiene otro pago en este archivo (registro 6); se registra igual (posible pago doble).'
            .' | Pago ya registrado en cuotaspagos (misma cadena).';

        $this->assertSame(
            'PAGO DUPLICADO: La cuota ya tiene otro pago en este archivo (registro 6); se registra igual (posible pago doble).',
            SiroDescargaRendicionImpacto::limpiarObsTrasImpacto($obs),
        );
    }
}
