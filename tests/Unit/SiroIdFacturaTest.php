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

    public function test_generar_decodea_el_id_factura_duplicado_de_impresion(): void
    {
        $this->assertSame(
            '00002648000009101091',
            SiroIdFactura::generar(2648, 91, 1),
        );
    }

    public function test_siguiente_ult_upload_salta_el_numero_ya_ocupado(): void
    {
        $this->assertSame(1, SiroIdFactura::siguienteUltUploadLibre(0, []));
        $this->assertSame(2, SiroIdFactura::siguienteUltUploadLibre(0, [1]));
        $this->assertSame(7, SiroIdFactura::siguienteUltUploadLibre(5, [1, 6]));
        $this->assertSame(
            '00002648000009102091',
            SiroIdFactura::generar(2648, 91, SiroIdFactura::siguienteUltUploadLibre(0, [1])),
        );
    }

    public function test_siguiente_ult_upload_sin_numeros_libres_falla(): void
    {
        $this->expectException(\RuntimeException::class);

        SiroIdFactura::siguienteUltUploadLibre(99, []);
    }

    public function test_partes_cadena_separa_upload(): void
    {
        $id = SiroIdFactura::generar(2530, 86, 5);
        $partes = SiroIdFactura::partesCadena($id);

        $this->assertNotNull($partes);
        $this->assertSame('000025300000086', $partes['prefijoSinUpload']);
        $this->assertSame(5, $partes['ultUpload']);
        $this->assertSame('086', $partes['sufijoCuota']);
    }
}
