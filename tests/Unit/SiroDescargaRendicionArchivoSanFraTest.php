<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionBarcodeComprobante448;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionIdFactura;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionLinea;
use App\Support\Cuotas\Siro\SiroIdFactura;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionArchivoSanFraTest extends TestCase
{
    private const ARCHIVO = 'd:/_enviar/_06-Junio/sanfra/nuevo/CobranzasSiro_Cta. 1102_20260625txt.txt';

    public function test_archivo_san_fra_bpd_y_pf_claves(): void
    {
        if (! is_readable(self::ARCHIVO)) {
            $this->markTestSkipped('Archivo de rendición de ejemplo no disponible.');
        }

        $lineas = file(self::ARCHIVO);
        $this->assertCount(5, $lineas);

        $linea2 = SiroDescargaRendicionLinea::parsear(rtrim($lineas[1], "\r\n"));
        $this->assertNotNull($linea2);
        $this->assertSame(
            SiroIdFactura::generar(1167, 86, 1),
            SiroDescargaRendicionIdFactura::principalDesdeLinea($linea2),
        );

        $linea1 = SiroDescargaRendicionLinea::parsear(rtrim($lineas[0], "\r\n"));
        $this->assertNotNull($linea1);
        $this->assertStringStartsWith('0448103284086000000', $linea1['codigoBarras']);
        $parsed1 = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($linea1['codigoBarras']);
        $this->assertNotNull($parsed1);
        $this->assertSame(
            SiroIdFactura::generar(3284, 86, 1),
            SiroDescargaRendicionBarcodeComprobante448::idFacturaDesdeParseLegacy($parsed1, 1),
        );

        $linea5 = SiroDescargaRendicionLinea::parsear(rtrim($lineas[4], "\r\n"));
        $this->assertNotNull($linea5);
        $parsed5 = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($linea5['codigoBarras']);
        $this->assertNotNull($parsed5);
        $this->assertSame(3134, $parsed5['idLegajos']);
        $this->assertSame(86, $parsed5['idCuotas']);
        $this->assertSame(
            SiroIdFactura::generar(3134, 86, 1),
            SiroDescargaRendicionBarcodeComprobante448::idFacturaDesdeParseLegacy($parsed5, 1),
        );
    }
}
