<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionCupon;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionIdComprobante;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionLinea;
use App\Support\Cuotas\Siro\SiroIdFactura;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionIdComprobanteTest extends TestCase
{
    public function test_bpd_reconstruye_id_factura_desde_comprobante_sin_legajo_e_id_usuario(): void
    {
        $idFactura = SiroDescargaRendicionIdComprobante::idFacturaDesdeLinea(
            '00000000086308601086',
            '90001167',
        );

        $this->assertSame(SiroIdFactura::generar(1167, 86, 1), $idFactura);
    }

    public function test_bpd_segundo_pago_usa_otro_legajo(): void
    {
        $idFactura = SiroDescargaRendicionIdComprobante::idFacturaDesdeLinea(
            '00000000086308601086',
            '90001482',
        );

        $this->assertSame(SiroIdFactura::generar(1482, 86, 1), $idFactura);
    }

    public function test_id_factura_completo_no_se_reinterpreta_como_comprobante_sin_legajo(): void
    {
        $id = SiroIdFactura::generar(2530, 86, 5);

        $this->assertSame($id, SiroDescargaRendicionIdComprobante::idFacturaDesdeLinea($id, '90002530'));
    }

    public function test_rechaza_comprobante_vacio(): void
    {
        $this->assertNull(SiroDescargaRendicionIdComprobante::idFacturaDesdeLinea('00000000000000000000', '90001167'));
    }
}
