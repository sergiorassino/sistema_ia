<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionBarcodeComprobante448;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionIdClienteExtendido;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionLinea;
use App\Support\Cuotas\Siro\SiroIdFactura;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionIdClienteExtendidoTest extends TestCase
{
    public function test_relleno_legacy_codifica_y_decodifica_ult_upload(): void
    {
        $this->assertSame('010000', SiroDescargaRendicionBarcodeComprobante448::rellenoDesdeUltUpload(1));
        $this->assertSame(5, SiroDescargaRendicionBarcodeComprobante448::ultUploadDesdeRelleno('050000'));
        $this->assertNull(SiroDescargaRendicionBarcodeComprobante448::ultUploadDesdeRelleno('000000'));
    }

    public function test_ident_usuario_legacy_con_ult_en_relleno(): void
    {
        $ident = SiroDescargaRendicionBarcodeComprobante448::armarIdentUsuarioLegacy('1', 3284, 86, 3);
        $this->assertSame('103284086030000', $ident);
        $this->assertSame(3, SiroDescargaRendicionBarcodeComprobante448::ultUploadDesdeIdentUsuarioLegacy($ident));
    }

    public function test_archivo_san_fra_id_cliente_extendido_desde_cola(): void
    {
        $f = 'd:/_enviar/_06-Junio/sanfra/nuevo/CobranzasSiro_Cta. 1102_20260625txt.txt';
        if (! is_readable($f)) {
            $this->markTestSkipped('Archivo de rendición de ejemplo no disponible.');
        }

        foreach (file($f) as $raw) {
            $linea = SiroDescargaRendicionLinea::parsear(rtrim($raw, "\r\n"));
            if ($linea === null || ! str_starts_with($linea['codigoBarras'], '0448')) {
                continue;
            }

            $desdeCola = SiroDescargaRendicionIdClienteExtendido::desdeColaLinea($linea['cadenaPago']);
            $this->assertNotSame('', $desdeCola);
            $this->assertSame($desdeCola, $linea['idClienteExtendido']);
        }
    }

    public function test_cola_extendida_recompone_concepto_cuando_faltan_15_digitos(): void
    {
        $cadena = str_repeat(' ', 272).'4'.str_repeat(' ', 100).'01530084000000';
        $this->assertSame(
            '401530084000000',
            SiroDescargaRendicionIdClienteExtendido::desdeColaLinea($cadena),
        );
    }

    public function test_id_factura_desde_ident_legacy_con_ult_codificado(): void
    {
        $ident = SiroDescargaRendicionBarcodeComprobante448::armarIdentUsuarioLegacy('1', 3284, 86, 2);
        $barcode = '0448'.$ident.'26061200109800001400122000000515001102925';
        $parsed = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($barcode);
        $this->assertNotNull($parsed);
        $this->assertSame(2, $parsed['ultUpload']);
        $this->assertSame(
            SiroIdFactura::generar(3284, 86, 2),
            SiroDescargaRendicionBarcodeComprobante448::idFacturaDesdeParseLegacy($parsed, 2),
        );
    }
}
