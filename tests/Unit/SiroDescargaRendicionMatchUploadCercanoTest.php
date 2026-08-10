<?php

namespace Tests\Unit;

use App\Models\CuponAPagar;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionMatchUploadCercano;
use App\Support\Cuotas\Siro\SiroIdFactura;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionMatchUploadCercanoTest extends TestCase
{
    public function test_elige_upload_mas_cercano(): void
    {
        $buscado = SiroIdFactura::generar(2530, 86, 5);
        $cercano = $this->cupon(SiroIdFactura::generar(2530, 86, 4), 1000.0, 1);
        $lejano = $this->cupon(SiroIdFactura::generar(2530, 86, 1), 1000.0, 2);

        $elegido = SiroDescargaRendicionMatchUploadCercano::elegirMasCercano(
            new Collection([$lejano, $cercano]),
            5,
            1000.0,
        );

        $this->assertNotNull($elegido);
        $this->assertSame((string) $cercano->id_factura, (string) $elegido['cupon']->id_factura);
        $this->assertSame(4, $elegido['ultUpload']);
        $this->assertNotSame($buscado, (string) $elegido['cupon']->id_factura);
    }

    public function test_ante_misma_distancia_prefiere_importe_coincidente(): void
    {
        $sinImporte = $this->cupon(SiroIdFactura::generar(2530, 86, 4), 500.0, 1);
        $conImporte = $this->cupon(SiroIdFactura::generar(2530, 86, 6), 1000.0, 2);

        $elegido = SiroDescargaRendicionMatchUploadCercano::elegirMasCercano(
            new Collection([$sinImporte, $conImporte]),
            5,
            1000.0,
        );

        $this->assertNotNull($elegido);
        $this->assertSame((string) $conImporte->id_factura, (string) $elegido['cupon']->id_factura);
    }

    public function test_importe_coincide_con_vencimientos_o_saldo(): void
    {
        $cupon = $this->cupon(SiroIdFactura::generar(10, 86, 1), 0.0, 1);
        $cupon->importe2venc = 1500.55;

        $this->assertTrue(
            SiroDescargaRendicionMatchUploadCercano::importeCoincideConCupon(1500.55, $cupon),
        );
        $this->assertFalse(
            SiroDescargaRendicionMatchUploadCercano::importeCoincideConCupon(1500.00, $cupon),
        );
    }

    public function test_importe_con_bonificacion_usa_importe1venc_no_saldo(): void
    {
        $cupon = $this->cupon(SiroIdFactura::generar(10, 86, 1), 111000.0, 1);
        $cupon->importe1venc = 99900.0;

        $this->assertTrue(
            SiroDescargaRendicionMatchUploadCercano::importeCoincideConCupon(99900.0, $cupon),
        );
        $this->assertSame(
            99900.0,
            SiroDescargaRendicionMatchUploadCercano::importeReferenciaCupon($cupon, 99900.0),
        );
    }

    public function test_aviso_formulario_menciona_puesta_en_marcha(): void
    {
        $mensaje = SiroDescargaRendicionMatchUploadCercano::mensajeAvisoFormulario();

        $this->assertStringContainsString('puesta en marcha', mb_strtolower($mensaje));
        $this->assertStringContainsString('upload', mb_strtolower($mensaje));
    }

    private function cupon(string $idFactura, float $saldo, int $id): CuponAPagar
    {
        $cupon = new CuponAPagar([
            'id_factura' => $idFactura,
            'saldo_pagar' => $saldo,
            'importe1venc' => $saldo,
            'importe2venc' => 0,
            'importe3venc' => 0,
            'ult_upload' => (int) substr($idFactura, 15, 2),
        ]);
        $cupon->id = $id;

        return $cupon;
    }
}
