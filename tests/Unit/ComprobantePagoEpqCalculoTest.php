<?php

namespace Tests\Unit;

use App\Support\Alumnos\ComprobantePagoCodigoBarras;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionBarcodeComprobante448;
use PHPUnit\Framework\TestCase;

class ComprobantePagoEpqCalculoTest extends TestCase
{
    public function test_barcode_epq_usa_cuenta_del_nivel_y_ident_legacy(): void
    {
        $identUsuario = SiroDescargaRendicionBarcodeComprobante448::armarIdentUsuarioLegacy('1', 12345, 86, 1);
        $cuentaNivel = '5150011052';
        $partes = [
            'empresaServicio' => '0448',
            'identUsuario' => $identUsuario,
            'fecha1erVenc' => '260630',
            'importe1erVenc' => '0000015000',
            'dias2doVenc' => '15',
            'importe2doVenc' => '0000016500',
            'numeroCuenta' => $cuentaNivel,
        ];

        $barra = ComprobantePagoCodigoBarras::armar($partes);

        $this->assertSame(59, strlen($barra));
        $this->assertStringStartsWith('0448', $barra);
        $this->assertStringContainsString($cuentaNivel, $barra);
        $this->assertStringNotContainsString('5120281108', $barra);

        $parsed = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($barra);
        $this->assertNotNull($parsed);
        $this->assertSame(12345, $parsed['idLegajos']);
        $this->assertSame(86, $parsed['idCuotas']);
        $this->assertSame('1', $parsed['concepto']);
    }
}
