<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionBarcodeComprobante448;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionBarcodeFamilia;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionIdFactura;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionLinea;
use App\Support\Cuotas\Siro\SiroIdFactura;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionBarcodeFamiliaTest extends TestCase
{
    public function test_clasifica_prefijos_siro(): void
    {
        $this->assertSame(
            SiroDescargaRendicionBarcodeFamilia::CUPON_448,
            SiroDescargaRendicionBarcodeFamilia::desdeCodigoBarras('04481032840860000002606120010980000140012200000515001102925'),
        );
        $this->assertSame(
            SiroDescargaRendicionBarcodeFamilia::ELECTRONICO_449,
            SiroDescargaRendicionBarcodeFamilia::desdeCodigoBarras('04490900011672606120009990000140011100000515001105292'),
        );
        $this->assertSame(
            SiroDescargaRendicionBarcodeFamilia::DESCONOCIDO,
            SiroDescargaRendicionBarcodeFamilia::desdeCodigoBarras('1234'),
        );
    }

    public function test_0448_no_usa_id_comprobante_aunque_coincida_bpd(): void
    {
        $barcode = '04481032840860000002606120010980000140012200000515001102925';
        $parsed = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($barcode);
        $this->assertNotNull($parsed);

        $this->assertSame(
            SiroIdFactura::generar(3284, 86, 1),
            SiroDescargaRendicionBarcodeComprobante448::idFacturaDesdeParseLegacy($parsed, 1),
        );
    }

    public function test_0448_sin_ciclo_activo_no_arma_id_factura(): void
    {
        $linea = [
            'idComprobante' => '00000000000000000000',
            'idUsuario' => '86000000',
            'codigoBarras' => '04481032840860000002606120010980000140012200000515001102925',
        ];

        $this->assertNull(SiroDescargaRendicionIdFactura::principalDesdeLinea($linea));
        $this->assertSame([], SiroDescargaRendicionIdFactura::candidatosDesdeLinea($linea));
    }

    public function test_0449_usa_id_comprobante_no_barcode_448(): void
    {
        $linea = [
            'idComprobante' => '00000000086308601086',
            'idUsuario' => '90001167',
            'codigoBarras' => '04490900011672606120009990000140011100000515001105292',
        ];

        $this->assertSame(
            SiroIdFactura::generar(1167, 86, 1),
            SiroDescargaRendicionIdFactura::principalDesdeLinea($linea),
        );
    }

    public function test_archivo_1102_bpd_ruta_electronica(): void
    {
        $f = 'd:/_enviar/_06-Junio/sanfra/nuevo/CobranzasSiro_Cta. 1102_20260625txt.txt';
        if (! is_readable($f)) {
            $this->markTestSkipped('Archivo de rendición de ejemplo no disponible.');
        }

        $lineas = file($f);
        $linea2 = SiroDescargaRendicionLinea::parsear(rtrim($lineas[1], "\r\n"));
        $this->assertNotNull($linea2);
        $this->assertTrue(
            SiroDescargaRendicionBarcodeFamilia::esElectronico(
                SiroDescargaRendicionIdFactura::familiaDesdeLinea($linea2),
            ),
        );
        $this->assertSame(
            SiroIdFactura::generar(1167, 86, 1),
            SiroDescargaRendicionIdFactura::principalDesdeLinea($linea2),
        );
    }
}
