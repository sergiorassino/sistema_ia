<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\SiroIdFactura;
use PHPUnit\Framework\TestCase;

class SiroIdFacturaTest extends TestCase
{
    public function test_generar_legacy_id_cuotas_chico(): void
    {
        $this->assertSame(
            '00002530000008605086',
            SiroIdFactura::generar(2530, 86, 5),
        );
    }

    public function test_generar_diferenciador_cinco_digitos_con_id_cuotas_grande(): void
    {
        $id = SiroIdFactura::generar(4810313, 1098, 1);

        $this->assertSame(20, strlen($id));
        $this->assertSame('04810313000109801098', $id);
        $this->assertSame('01098', substr($id, 15, 5));
    }
}
