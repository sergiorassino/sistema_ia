<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionBarcodeComprobante448;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionLinea;
use App\Support\Cuotas\Siro\SiroIdFactura;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionBarcode448ComprobanteTest extends TestCase
{
    public function test_parsea_ident_usuario_del_cupon_impreso(): void
    {
        $barcode = '04481028630860000002606120009990000140011100000515001105292';
        $parsed = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($barcode);

        $this->assertNotNull($parsed);
        $this->assertSame(2863, $parsed['idLegajos']);
        $this->assertSame(86, $parsed['idCuotas']);
        $this->assertSame('260612', $parsed['fecha1erVenc']);
    }

    public function test_id_factura_desde_parse_con_ult_upload(): void
    {
        $barcode = '04481032840860000002606120010980000140012200000515001102925';
        $parsed = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($barcode);
        $this->assertNotNull($parsed);

        $this->assertSame(
            SiroIdFactura::generar(3284, 86, 1),
            SiroDescargaRendicionBarcodeComprobante448::idFacturaDesdeParseLegacy($parsed, 1),
        );
    }

    public function test_id_factura_desde_parse_rechaza_ult_cero(): void
    {
        $barcode = '04481032840860000002606120010980000140012200000515001102925';
        $parsed = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($barcode);
        $this->assertNotNull($parsed);
        $this->assertNull(SiroDescargaRendicionBarcodeComprobante448::idFacturaDesdeParseLegacy($parsed, 0));
    }

    public function test_archivo_1105_lineas_pf_rp_tienen_id_factura(): void
    {
        $f = 'd:/_enviar/_06-Junio/sanfra/nuevo/CobranzasSiro_Cta. 1105_20260625txt.txt';
        if (! is_readable($f)) {
            $this->markTestSkipped('Archivo de rendición de ejemplo no disponible.');
        }

        $esperados = [
            2 => [2863, 86, 1],
            3 => [2877, 86, 1],
            4 => [2585, 85, 1],
            7 => [2585, 86, 1],
        ];

        $lineas = file($f);
        foreach ($esperados as $numero => [$legajo, $idCuotas, $ultUpload]) {
            $linea = SiroDescargaRendicionLinea::parsear(rtrim($lineas[$numero - 1], "\r\n"));
            $this->assertNotNull($linea, 'Línea '.$numero);
            $parsed = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($linea['codigoBarras']);
            $this->assertNotNull($parsed, 'Línea '.$numero);
            $this->assertSame(
                SiroIdFactura::generar($legajo, $idCuotas, $ultUpload),
                SiroDescargaRendicionBarcodeComprobante448::idFacturaDesdeParseLegacy($parsed, $ultUpload),
                'Línea '.$numero,
            );
        }
    }
}
